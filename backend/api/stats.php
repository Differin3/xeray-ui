<?php
// Xeray UI - API для получения статистики

require_once '../core/config.php';
require_once '../core/database.php';

header('Content-Type: application/json');

// Проверка авторизации
session_start();
if (!isset($_SESSION['user_id'])) {
    sendError('Доступ запрещен', 403);
}

$db = Database::getInstance();

// Определение действия
$action = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['action'] ?? '';

try {
    switch ($endpoint) {
        case 'overview':
            // Получение общей статистики
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            // Количество серверов
            $servers = $db->fetchColumn("SELECT COUNT(*) FROM servers");

            // Количество активных серверов
            $activeServers = $db->fetchColumn("SELECT COUNT(*) FROM servers WHERE status = 'online'");

            // Количество пользователей
            $users = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1");

            // Количество активных пользователей
            $activeUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1 AND id IN (SELECT DISTINCT user_id FROM traffic WHERE bytes_up > 0 OR bytes_down > 0)");

            // Общий трафик
            $totalTraffic = $db->fetchColumn("
                SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                FROM traffic
                WHERE start_time >= datetime('now', 'start of month')
            ");

            // Трафик за сегодня
            $todayTraffic = $db->fetchColumn("
                SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                FROM traffic
                WHERE date(start_time) = date('now')
            ");

            // Трафик за вчерашний день
            $yesterdayTraffic = $db->fetchColumn("
                SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                FROM traffic
                WHERE date(start_time) = date('now', '-1 day')
            ");

            // Трафик за неделю
            $weekTraffic = $db->fetchColumn("
                SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                FROM traffic
                WHERE start_time >= datetime('now', 'start of day', '-6 days')
            ");

            // Трафик за месяц
            $monthTraffic = $db->fetchColumn("
                SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                FROM traffic
                WHERE start_time >= datetime('now', 'start of month')
            ");

            // Статистика по типам трафика
            $trafficTypes = $db->fetchAll("
                SELECT t.inbound_id, i.type, i.name, 
                       COALESCE(SUM(t.bytes_up) + SUM(t.bytes_down), 0) as total_traffic
                FROM traffic t
                JOIN inbounds i ON t.inbound_id = i.id
                WHERE t.start_time >= datetime('now', 'start of month')
                GROUP BY t.inbound_id, i.type, i.name
                ORDER BY total_traffic DESC
            ");

            // Статистика по серверам
            $serverStats = $db->fetchAll("
                SELECT s.id, s.name, s.location, s.status,
                       COALESCE(SUM(t.bytes_up) + SUM(t.bytes_down), 0) as total_traffic
                FROM servers s
                LEFT JOIN traffic t ON s.id = t.server_id
                WHERE t.start_time >= datetime('now', 'start of month')
                GROUP BY s.id, s.name, s.location, s.status
                ORDER BY total_traffic DESC
            ");

            // Статистика по пользователям
            $userStats = $db->fetchAll("
                SELECT u.id, u.username, u.full_name, u.email,
                       COALESCE(SUM(t.bytes_up) + SUM(t.bytes_down), 0) as total_traffic
                FROM users u
                LEFT JOIN traffic t ON u.id = t.user_id
                WHERE t.start_time >= datetime('now', 'start of month')
                GROUP BY u.id, u.username, u.full_name, u.email
                ORDER BY total_traffic DESC
                LIMIT 10
            ");

            // Данные для графиков
            $chartData = [
                'daily' => [],
                'monthly' => []
            ];

            // Получение данных за последние 30 дней для графика
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $traffic = $db->fetchColumn("
                    SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                    FROM traffic
                    WHERE date(start_time) = ?
                ", [$date]);

                $chartData['daily'][] = [
                    'date' => $date,
                    'traffic' => $traffic
                ];
            }

            // Получение данных за последние 12 месяцев для графика
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("$i months"));
                $traffic = $db->fetchColumn("
                    SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0)
                    FROM traffic
                    WHERE strftime('%Y-%m', start_time) = ?
                ", [$month]);

                $chartData['monthly'][] = [
                    'month' => $month,
                    'traffic' => $traffic
                ];
            }

            $stats = [
                'servers' => [
                    'total' => $servers,
                    'active' => $activeServers
                ],
                'users' => [
                    'total' => $users,
                    'active' => $activeUsers
                ],
                'traffic' => [
                    'total' => $totalTraffic,
                    'today' => $todayTraffic,
                    'yesterday' => $yesterdayTraffic,
                    'week' => $weekTraffic,
                    'month' => $monthTraffic
                ],
                'traffic_types' => $trafficTypes,
                'server_stats' => $serverStats,
                'user_stats' => $userStats,
                'chart_data' => $chartData
            ];

            sendSuccess($stats);
            break;

        case 'traffic':
            // Получение статистики трафика
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            // Фильтры
            $serverId = $_GET['server_id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            $inboundId = $_GET['inbound_id'] ?? null;
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            // Базовый запрос
            $query = "
                SELECT 
                    date(start_time) as date,
                    SUM(bytes_up) as up,
                    SUM(bytes_down) as down,
                    SUM(bytes_up) + SUM(bytes_down) as total
                FROM traffic
                WHERE date(start_time) BETWEEN ? AND ?
            ";

            $params = [$startDate, $endDate];

            // Добавление фильтров
            if ($serverId) {
                $query .= " AND server_id = ?";
                $params[] = $serverId;
            }

            if ($userId) {
                $query .= " AND user_id = ?";
                $params[] = $userId;
            }

            if ($inboundId) {
                $query .= " AND inbound_id = ?";
                $params[] = $inboundId;
            }

            $query .= " GROUP BY date(start_time) ORDER BY date";

            $trafficData = $db->fetchAll($query, $params);

            // Общий трафик
            $totalQuery = "
                SELECT 
                    COALESCE(SUM(bytes_up), 0) as up,
                    COALESCE(SUM(bytes_down), 0) as down,
                    COALESCE(SUM(bytes_up) + SUM(bytes_down), 0) as total
                FROM traffic
                WHERE date(start_time) BETWEEN ? AND ?
            ";

            $totalParams = [$startDate, $endDate];

            if ($serverId) {
                $totalQuery .= " AND server_id = ?";
                $totalParams[] = $serverId;
            }

            if ($userId) {
                $totalQuery .= " AND user_id = ?";
                $totalParams[] = $userId;
            }

            if ($inboundId) {
                $totalQuery .= " AND inbound_id = ?";
                $totalParams[] = $inboundId;
            }

            $totalTraffic = $db->fetchOne($totalQuery, $totalParams);

            $stats = [
                'total' => $totalTraffic,
                'daily' => $trafficData
            ];

            sendSuccess($stats);
            break;

        case 'servers':
            // Получение статистики по серверам
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            $stats = $db->fetchAll("
                SELECT 
                    s.id,
                    s.name,
                    s.location,
                    s.status,
                    COUNT(DISTINCT t.user_id) as users,
                    COALESCE(SUM(t.bytes_up) + SUM(t.bytes_down), 0) as total_traffic,
                    COALESCE(SUM(t.bytes_up), 0) as up_traffic,
                    COALESCE(SUM(t.bytes_down), 0) as down_traffic
                FROM servers s
                LEFT JOIN traffic t ON s.id = t.server_id
                WHERE t.start_time >= datetime('now', 'start of month')
                GROUP BY s.id, s.name, s.location, s.status
                ORDER BY total_traffic DESC
            ");

            sendSuccess($stats);
            break;

        case 'users':
            // Получение статистики по пользователям
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            $stats = $db->fetchAll("
                SELECT 
                    u.id,
                    u.username,
                    u.full_name,
                    u.email,
                    u.is_active,
                    COUNT(DISTINCT t.server_id) as servers,
                    COALESCE(SUM(t.bytes_up) + SUM(t.bytes_down), 0) as total_traffic,
                    COALESCE(SUM(t.bytes_up), 0) as up_traffic,
                    COALESCE(SUM(t.bytes_down), 0) as down_traffic
                FROM users u
                LEFT JOIN traffic t ON u.id = t.user_id
                WHERE t.start_time >= datetime('now', 'start of month')
                GROUP BY u.id, u.username, u.full_name, u.email, u.is_active
                ORDER BY total_traffic DESC
                LIMIT 50
            ");

            sendSuccess($stats);
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API статистики: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
