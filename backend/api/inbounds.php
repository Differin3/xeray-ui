<?php
// Xeray UI - API для управления inbounds

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
            // Получение списка всех inbounds
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            // Фильтры
            $serverId = $_GET['server_id'] ?? null;

            // Базовый запрос
            $query = "
                SELECT i.*, s.name as server_name, s.location as server_location
                FROM inbounds i
                JOIN servers s ON i.server_id = s.id
            ";

            $params = [];

            // Фильтрация по серверу
            if ($serverId) {
                $query .= " WHERE i.server_id = ?";
                $params[] = $serverId;
            }

            $query .= " ORDER BY s.name, i.name";

            $inbounds = $db->fetchAll($query, $params);

            // Форматирование данных
            foreach ($inbounds as &$inbound) {
                $inbound['settings'] = json_decode($inbound['settings'], true);
                $inbound['enabled'] = (bool)$inbound['enabled'];
            }

            sendSuccess($inbounds);
            break;

        case 'get':
            // Получение информации о конкретном inbound
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID inbound', 400);
            }

            $inbound = $db->fetchOne("
                SELECT i.*, s.name as server_name, s.location as server_location
                FROM inbounds i
                JOIN servers s ON i.server_id = s.id
                WHERE i.id = ?
            ", [$_GET['id']]);

            if (!$inbound) {
                sendError('Inbound не найден', 404);
            }

            // Форматирование данных
            $inbound['settings'] = json_decode($inbound['settings'], true);
            $inbound['enabled'] = (bool)$inbound['enabled'];

            sendSuccess($inbound);
            break;

        case 'add':
            // Добавление нового inbound
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            // Получение данных из POST запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Валидация данных
            $required = ['server_id', 'name', 'type', 'port', 'settings'];
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

            // Проверка уникальности порта на сервере
            $existing = $db->fetchOne("SELECT id FROM inbounds WHERE server_id = ? AND port = ?", [
                $input['server_id'],
                $input['port']
            ]);

            if ($existing) {
                sendError('Inbound с таким портом уже существует на этом сервере', 400);
            }

            // Добавление inbound
            $inboundId = $db->insert('inbounds', [
                'server_id' => $input['server_id'],
                'name' => $input['name'],
                'type' => $input['type'],
                'port' => $input['port'],
                'settings' => json_encode($input['settings']),
                'enabled' => $input['enabled'] ?? 1
            ]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverManager->createXrayConfig($input['server_id'], $input);

            sendSuccess(['id' => $inboundId], 'Inbound успешно добавлен');
            break;

        case 'update':
            // Обновление информации об inbound
            if ($action !== 'PUT') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID inbound', 400);
            }

            // Проверка существования inbound
            $inbound = $db->fetchOne("SELECT * FROM inbounds WHERE id = ?", [$_GET['id']]);
            if (!$inbound) {
                sendError('Inbound не найден', 404);
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

            // Проверка уникальности порта на сервере
            if (isset($input['port']) && isset($input['server_id'])) {
                $existing = $db->fetchOne("SELECT id FROM inbounds WHERE server_id = ? AND port = ? AND id <> ?", [
                    $input['server_id'],
                    $input['port'],
                    $_GET['id']
                ]);

                if ($existing) {
                    sendError('Inbound с таким портом уже существует на этом сервере', 400);
                }
            }

            // Обновление inbound
            $updateData = [
                'name' => $input['name'] ?? $inbound['name'],
                'type' => $input['type'] ?? $inbound['type'],
                'port' => $input['port'] ?? $inbound['port'],
                'settings' => isset($input['settings']) ? json_encode($input['settings']) : $inbound['settings'],
                'enabled' => $input['enabled'] ?? $inbound['enabled']
            ];

            if (isset($input['server_id'])) {
                $updateData['server_id'] = $input['server_id'];
            }

            $db->update('inbounds', $updateData, 'id = ?', [$_GET['id']]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverData = [
                'config_path' => $inbound['config_path'],
                'server_id' => $inbound['server_id']
            ];

            if (isset($input['server_id'])) {
                $serverData['server_id'] = $input['server_id'];
            }

            $serverManager->createXrayConfig($inbound['server_id'], $serverData);

            sendSuccess(null, 'Inbound успешно обновлен');
            break;

        case 'delete':
            // Удаление inbound
            if ($action !== 'DELETE') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID inbound', 400);
            }

            // Проверка существования inbound
            $inbound = $db->fetchOne("SELECT * FROM inbounds WHERE id = ?", [$_GET['id']]);
            if (!$inbound) {
                sendError('Inbound не найден', 404);
            }

            // Удаление inbound
            $db->delete('inbounds', 'id = ?', [$_GET['id']]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverData = [
                'config_path' => $inbound['config_path'],
                'server_id' => $inbound['server_id']
            ];

            $serverManager->createXrayConfig($inbound['server_id'], $serverData);

            sendSuccess(null, 'Inbound успешно удален');
            break;

        case 'toggle':
            // Включение/отключение inbound
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID inbound', 400);
            }

            // Проверка существования inbound
            $inbound = $db->fetchOne("SELECT * FROM inbounds WHERE id = ?", [$_GET['id']]);
            if (!$inbound) {
                sendError('Inbound не найден', 404);
            }

            // Переключение состояния
            $newStatus = !$inbound['enabled'];

            $db->update('inbounds', [
                'enabled' => $newStatus
            ], 'id = ?', [$_GET['id']]);

            // Обновление конфигурации XRay на сервере
            require_once '../core/server_manager_new.php';
            $serverManager = new ServerManager();
            $serverData = [
                'config_path' => $inbound['config_path'],
                'server_id' => $inbound['server_id']
            ];

            $serverManager->createXrayConfig($inbound['server_id'], $serverData);

            sendSuccess(null, 'Inbound успешно ' . ($newStatus ? 'включен' : 'отключен'));
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API inbounds: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
