<?php
// Xeray UI - API для получения логов

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
        case 'system':
            // Получение системных логов
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            // Параметры пагинации
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;

            // Фильтры
            $level = $_GET['level'] ?? '';
            $userId = $_GET['user_id'] ?? null;
            $serverId = $_GET['server_id'] ?? null;
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            // Базовый запрос
            $query = "SELECT * FROM logs";
            $params = [];

            // Условия фильтрации
            $conditions = [];

            if ($level) {
                $conditions[] = "level = ?";
                $params[] = $level;
            }

            if ($userId) {
                $conditions[] = "user_id = ?";
                $params[] = $userId;
            }

            if ($serverId) {
                $conditions[] = "server_id = ?";
                $params[] = $serverId;
            }

            if ($startDate) {
                $conditions[] = "created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }

            if ($endDate) {
                $conditions[] = "created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }

            // Добавление условий к запросу
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            // Сортировка
            $query .= " ORDER BY created_at DESC";

            // Подсчет общего количества записей
            $countQuery = "SELECT COUNT(*) FROM logs";
            if (!empty($conditions)) {
                $countQuery .= " WHERE " . implode(" AND ", $conditions);
            }

            $totalLogs = $db->fetchColumn($countQuery, $params);
            $totalPages = ceil($totalLogs / $perPage);

            // Получение записей
            $offset = ($page - 1) * $perPage;
            $logs = $db->fetchAll($query . " LIMIT ? OFFSET ?", array_merge($params, [$perPage, $offset]));

            // Форматирование данных
            foreach ($logs as &$log) {
                $log['formatted_date'] = formatDate($log['created_at']);
            }

            $result = [
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalLogs,
                    'total_pages' => $totalPages
                ]
            ];

            sendSuccess($result);
            break;

        case 'server':
            // Получение логов конкретного сервера
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID сервера', 400);
            }

            // Проверка существования сервера
            $server = $db->fetchOne("SELECT id FROM servers WHERE id = ?", [$_GET['id']]);
            if (!$server) {
                sendError('Сервер не найден', 404);
            }

            // Параметры пагинации
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;

            // Фильтры
            $level = $_GET['level'] ?? '';
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            // Базовый запрос
            $query = "SELECT * FROM logs WHERE server_id = ?";
            $params = [$_GET['id']];

            // Условия фильтрации
            $conditions = [];

            if ($level) {
                $conditions[] = "level = ?";
                $params[] = $level;
            }

            if ($startDate) {
                $conditions[] = "created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }

            if ($endDate) {
                $conditions[] = "created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }

            // Добавление условий к запросу
            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            // Сортировка
            $query .= " ORDER BY created_at DESC";

            // Подсчет общего количества записей
            $countQuery = "SELECT COUNT(*) FROM logs WHERE server_id = ?";
            if (!empty($conditions)) {
                $countQuery .= " AND " . implode(" AND ", $conditions);
            }

            $totalLogs = $db->fetchColumn($countQuery, $params);
            $totalPages = ceil($totalLogs / $perPage);

            // Получение записей
            $offset = ($page - 1) * $perPage;
            $logs = $db->fetchAll($query . " LIMIT ? OFFSET ?", array_merge($params, [$perPage, $offset]));

            // Форматирование данных
            foreach ($logs as &$log) {
                $log['formatted_date'] = formatDate($log['created_at']);
            }

            $result = [
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalLogs,
                    'total_pages' => $totalPages
                ]
            ];

            sendSuccess($result);
            break;

        case 'user':
            // Получение логов конкретного пользователя
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID пользователя', 400);
            }

            // Проверка существования пользователя
            $user = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$_GET['id']]);
            if (!$user) {
                sendError('Пользователь не найден', 404);
            }

            // Параметры пагинации
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;

            // Фильтры
            $level = $_GET['level'] ?? '';
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';

            // Базовый запрос
            $query = "SELECT * FROM logs WHERE user_id = ?";
            $params = [$_GET['id']];

            // Условия фильтрации
            $conditions = [];

            if ($level) {
                $conditions[] = "level = ?";
                $params[] = $level;
            }

            if ($startDate) {
                $conditions[] = "created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }

            if ($endDate) {
                $conditions[] = "created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }

            // Добавление условий к запросу
            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }

            // Сортировка
            $query .= " ORDER BY created_at DESC";

            // Подсчет общего количества записей
            $countQuery = "SELECT COUNT(*) FROM logs WHERE user_id = ?";
            if (!empty($conditions)) {
                $countQuery .= " AND " . implode(" AND ", $conditions);
            }

            $totalLogs = $db->fetchColumn($countQuery, $params);
            $totalPages = ceil($totalLogs / $perPage);

            // Получение записей
            $offset = ($page - 1) * $perPage;
            $logs = $db->fetchAll($query . " LIMIT ? OFFSET ?", array_merge($params, [$perPage, $offset]));

            // Форматирование данных
            foreach ($logs as &$log) {
                $log['formatted_date'] = formatDate($log['created_at']);
            }

            $result = [
                'logs' => $logs,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalLogs,
                    'total_pages' => $totalPages
                ]
            ];

            sendSuccess($result);
            break;

        case 'clear':
            // Очистка логов
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            // Проверка прав администратора
            if (!isAdmin()) {
                sendError('Доступ запрещен', 403);
            }

            // Удаление логов
            $db->exec("DELETE FROM logs");

            // Логирование действия
            logMessage('INFO', 'Логи системы очищены', $_SESSION['user_id']);

            sendSuccess(null, 'Логи успешно очищены');
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API логов: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
