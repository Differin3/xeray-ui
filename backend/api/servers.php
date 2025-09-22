<?php
// Xeray UI - API для управления серверами

require_once '../core/config.php';
require_once '../core/server_manager_new.php';
require_once '../core/database.php';

header('Content-Type: application/json');

// Проверка авторизации
session_start();
if (!isset($_SESSION['user_id']) || !isAdmin()) {
    sendError('Доступ запрещен', 403);
}

$serverManager = new ServerManager();

// Определение действия
$action = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['action'] ?? '';

try {
    switch ($endpoint) {
        case 'list':
            // Получение списка всех серверов
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            $servers = $serverManager->getServers();
            sendSuccess($servers);
            break;

        case 'get':
            // Получение информации о конкретном сервере
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID сервера', 400);
            }

            $server = $serverManager->getServer($_GET['id']);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            sendSuccess($server);
            break;

        case 'add':
            // Добавление нового сервера
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            // Получение данных из POST запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Валидация данных
            $required = ['name', 'location', 'ip_address', 'port', 'protocol', 'config_path'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    sendError("Поле $field обязательно для заполнения", 400);
                }
            }

            // Валидация IP адреса
            if (!isValidIp($input['ip_address'])) {
                sendError('Неверный формат IP адреса', 400);
            }

            // Валидация порта
            if (!is_numeric($input['port']) || $input['port'] < 1 || $input['port'] > 65535) {
                sendError('Неверный номер порта', 400);
            }

            // Добавление сервера
            $serverId = $serverManager->addServer($input);
            sendSuccess(['id' => $serverId], 'Сервер успешно добавлен');
            break;

        case 'update':
            // Обновление информации о сервере
            if ($action !== 'PUT') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID сервера', 400);
            }

            // Получение данных из PUT запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Проверка существования сервера
            $server = $serverManager->getServer($_GET['id']);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            // Валидация данных
            if (isset($input['ip_address']) && !isValidIp($input['ip_address'])) {
                sendError('Неверный формат IP адреса', 400);
            }

            if (isset($input['port']) && (!is_numeric($input['port']) || $input['port'] < 1 || $input['port'] > 65535)) {
                sendError('Неверный номер порта', 400);
            }

            // Обновление сервера
            $serverManager->updateServer($_GET['id'], $input);
            sendSuccess(null, 'Сервер успешно обновлен');
            break;

        case 'delete':
            // Удаление сервера
            if ($action !== 'DELETE') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID сервера', 400);
            }

            // Проверка существования сервера
            $server = $serverManager->getServer($_GET['id']);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            // Удаление сервера
            $serverManager->deleteServer($_GET['id']);
            sendSuccess(null, 'Сервер успешно удален');
            break;

        case 'status':
            // Проверка статуса сервера
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID сервера', 400);
            }

            // Проверка существования сервера
            $server = $serverManager->getServer($_GET['id']);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            // Проверка статуса
            $status = $serverManager->checkServerStatus($_GET['id']);
            sendSuccess(['status' => $status]);
            break;

        case 'restart':
            // Перезапуск XRay на сервере
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID сервера', 400);
            }

            // Проверка существования сервера
            $server = $serverManager->getServer($_GET['id']);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            // Перезапуск XRay
            $serverManager->restartXray($_GET['id']);
            sendSuccess(null, 'XRay успешно перезапущен');
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API серверов: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
