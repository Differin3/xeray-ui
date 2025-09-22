<?php
// Xeray UI - Управление серверами XRay

require_once 'config.php';
require_once 'database.php';

class ServerManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // Получение списка всех серверов
    public function getServers() {
        return $this->db->fetchAll("SELECT * FROM servers ORDER BY name");
    }

    // Получение информации о конкретном сервере
    public function getServer($id) {
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);

        if ($server) {
            // Получение inbounds для сервера
            $server['inbounds'] = $this->db->fetchAll("SELECT * FROM inbounds WHERE server_id = ?", [$id]);

            // Получение outbounds для сервера
            $server['outbounds'] = $this->db->fetchAll("SELECT * FROM outbounds WHERE server_id = ?", [$id]);
        }

        return $server;
    }

    // Добавление нового сервера
    public function addServer($data) {
        $this->db->beginTransaction();

        try {
            // Вставка сервера
            $serverId = $this->db->insert('servers', [
                'name' => $data['name'],
                'location' => $data['location'],
                'ip_address' => $data['ip_address'],
                'port' => $data['port'],
                'protocol' => $data['protocol'],
                'config_path' => $data['config_path']
            ]);

            // Создание конфигурации XRay
            $this->createXrayConfig($serverId, $data);

            // Логирование действия
            $this->logAction('add_server', 'Добавлен сервер: ' . $data['name'], null, $serverId);

            $this->db->commit();
            return $serverId;
        } catch (Exception $e) {
            $this->db->rollBack();
            logMessage('ERROR', 'Ошибка добавления сервера: ' . $e->getMessage());
            throw $e;
        }
    }

    // Обновление информации о сервере
    public function updateServer($id, $data) {
        $this->db->beginTransaction();

        try {
            // Обновление информации о сервере
            $this->db->update('servers', [
                'name' => $data['name'],
                'location' => $data['location'],
                'ip_address' => $data['ip_address'],
                'port' => $data['port'],
                'protocol' => $data['protocol'],
                'config_path' => $data['config_path'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);

            // Обновление конфигурации XRay
            $this->createXrayConfig($id, $data);

            // Логирование действия
            $this->logAction('update_server', 'Обновлен сервер: ' . $data['name'], null, $id);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            logMessage('ERROR', 'Ошибка обновления сервера: ' . $e->getMessage());
            throw $e;
        }
    }

    // Удаление сервера
    public function deleteServer($id) {
        $this->db->beginTransaction();

        try {
            // Получение имени сервера для логирования
            $server = $this->db->fetchOne("SELECT name FROM servers WHERE id = ?", [$id]);

            // Удаление сервера
            $this->db->delete('servers', 'id = ?', [$id]);

            // Удаление связанных inbounds и outbounds (сработает через CASCADE)

            // Логирование действия
            $this->logAction('delete_server', 'Удален сервер: ' . $server['name'], null, $id);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            logMessage('ERROR', 'Ошибка удаления сервера: ' . $e->getMessage());
            throw $e;
        }
    }

    // Проверка статуса сервера
    public function checkServerStatus($id) {
        try {
            $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);

            if (!$server) {
                return false;
            }

            // Здесь можно добавить проверку доступности сервера
            // Например, через SSH или ping

            // Временно всегда возвращаем online
            return 'online';
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка проверки статуса сервера: ' . $e->getMessage());
            return 'offline';
        }
    }

    // Обновление статуса сервера
    public function updateServerStatus($id, $status) {
        try {
            $server = $this->db->fetchOne("SELECT name FROM servers WHERE id = ?", [$id]);

            $this->db->update('servers', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);

            // Логирование изменения статуса
            $this->logAction('update_server_status', 
                'Статус сервера "' . $server['name] . '" изменен на: ' . $status, 
                null, 
                $id
            );

            return true;
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка обновления статуса сервера: ' . $e->getMessage());
            return false;
        }
    }

    // Создание конфигурации XRay для сервера
    private function createXrayConfig($serverId, $data) {
        try {
            // Получение inbounds и outbounds для сервера
            $inbounds = $this->db->fetchAll("SELECT * FROM inbounds WHERE server_id = ?", [$serverId]);
            $outbounds = $this->db->fetchAll("SELECT * FROM outbounds WHERE server_id = ?", [$serverId]);

            // Генерация конфигурации XRay
            $config = [
                'inbounds' => [],
                'outbounds' => []
            ];

            // Формирование inbounds
            foreach ($inbounds as $inbound) {
                $inboundConfig = json_decode($inbound['settings'], true);
                $inboundConfig['port'] = $inbound['port'];
                $inboundConfig['protocol'] = $inbound['type'];

                $config['inbounds'][] = $inboundConfig;
            }

            // Формирование outbounds
            foreach ($outbounds as $outbound) {
                $outboundConfig = json_decode($outbound['settings'], true);
                $outboundConfig['protocol'] = $outbound['type'];

                $config['outbounds'][] = $outboundConfig;
            }

            // Сохранение конфигурации
            $configPath = $data['config_path'];
            $configContent = json_encode($config, JSON_PRETTY_PRINT);

            // Запись конфигурации в файл
            file_put_contents($configPath, $configContent);

            // Логирование действия
            $this->logAction('create_xray_config', 'Создана конфигурация XRay для сервера ID: ' . $serverId, null, $serverId);

            return true;
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка создания конфигурации XRay: ' . $e->getMessage());
            throw $e;
        }
    }

    // Перезапуск XRay на сервере
    public function restartXray($id) {
        try {
            $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);

            if (!$server) {
                throw new Exception('Сервер не найден');
            }

            // Команда для перезапуска XRay
            $command = "systemctl restart xray";

            // Выполнение команды (здесь нужно реализовать удаленное выполнение через SSH)
            // Для примера просто логируем
            logMessage('INFO', "Выполняется команда: $command на сервере {$server['name']}");

            // Обновление статуса сервера
            $this->updateServerStatus($id, 'restarting');

            // Логирование действия
            $this->logAction('restart_xray', 'Перезапуск XRay на сервере: ' . $server['name'], null, $id);

            return true;
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка перезапуска XRay: ' . $e->getMessage());
            throw $e;
        }
    }

    // Логирование действий
    private function logAction($action, $details, $userId = null, $serverId = null) {
        return $this->db->insert('logs', [
            'user_id' => $userId,
            'server_id' => $serverId,
            'action' => $action,
            'details' => $details,
            'ip_address' => getClientIp()
        ]);
    }
}

// Инициализация менеджера серверов
$serverManager = new ServerManager();
