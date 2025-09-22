<?php
// Xeray UI - API для управления пользователями

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
        case 'list':
            // Получение списка всех пользователей
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            $users = $db->fetchAll("
                SELECT u.*, q.data_limit_gb, q.reset_strategy
                FROM users u
                LEFT JOIN user_quotas q ON u.id = q.user_id
                ORDER BY u.created_at DESC
            ");

            // Добавляем информацию о трафике
            foreach ($users as &$user) {
                $traffic = $db->fetchOne("
                    SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0) as total_traffic
                    FROM traffic
                    WHERE user_id = ?
                ", [$user['id']]);

                $user['total_traffic'] = $traffic['total_traffic'] ?? 0;
                $user['traffic_used'] = formatTraffic($user['total_traffic']);
                $user['traffic_limit'] = formatTraffic($user['data_limit_gb'] * 1024 * 1024 * 1024);
            }

            sendSuccess($users);
            break;

        case 'get':
            // Получение информации о конкретном пользователе
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['id'])) {
                sendError('Не указан ID пользователя', 400);
            }

            $user = $db->fetchOne("
                SELECT u.*, q.data_limit_gb, q.reset_strategy
                FROM users u
                LEFT JOIN user_quotas q ON u.id = q.user_id
                WHERE u.id = ?
            ", [$_GET['id']]);

            if (!$user) {
                sendError('Пользователь не найден', 404);
            }

            // Добавляем информацию о трафике
            $traffic = $db->fetchOne("
                SELECT COALESCE(SUM(bytes_up) + SUM(bytes_down), 0) as total_traffic
                FROM traffic
                WHERE user_id = ?
            ", [$user['id']]);

            $user['total_traffic'] = $traffic['total_traffic'] ?? 0;
            $user['traffic_used'] = formatTraffic($user['total_traffic']);
            $user['traffic_limit'] = formatTraffic($user['data_limit_gb'] * 1024 * 1024 * 1024);

            sendSuccess($user);
            break;

        case 'add':
            // Добавление нового пользователя
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            // Получение данных из POST запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Валидация данных
            $required = ['username', 'email', 'password'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty($input[$field])) {
                    sendError("Поле $field обязательно для заполнения", 400);
                }
            }

            // Валидация email
            if (!isValidEmail($input['email'])) {
                sendError('Неверный формат email', 400);
            }

            // Проверка уникальности username и email
            $existing = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [
                $input['username'],
                $input['email']
            ]);

            if ($existing) {
                sendError('Пользователь с таким именем или email уже существует', 400);
            }

            // Хеширование пароля
            $input['password_hash'] = hashPassword($input['password']);

            // Удаление plaintext пароля
            unset($input['password']);

            // Добавление пользователя
            $db->beginTransaction();

            try {
                $userId = $db->insert('users', [
                    'username' => $input['username'],
                    'email' => $input['email'],
                    'password_hash' => $input['password_hash'],
                    'full_name' => $input['full_name'] ?? '',
                    'is_admin' => $input['is_admin'] ?? 0
                ]);

                // Добавление квоты
                $db->insert('user_quotas', [
                    'user_id' => $userId,
                    'data_limit_gb' => $input['data_limit_gb'] ?? DEFAULT_TRAFFIC_LIMIT,
                    'reset_strategy' => $input['reset_strategy'] ?? 'no-reset'
                ]);

                $db->commit();
                sendSuccess(['id' => $userId], 'Пользователь успешно добавлен');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'update':
            // Обновление информации о пользователе
            if ($action !== 'PUT') {
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

            // Получение данных из PUT запроса
            $input = json_decode(file_get_contents('php://input'), true);

            // Валидация email
            if (isset($input['email']) && !isValidEmail($input['email'])) {
                sendError('Неверный формат email', 400);
            }

            // Проверка уникальности username и email
            if (isset($input['username']) || isset($input['email'])) {
                $params = [];
                $conditions = [];

                if (isset($input['username'])) {
                    $conditions[] = "username = ?";
                    $params[] = $input['username'];
                }

                if (isset($input['email'])) {
                    $conditions[] = "email = ?";
                    $params[] = $input['email'];
                }

                $params[] = $_GET['id'];

                $existing = $db->fetchOne(
                    "SELECT id FROM users WHERE (" . implode(" OR ", $conditions) . ") AND id <> ?",
                    $params
                );

                if ($existing) {
                    sendError('Пользователь с таким именем или email уже существует', 400);
                }
            }

            // Хеширование пароля если он указан
            if (isset($input['password']) && !empty($input['password'])) {
                $input['password_hash'] = hashPassword($input['password']);
                unset($input['password']);
            }

            // Обновление пользователя
            $db->beginTransaction();

            try {
                // Обновление данных пользователя
                $updateData = [
                    'username' => $input['username'] ?? $user['username'],
                    'email' => $input['email'] ?? $user['email'],
                    'full_name' => $input['full_name'] ?? $user['full_name'],
                    'is_admin' => $input['is_admin'] ?? $user['is_admin'],
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if (isset($input['password_hash'])) {
                    $updateData['password_hash'] = $input['password_hash'];
                }

                $db->update('users', $updateData, 'id = ?', [$_GET['id']]);

                // Обновление квоты
                if (isset($input['data_limit_gb']) || isset($input['reset_strategy'])) {
                    $quota = $db->fetchOne("SELECT id FROM user_quotas WHERE user_id = ?", [$_GET['id']]);

                    if ($quota) {
                        $db->update('user_quotas', [
                            'data_limit_gb' => $input['data_limit_gb'] ?? $quota['data_limit_gb'],
                            'reset_strategy' => $input['reset_strategy'] ?? $quota['reset_strategy'],
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'user_id = ?', [$_GET['id']]);
                    } else {
                        $db->insert('user_quotas', [
                            'user_id' => $_GET['id'],
                            'data_limit_gb' => $input['data_limit_gb'] ?? DEFAULT_TRAFFIC_LIMIT,
                            'reset_strategy' => $input['reset_strategy'] ?? 'no-reset'
                        ]);
                    }
                }

                $db->commit();
                sendSuccess(null, 'Пользователь успешно обновлен');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'delete':
            // Удаление пользователя
            if ($action !== 'DELETE') {
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

            // Проверка, что пользователь не удаляет сам себя
            if ($_GET['id'] == $_SESSION['user_id']) {
                sendError('Вы не можете удалить свой аккаунт', 400);
            }

            // Удаление пользователя
            $db->delete('users', 'id = ?', [$_GET['id']]);
            sendSuccess(null, 'Пользователь успешно удален');
            break;

        case 'reset_traffic':
            // Сброс трафика пользователя
            if ($action !== 'POST') {
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

            // Сброс трафика
            $db->delete('traffic', 'user_id = ?', [$_GET['id']]);
            sendSuccess(null, 'Трафик пользователя успешно сброшен');
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API пользователей: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
