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
                'config_path' => $data['config_path'],
                'daemon_id' => $data['daemon_id'] ?? null
            ]);

            // Создание конфигурации XRay
            $this->createXrayConfig($serverId, $data);

            // Установка соединения с демоном
            $this->connectToDaemon($serverId, $data);

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

            // Обновление соединения с демоном
            $this->connectToDaemon($id, $data);

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

            // Отключение от демона
            $this->disconnectFromDaemon($id);

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

            // Проверка статуса через демона
            return $this->checkDaemonStatus($id);
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
                'Статус сервера "' . $server['name'] . '" изменен на: ' . $status,
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

    // Перезапуск XRay на сервере через демона
    public function restartXray($id) {
        try {
            $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);

            if (!$server) {
                throw new Exception('Сервер не найден');
            }

            // Отправка команды демону на перезапуск XRay
            $result = $this->sendDaemonCommand($id, 'restart_xray', []);

            if (!$result['success']) {
                throw new Exception($result['message']);
            }

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

    // Подключение к демону для управления сервером
    private function connectToDaemon($serverId, $data) {
        try {
            $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$serverId]);

            // Отправка запроса на подключение к демону
            $daemonData = [
                'server_id' => $serverId,
                'server_name' => $server['name'],
                'server_ip' => $server['ip_address'],
                'server_port' => $server['port'],
                'config_path' => $server['config_path']
            ];

            $result = $this->sendDaemonRequest('connect', $daemonData);

            if (!$result['success']) {
                throw new Exception('Не удалось подключиться к демону: ' . $result['message']);
            }

            // Обновляем ID демона в базе данных
            $this->db->update('servers', [
                'daemon_id' => $result['daemon_id'],
                'status' => 'connected'
            ], 'id = ?', [$serverId]);

            return $result['daemon_id'];
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка подключения к демону: ' . $e->getMessage());
            throw $e;
        }
    }

    // Отключение от демона
    private function disconnectFromDaemon($serverId) {
        try {
            $server = $this->db->fetchOne("SELECT daemon_id FROM servers WHERE id = ?", [$serverId]);

            if ($server && $server['daemon_id']) {
                // Отправляем запрос на отключение
                $result = $this->sendDaemonRequest('disconnect', ['server_id' => $serverId, 'daemon_id' => $server['daemon_id']]);

                if ($result['success']) {
                    // Обновляем статус сервера
                    $this->db->update('servers', [
                        'daemon_id' => null,
                        'status' => 'disconnected'
                    ], 'id = ?', [$serverId]);
                }
            }

            return true;
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка отключения от демона: ' . $e->getMessage());
            return false;
        }
    }

    // Проверка статуса сервера через демона
    private function checkDaemonStatus($serverId) {
        try {
            $server = $this->db->fetchOne("SELECT daemon_id FROM servers WHERE id = ?", [$serverId]);

            if (!$server || !$server['daemon_id']) {
                return 'offline';
            }

            $result = $this->sendDaemonRequest('check_status', [
                'server_id' => $serverId,
                'daemon_id' => $server['daemon_id']
            ]);

            if ($result['success']) {
                // Обновляем статус сервера
                $this->updateServerStatus($serverId, $result['status']);
                return $result['status'];
            } else {
                return 'offline';
            }
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка проверки статуса через демон: ' . $e->getMessage());
            return 'offline';
        }
    }

    // Отправка команды демону
    private function sendDaemonCommand($serverId, $command, $params = []) {
        try {
            $server = $this->db->fetchOne("SELECT daemon_id FROM servers WHERE id = ?", [$serverId]);

            if (!$server || !$server['daemon_id']) {
                return ['success' => false, 'message' => 'Сервер не подключен к демону'];
            }

            $data = [
                'server_id' => $serverId,
                'daemon_id' => $server['daemon_id'],
                'command' => $command,
                'params' => $params,
                'timestamp' => time(),
                'signature' => hash_hmac('sha256', json_encode($params), DAEMON_SECRET)
            ];

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'http://' . DAEMON_HOST . ':' . DAEMON_PORT . '/command');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Daemon-Secret: ' . DAEMON_SECRET
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => 'Ошибка HTTP: ' . $httpCode];
            }

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Некорректный ответ от демона'];
            }

            return $result;
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка отправки команды демону: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Отправка запроса демону
    private function sendDaemonRequest($endpoint, $data = []) {
        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'http://' . DAEMON_HOST . ':' . DAEMON_PORT . '/' . $endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Daemon-Secret: ' . DAEMON_SECRET
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => 'Ошибка HTTP: ' . $httpCode];
            }

            $result = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Некорректный ответ от демона'];
            }

            return $result;
        } catch (Exception $e) {
            logMessage('ERROR', 'Ошибка отправки запроса демону: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
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
