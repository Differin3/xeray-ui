#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Xeray UI - Демон для управления серверами XRay
Используется для удаленного управления серверами без SSH
"""

import asyncio
import json
import logging
import os
import signal
import sys
import time
import hashlib
import hmac
from datetime import datetime, timedelta
from typing import Dict, Any, Optional, List

import aiohttp
import aiohttp.web
import sqlite3

# Добавляем корневую директорию в sys.path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Импортируем конфигурацию
try:
    from config import DAEMON_SECRET, DAEMON_HOST, DAEMON_PORT, LOG_LEVEL, LOG_PATH
except ImportError:
    # Если конфиг не найден, используем значения по умолчанию
    DAEMON_SECRET = 'daemon-secret-key'
    DAEMON_HOST = '0.0.0.0'
    DAEMON_PORT = 8080
    LOG_LEVEL = 'INFO'
    LOG_PATH = 'logs'

# Создаем директорию для логов, если ее нет
os.makedirs(LOG_PATH, exist_ok=True)

# Настройка логирования
log_format = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL),
    format=log_format,
    handlers=[
        logging.FileHandler(os.path.join(LOG_PATH, 'daemon.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)


class XrayDaemon:
    def __init__(self, host: str = '0.0.0.0', port: int = 8080, secret: str = 'daemon-secret-key'):
        """
        Инициализация демона

        :param host: Хост для запуска сервера
        :param port: Порт для запуска сервера
        :param secret: Секретный ключ для аутентификации
        """
        self.host = host
        self.port = port
        self.secret = secret
        self.running = False
        self.servers = {}  # Словарь подключенных серверов {server_id: server_info}
        self.app = None
        self.runner = None
        self.site = None

        # Список задач для мониторинга
        self.monitoring_tasks = []

        # Интервалы для проверки статуса
        self.status_check_interval = 30  # секунд

        # Счетчик для отслеживания времени последней проверки
        self.last_status_check = 0

        # Инициализация базы данных
        self.init_database()

        # Обработка сигналов завершения
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)

        logger.info(f"Демон инициализирован с хостом {host} и портом {port}")


    def init_database(self):
        """Инициализация базы данных для хранения информации о серверах"""
        self.db_path = 'daemon.db'

        # Убедимся, что директория для базы данных существует
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)

        # Создаем базу данных, если ее нет
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        # Создаем таблицу серверов
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                daemon_id TEXT NOT NULL,
                name TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                port INTEGER NOT NULL,
                config_path TEXT NOT NULL,
                status TEXT DEFAULT 'offline',
                last_heartbeat DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)

        # Создаем таблицу статистики
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS statistics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                connections_count INTEGER DEFAULT 0,
                traffic_up INTEGER DEFAULT 0,
                traffic_down INTEGER DEFAULT 0,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
            )
        """)

        # Создаем таблицу логов операций
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS operation_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id INTEGER,
                operation TEXT NOT NULL,
                details TEXT,
                status TEXT DEFAULT 'success',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL
            )
        """)

        # Создаем уникальный индекс для server_id
        cursor.execute("""
            CREATE UNIQUE INDEX IF NOT EXISTS idx_server_id ON servers(server_id)
        """)

        # Создаем индекс для таблицы статистики
        cursor.execute("""
            CREATE INDEX IF NOT EXISTS idx_statistics_server ON statistics(server_id)
        """)

        # Создаем индекс для таблицы логов
        cursor.execute("""
            CREATE INDEX IF NOT EXISTS idx_logs_server ON operation_logs(server_id)
        """)

        conn.commit()
        conn.close()

        logger.info("База данных демона инициализирована")


    def signal_handler(self, signum, frame):
        """Обработчик сигналов для корректного завершения работы"""
        logger.info(f"Получен сигнал {signum}, завершение работы...")
        self.stop()

    async def start(self):
        """Запуск демона"""
        logger.info(f"Запуск демона на {self.host}:{self.port}")

        # Создаем aiohttp приложение
        self.app = aiohttp.web.Application()

        # Регистрируем маршруты
        self.app.router.add_post('/api/{command}', self.handle_command)
        self.app.router.add_get('/health', self.health_check)
        self.app.router.add_get('/servers', self.list_servers)
        self.app.router.add_get('/server/{server_id}', self.get_server_info)

        # Запускаем сервер
        self.runner = aiohttp.web.AppRunner(self.app)
        await self.runner.setup()

        self.site = aiohttp.web.TCPSite(self.runner, self.host, self.port)
        await self.site.start()

        self.running = True
        logger.info("Демон запущен успешно")

        # Запускаем задачи мониторинга
        asyncio.create_task(self.monitor_servers())
        asyncio.create_task(self.cleanup_inactive_servers())

        logger.info("Все задачи мониторинга запущены")


    async def stop(self):
        """Остановка демона"""
        logger.info("Остановка демона...")
        self.running = False

        if self.site:
            await self.site.stop()

        if self.runner:
            await self.runner.cleanup()

        logger.info("Демон остановлен")

    async def handle_command(self, request: aiohttp.web.Request):
        """Обработка входящих команд"""
        try:
            # Проверка секретного ключа
            auth_header = request.headers.get('X-Auth-Key')
            if not auth_header or auth_header != self.secret:
                return aiohttp.web.json_response(
                    {'success': False, 'message': 'Неверный секретный ключ'},
                    status=401
                )

            # Получение команды и параметров
            command = request.match_info['command']
            try:
                data = await request.json()
            except:
                data = {}

            # Обработка команды
            result = await self.process_command(command, data)

            return aiohttp.web.json_response(result)

        except Exception as e:
            logger.error(f"Ошибка обработки команды: {e}")
            return aiohttp.web.json_response(
                {'success': False, 'message': str(e)},
                status=500
            )

    async def process_command(self, command: str, data: Dict[str, Any]) -> Dict[str, Any]:
        """Обработка конкретной команды"""
        try:
            if command == 'connect':
                return await self.cmd_connect(data)
            elif command == 'disconnect':
                return await self.cmd_disconnect(data)
            elif command == 'check_status':
                return await self.cmd_check_status(data)
            elif command == 'restart_xray':
                return await self.cmd_restart_xray(data)
            elif command == 'get_stats':
                return await self.cmd_get_stats(data)
            elif command == 'update_config':
                return await self.cmd_update_config(data)
            else:
                return {'success': False, 'message': f'Неизвестная команда: {command}'}

        except Exception as e:
            logger.error(f"Ошибка выполнения команды {command}: {e}")
            return {'success': False, 'message': str(e)}

    async def cmd_connect(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Команда подключения сервера"""
        server_id = data.get('server_id')
        server_name = data.get('server_name')
        server_ip = data.get('server_ip')
        server_port = data.get('server_port')
        config_path = data.get('config_path')

        if not all([server_id, server_name, server_ip, server_port, config_path]):
            return {'success': False, 'message': 'Недостаточно данных для подключения'}

        # Генерируем уникальный ID для демона
        daemon_id = f"daemon_{server_id}_{int(time.time())}"

        # Сохраняем информацию о сервере в базе данных
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        try:
            # Проверяем, не подключен ли уже сервер
            cursor.execute("SELECT daemon_id FROM servers WHERE server_id = ?", (server_id,))
            existing = cursor.fetchone()

            if existing:
                # Обновляем существующую запись
                cursor.execute("""
                    UPDATE servers SET
                        daemon_id = ?,
                        name = ?,
                        ip_address = ?,
                        port = ?,
                        config_path = ?,
                        status = 'connected',
                        last_heartbeat = CURRENT_TIMESTAMP
                    WHERE server_id = ?
                """, (daemon_id, server_name, server_ip, server_port, config_path, server_id))
            else:
                # Добавляем новую запись
                cursor.execute("""
                    INSERT INTO servers (
                        server_id, daemon_id, name, ip_address, port, config_path, status, last_heartbeat
                    ) VALUES (?, ?, ?, ?, ?, ?, 'connected', CURRENT_TIMESTAMP)
                """, (server_id, daemon_id, server_name, server_ip, server_port, config_path))

            conn.commit()

            # Сохраняем информацию в память
            self.servers[server_id] = {
                'daemon_id': daemon_id,
                'name': server_name,
                'ip_address': server_ip,
                'port': server_port,
                'config_path': config_path,
                'status': 'connected',
                'last_heartbeat': datetime.now()
            }

            logger.info(f"Сервер {server_name} ({server_id}) подключен к демону")

            return {'success': True, 'daemon_id': daemon_id}

        except Exception as e:
            conn.rollback()
            logger.error(f"Ошибка подключения сервера: {e}")
            return {'success': False, 'message': str(e)}
        finally:
            conn.close()

    async def cmd_disconnect(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Команда отключения сервера"""
        server_id = data.get('server_id')
        daemon_id = data.get('daemon_id')

        if not server_id:
            return {'success': False, 'message': 'Не указан ID сервера'}

        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        try:
            # Проверяем, существует ли сервер
            cursor.execute("SELECT daemon_id FROM servers WHERE server_id = ?", (server_id,))
            existing = cursor.fetchone()

            if not existing:
                return {'success': False, 'message': 'Сервер не найден'}

            # Если указан daemon_id, проверяем его
            if daemon_id and existing[0] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            # Удаляем сервер из базы данных
            cursor.execute("DELETE FROM servers WHERE server_id = ?", (server_id,))
            conn.commit()

            # Удаляем из памяти
            if server_id in self.servers:
                del self.servers[server_id]

            logger.info(f"Сервер {server_id} отключен от демона")

            return {'success': True}

        except Exception as e:
            conn.rollback()
            logger.error(f"Ошибка отключения сервера: {e}")
            return {'success': False, 'message': str(e)}
        finally:
            conn.close()

    async def cmd_check_status(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Команда проверки статуса сервера"""
        server_id = data.get('server_id')
        daemon_id = data.get('daemon_id')

        if not server_id:
            return {'success': False, 'message': 'Не указан ID сервера'}

        # Проверяем в памяти
        if server_id in self.servers:
            server = self.servers[server_id]

            # Обновляем время последнего сердцебиения
            server['last_heartbeat'] = datetime.now()

            # Проверяем доступность XRay
            xray_status = await self.check_xray_status(server)

            # Обновляем статус
            server['status'] = xray_status

            return {
                'success': True,
                'status': xray_status,
                'last_heartbeat': server['last_heartbeat'].isoformat()
            }

        # Проверяем в базе данных
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        try:
            cursor.execute("SELECT * FROM servers WHERE server_id = ?", (server_id,))
            server_data = cursor.fetchone()

            if not server_data:
                return {'success': False, 'message': 'Сервер не найден'}

            # Если указан daemon_id, проверяем его
            if daemon_id and server_data[1] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            # Загружаем данные в память
            server_info = {
                'daemon_id': server_data[1],
                'name': server_data[2],
                'ip_address': server_data[3],
                'port': server_data[4],
                'config_path': server_data[5],
                'status': server_data[6],
                'last_heartbeat': datetime.fromisoformat(server_data[7]) if server_data[7] else None
            }

            # Проверяем доступность XRay
            xray_status = await self.check_xray_status(server_info)

            # Обновляем статус в базе данных
            cursor.execute("""
                UPDATE servers SET status = ?, last_heartbeat = CURRENT_TIMESTAMP
                WHERE server_id = ?
            """, (xray_status, server_id))
            conn.commit()

            # Сохраняем в память
            self.servers[server_id] = server_info

            return {
                'success': True,
                'status': xray_status,
                'last_heartbeat': datetime.now().isoformat()
            }

        except Exception as e:
            conn.rollback()
            logger.error(f"Ошибка проверки статуса сервера: {e}")
            return {'success': False, 'message': str(e)}
        finally:
            conn.close()

    async def cmd_restart_xray(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Команда перезапуска XRay на сервере"""
        server_id = data.get('server_id')
        daemon_id = data.get('daemon_id')

        if not server_id:
            return {'success': False, 'message': 'Не указан ID сервера'}

        # Проверяем в памяти
        if server_id in self.servers:
            server = self.servers[server_id]

            # Проверяем ID демона
            if daemon_id and server['daemon_id'] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            try:
                # Перезапускаем XRay
                await self.restart_xray_process(server)

                # Обновляем статус
                server['status'] = 'restarting'

                logger.info(f"XRay на сервере {server['name']} перезапущен")

                return {'success': True}

            except Exception as e:
                logger.error(f"Ошибка перезапуска XRay: {e}")
                return {'success': False, 'message': str(e)}

        # Проверяем в базе данных
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        try:
            cursor.execute("SELECT * FROM servers WHERE server_id = ?", (server_id,))
            server_data = cursor.fetchone()

            if not server_data:
                return {'success': False, 'message': 'Сервер не найден'}

            # Если указан daemon_id, проверяем его
            if daemon_id and server_data[1] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            # Загружаем данные
            server_info = {
                'daemon_id': server_data[1],
                'name': server_data[2],
                'ip_address': server_data[3],
                'port': server_data[4],
                'config_path': server_data[5],
                'status': server_data[6]
            }

            # Перезапускаем XRay
            await self.restart_xray_process(server_info)

            # Обновляем статус в базе данных
            cursor.execute("""
                UPDATE servers SET status = 'restarting', last_heartbeat = CURRENT_TIMESTAMP
                WHERE server_id = ?
            """, (server_id,))
            conn.commit()

            # Сохраняем в память
            self.servers[server_id] = server_info

            logger.info(f"XRay на сервере {server_info['name']} перезапущен")

            return {'success': True}

        except Exception as e:
            conn.rollback()
            logger.error(f"Ошибка перезапуска XRay: {e}")
            return {'success': False, 'message': str(e)}
        finally:
            conn.close()

    async def cmd_get_stats(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Команда получения статистики с сервера"""
        server_id = data.get('server_id')
        daemon_id = data.get('daemon_id')

        if not server_id:
            return {'success': False, 'message': 'Не указан ID сервера'}

        # Проверяем в памяти
        if server_id in self.servers:
            server = self.servers[server_id]

            # Проверяем ID демона
            if daemon_id and server['daemon_id'] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            try:
                # Получаем статистику
                stats = await self.get_xray_stats(server)

                return {
                    'success': True,
                    'stats': stats
                }

            except Exception as e:
                logger.error(f"Ошибка получения статистики: {e}")
                return {'success': False, 'message': str(e)}

        # Проверяем в базе данных
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        try:
            cursor.execute("SELECT * FROM servers WHERE server_id = ?", (server_id,))
            server_data = cursor.fetchone()

            if not server_data:
                return {'success': False, 'message': 'Сервер не найден'}

            # Если указан daemon_id, проверяем его
            if daemon_id and server_data[1] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            # Загружаем данные
            server_info = {
                'daemon_id': server_data[1],
                'name': server_data[2],
                'ip_address': server_data[3],
                'port': server_data[4],
                'config_path': server_data[5],
                'status': server_data[6]
            }

            # Получаем статистику
            stats = await self.get_xray_stats(server_info)

            return {
                'success': True,
                'stats': stats
            }

        except Exception as e:
            logger.error(f"Ошибка получения статистики: {e}")
            return {'success': False, 'message': str(e)}
        finally:
            conn.close()

    async def cmd_update_config(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Команда обновления конфигурации XRay"""
        server_id = data.get('server_id')
        daemon_id = data.get('daemon_id')
        config_data = data.get('config')

        if not server_id or not config_data:
            return {'success': False, 'message': 'Недостаточно данных'}

        # Проверяем в памяти
        if server_id in self.servers:
            server = self.servers[server_id]

            # Проверяем ID демона
            if daemon_id and server['daemon_id'] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            try:
                # Обновляем конфигурацию
                await self.update_xray_config(server, config_data)

                # Перезапускаем XRay для применения новой конфигурации
                await self.restart_xray_process(server)

                logger.info(f"Конфигурация XRay на сервере {server['name']} обновлена")

                return {'success': True}

            except Exception as e:
                logger.error(f"Ошибка обновления конфигурации: {e}")
                return {'success': False, 'message': str(e)}

        # Проверяем в базе данных
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()

        try:
            cursor.execute("SELECT * FROM servers WHERE server_id = ?", (server_id,))
            server_data = cursor.fetchone()

            if not server_data:
                return {'success': False, 'message': 'Сервер не найден'}

            # Если указан daemon_id, проверяем его
            if daemon_id and server_data[1] != daemon_id:
                return {'success': False, 'message': 'Неверный ID демона'}

            # Загружаем данные
            server_info = {
                'daemon_id': server_data[1],
                'name': server_data[2],
                'ip_address': server_data[3],
                'port': server_data[4],
                'config_path': server_data[5],
                'status': server_data[6]
            }

            # Обновляем конфигурацию
            await self.update_xray_config(server_info, config_data)

            # Перезапускаем XRay
            await self.restart_xray_process(server_info)

            logger.info(f"Конфигурация XRay на сервере {server_info['name']} обновлена")

            return {'success': True}

        except Exception as e:
            logger.error(f"Ошибка обновления конфигурации: {e}")
            return {'success': False, 'message': str(e)}
        finally:
            conn.close()

    async def check_xray_status(self, server: Dict[str, Any]) -> str:
        """Проверка статуса XRay на сервере"""
        try:
            # Здесь можно реализовать проверку статуса XRay
            # Например, через HTTP API или проверку порта

            # Для примера просто возвращаем 'online'
            return 'online'

        except Exception as e:
            logger.error(f"Ошибка проверки статуса XRay: {e}")
            return 'offline'

    async def restart_xray_process(self, server: Dict[str, Any]) -> bool:
        """Перезапуск процесса XRay"""
        try:
            # Здесь можно реализовать перезапуск XRay
            # Например, через системные команды или API

            # Для примера просто логируем
            logger.info(f"Перезапуск XRay на сервере {server['name']}")

            return True

        except Exception as e:
            logger.error(f"Ошибка перезапуска XRay: {e}")
            return False

    async def get_xray_stats(self, server: Dict[str, Any]) -> Dict[str, Any]:
        """Получение статистики XRay"""
        try:
            # Здесь можно реализовать получение статистики
            # Например, через HTTP API XRay

            # Для примера возвращаем тестовые данные
            return {
                'uptime': '1h 30m',
                'inbound_connections': 42,
                'outbound_connections': 38,
                'total_up': '1.2 GB',
                'total_down': '3.4 GB',
                'users': [
                    {'id': 1, 'email': 'user1@example.com', 'up': '500 MB', 'down': '1.2 GB'},
                    {'id': 2, 'email': 'user2@example.com', 'up': '700 MB', 'down': '2.2 GB'}
                ]
            }

        except Exception as e:
            logger.error(f"Ошибка получения статистики: {e}")
            return {}

    async def update_xray_config(self, server: Dict[str, Any], config: Dict[str, Any]) -> bool:
        """Обновление конфигурации XRay"""
        try:
            # Здесь можно реализовать обновление конфигурации
            # Например, через запись в файл конфигурации

            # Для примера просто логируем
            logger.info(f"Обновление конфигурации XRay на сервере {server['name']}")

            return True

        except Exception as e:
            logger.error(f"Ошибка обновления конфигурации: {e}")
            return False

    async def monitor_servers(self):
        """Мониторинг подключенных серверов"""
        while self.running:
            try:
                # Проверяем время последнего сердцебиания для каждого сервера
                current_time = datetime.now()

                for server_id, server in list(self.servers.items()):
                    # Если последнее сердцебиение было более 5 минут назад, считаем сервер оффлайн
                    if server['last_heartbeat'] and (current_time - server['last_heartbeat']).total_seconds() > 300:
                        server['status'] = 'offline'
                        logger.warning(f"Сервер {server['name']} ({server_id}) не отвечает")

                # Ждем 1 минуту до следующей проверки
                await asyncio.sleep(60)

            except Exception as e:
                logger.error(f"Ошибка мониторинга серверов: {e}")
                await asyncio.sleep(60)

def main():
    """Основная функция запуска демона"""
    import argparse

    # Парсинг аргументов командной строки
    parser = argparse.ArgumentParser(description='Xeray UI - Демон управления серверами XRay')
    parser.add_argument('--host', default='0.0.0.0', help='Хост для запуска сервера')
    parser.add_argument('--port', type=int, default=8080, help='Порт для запуска сервера')
    parser.add_argument('--secret', default='daemon-secret-key', help='Секретный ключ для аутентификации')
    args = parser.parse_args()

    # Создаем и запускаем демон
    daemon = XrayDaemon(host=args.host, port=args.port, secret=args.secret)

    try:
        # Запускаем демон
        asyncio.run(daemon.start())

    except KeyboardInterrupt:
        logger.info("Завершение работы по запросу пользователя...")
    except Exception as e:
        logger.error(f"Ошибка при запуске демона: {e}")
    finally:
        # Останавливаем демон
        if daemon.running:
            asyncio.run(daemon.stop())

if __name__ == '__main__':
    main()
