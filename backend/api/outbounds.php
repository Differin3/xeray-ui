<?php
// Xeray UI - API для управления outbounds

require_once '../core/config.php';
require_once '../core/database.php';

header('Content-Type: application/json');

// Проверка авторизации
session_start();
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    sendError('Доступ запрещен', 403);
}

$db = Database::getInstance();

// Определение действия
$action = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['action'] ?? '';

try {
    switch ($endpoint) {
        case 'list':
            // Получение списка всех outbounds
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            // Фильтры
            $serverId = $_GET['server_id'] ?? null;

            // Базовый запрос
            $query = "
                SELECT o.*, s.name as server_name, s.location as server_location
                FROM outbounds o
                JOIN servers s ON o.server_id = s.id
            ";

            $params = [];

            // Фильтрация по серверу
            if ($serverId) {
                $query .= " WHERE o.server_id = ?";
                $params[] = $serverId;
            }

            $query .= " ORDER BY s.name, o.name";

            $outbounds = $db->fetchAll($query, $params);

            // Форматирование данных
            foreach ($outbounds as &$outbound) {
                $outbound['settings'] = json_decode($outbound['settings'], true);
                $outbound['enabled'] = (bool)$outbound['enabled'];
            }

            sendSuccess($outbounds);
            break;

        case 'get':
            // Получение информации о конкретном outbound
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID outbound', 400);
            }

            $outbound = $db->fetchOne("
                SELECT o.*, s.name as server_name, s.location as server_location
                FROM outbounds o
                JOIN servers s ON o.server_id = s.id
                WHERE o.id = ?
            ", [$_GET['id']]);

            if (!$outbound) {
                sendError('Outbound не найден', 404);
            }

            // Форматирование данных
            $outbound['settings'] = json_decode($outbound['settings'], true);
            $outbound['enabled'] = (bool)$outbound['enabled'];

            sendSuccess($outbound);
            break;

        case 'add':
            // Добавление нового outbound
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            // Получение данных из POST запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Валидация данных
            $required = ['server_id', 'name', 'type', 'settings'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    sendError("Поле $field обязательно для заполнения", 400);
                }
            }

            // Проверка существования сервера
            $server = $db->fetchOne("SELECT id FROM servers WHERE id = ?", [$input['server_id']]);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            // Добавление outbound
            $outboundId = $db->insert('outbounds', [
                'server_id' => $input['server_id'],
                'name' => $input['name'],
                'type' => $input['type'],
                'settings' => json_encode($input['settings']),
                'enabled' => $input['enabled'] ?? 1
            ]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverManager->createXrayConfig($input['server_id'], $input);

            sendSuccess(['id' => $outboundId], 'Outbound успешно добавлен');
            break;

        case 'update':
            // Обновление информации об outbound
            if ($action !== 'PUT') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID outbound', 400);
            }

            // Проверка существования outbound
            $outbound = $db->fetchOne("SELECT * FROM outbounds WHERE id = ?", [$_GET['id']]);
            if (!$outbound) {
                sendError('Outbound не найден', 404);
            }

            // Получение данных из PUT запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Проверка существования сервера
            if (isset($input['server_id'])) {
                $server = $db->fetchOne("SELECT id FROM servers WHERE id = ?", [$input['server_id']]);
                if (!$server) {
                    sendError('Сервер не найден', 404);
                }
            }

            // Обновление outbound
            $updateData = [
                'name' => $input['name'] ?? $outbound['name'],
                'type' => $input['type'] ?? $outbound['type'],
                'settings' => isset($input['settings']) ? json_encode($input['settings']) : $outbound['settings'],
                'enabled' => $input['enabled'] ?? $outbound['enabled']
            ];

            if (isset($input['server_id'])) {
                $updateData['server_id'] = $input['server_id'];
            }

            $db->update('outbounds', $updateData, 'id = ?', [$_GET['id']]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverData = [
                'config_path' => $outbound['config_path'],
                'server_id' => $outbound['server_id']
            ];

            if (isset($input['server_id'])) {
                $serverData['server_id'] = $input['server_id'];
            }

            $serverManager->createXrayConfig($outbound['server_id'], $serverData);

            sendSuccess(null, 'Outbound успешно обновлен');
            break;

        case 'delete':
            // Удаление outbound
            if ($action !== 'DELETE') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID outbound', 400);
            }

            // Проверка существования outbound
            $outbound = $db->fetchOne("SELECT * FROM outbounds WHERE id = ?", [$_GET['id']]);
            if (!$outbound) {
                sendError('Outbound не найден', 404);
            }

            // Удаление outbound
            $db->delete('outbounds', 'id = ?', [$_GET['id']]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverData = [
                'config_path' => $outbound['config_path'],
                'server_id' => $outbound['server_id']
            ];

            $serverManager->createXrayConfig($outbound['server_id'], $serverData);

            sendSuccess(null, 'Outbound успешно удален');
            break;

        case 'toggle':
            // Включение/отключение outbound
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID outbound', 400);
            }

            // Проверка существования outbound
            $outbound = $db->fetchOne("SELECT * FROM outbounds WHERE id = ?", [$_GET['id']]);
            if (!$outbound) {
                sendError('Outbound не найден', 404);
            }

            // Переключение состояния
            $newStatus = !$outbound['enabled'];

            $db->update('outbounds', [
                'enabled' => $newStatus
            ], 'id = ?', [$_GET['id']]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverData = [
                'config_path' => $outbound['config_path'],
                'server_id' => $outbound['server_id']
            ];

            $serverManager->createXrayConfig($outbound['server_id'], $serverData);

            sendSuccess(null, 'Outbound успешно ' . ($newStatus ? 'включен' : 'отключен'));
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API outbounds: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
