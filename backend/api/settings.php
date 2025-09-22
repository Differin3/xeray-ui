<?php
// Xeray UI - API для управления настройками

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
        case 'get':
            // Получение всех настроек
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            $settings = $db->fetchAll("SELECT * FROM settings");

            // Преобразуем в массив ключ-значение
            $settingsArray = [];
            foreach ($settings as $setting) {
                $settingsArray[$setting['key']] = [
                    'value' => $setting['value'],
                    'description' => $setting['description']
                ];
            }

            // Добавляем системные настройки
            $settingsArray['system'] = [
                'version' => '1.0.0',
                'debug_mode' => DEBUG_MODE,
                'session_lifetime' => SESSION_LIFETIME,
                'max_login_attempts' => MAX_LOGIN_ATTEMPTS,
                'login_lockout_time' => LOGIN_LOCKOUT_TIME,
                'allowed_hosts' => ALLOWED_HOSTS,
                'default_traffic_limit' => DEFAULT_TRAFFIC_LIMIT,
                'default_expiry_days' => DEFAULT_EXPIRY_DAYS
            ];

            sendSuccess($settingsArray);
            break;

        case 'get_value':
            // Получение конкретной настройки
            if ($action !== 'GET') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['key'])) {
                sendError('Не указан ключ настройки', 400);
            }

            $setting = $db->fetchOne("SELECT * FROM settings WHERE key = ?", [$_GET['key']]);

            if (!$setting) {
                sendError('Настройка не найдена', 404);
            }

            sendSuccess($setting);
            break;

        case 'update':
            // Обновление настроек
            if ($action !== 'PUT') {
                sendError('Метод не поддерживается', 405);
            }

            // Получение данных из PUT запроса
            $input = json_decode(file_get_contents('php://input'), true);

            if (!is_array($input) || empty($input)) {
                sendError('Неверный формат данных', 400);
            }

            // Обновляем каждую настройку
            $db->beginTransaction();

            try {
                foreach ($input as $key => $value) {
                    // Проверяем, существует ли настройка
                    $existing = $db->fetchOne("SELECT id FROM settings WHERE key = ?", [$key]);

                    if ($existing) {
                        // Обновляем существующую настройку
                        $db->update('settings', [
                            'value' => is_array($value) ? json_encode($value) : $value,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'key = ?', [$key]);
                    } else {
                        // Добавляем новую настройку
                        $db->insert('settings', [
                            'key' => $key,
                            'value' => is_array($value) ? json_encode($value) : $value,
                            'description' => 'Автоматически добавленная настройка'
                        ]);
                    }
                }

                $db->commit();
                sendSuccess(null, 'Настройки успешно обновлены');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'update_value':
            // Обновление конкретной настройки
            if ($action !== 'PUT') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['key'])) {
                sendError('Не указан ключ настройки', 400);
            }

            // Получение значения из PUT запроса
            $value = json_decode(file_get_contents('php://input'));

            // Проверяем, существует ли настройка
            $existing = $db->fetchOne("SELECT id FROM settings WHERE key = ?", [$_GET['key']]);

            if ($existing) {
                // Обновляем существующую настройку
                $db->update('settings', [
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'key = ?', [$_GET['key']]);
            } else {
                // Добавляем новую настройку
                $db->insert('settings', [
                    'key' => $_GET['key'],
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'description' => 'Автоматически добавленная настройка'
                ]);
            }

            sendSuccess(null, 'Настройка успешно обновлена');
            break;

        case 'delete':
            // Удаление настройки
            if ($action !== 'DELETE') {
                sendError('Метод не поддерживается', 405);
            }

            if (!isset($_GET['key'])) {
                sendError('Не указан ключ настройки', 400);
            }

            // Проверяем, существует ли настройка
            $existing = $db->fetchOne("SELECT id FROM settings WHERE key = ?", [$_GET['key']]);

            if (!$existing) {
                sendError('Настройка не найдена', 404);
            }

            // Удаляем настройку
            $db->delete('settings', 'key = ?', [$_GET['key']]);

            sendSuccess(null, 'Настройка успешно удалена');
            break;

        case 'reset':
            // Сброс настроек к значениям по умолчанию
            if ($action !== 'POST') {
                sendError('Метод не поддерживается', 405);
            }

            // Удаляем все настройки
            $db->exec("DELETE FROM settings");

            // Добавляем настройки по умолчанию
            $defaultSettings = [
                [
                    'key' => 'site_name',
                    'value' => 'Xeray UI',
                    'description' => 'Название сайта'
                ],
                [
                    'key' => 'site_description',
                    'value' => 'Панель управления для XRay Core',
                    'description' => 'Описание сайта'
                ],
                [
                    'key' => 'theme',
                    'value' => 'default',
                    'description' => 'Тема оформления'
                ],
                [
                    'key' => 'language',
                    'value' => 'ru',
                    'description' => 'Язык интерфейса'
                ],
                [
                    'key' => 'timezone',
                    'value' => 'Europe/Moscow',
                    'description' => 'Часовой пояс'
                ],
                [
                    'key' => 'date_format',
                    'value' => 'd.m.Y H:i',
                    'description' => 'Формат даты'
                ],
                [
                    'key' 'traffic_format',
                    'value' => 'auto',
                    'description' => 'Формат отображения трафика'
                ],
                [
                    'key' => 'log_level',
                    'value' => 'INFO',
                    'description' => 'Уровень логирования'
                ],
                [
                    'key' => 'log_retention_days',
                    'value' => 30,
                    'description' => 'Период хранения логов (дней)'
                ],
                [
                    'key' => 'backup_enabled',
                    'value' => true,
                    'description' => 'Включить автоматическое резервное копирование'
                ],
                [
                    'key' => 'backup_frequency',
                    'value' => 'daily',
                    'description' => 'Частота резервного копирования'
                ],
                [
                    'key' => 'backup_retention_days',
                    'value' => 7,
                    'description' => 'Период хранения резервных копий (дней)'
                ]
            ];

            foreach ($defaultSettings as $setting) {
                $db->insert('settings', [
                    'key' => $setting['key'],
                    'value' => is_array($setting['value']) ? json_encode($setting['value']) : $setting['value'],
                    'description' => $setting['description']
                ]);
            }

            sendSuccess(null, 'Настройки успешно сброшены');
            break;

        default:
            sendError('Неизвестный endpoint', 404);
            break;
    }
} catch (Exception $e) {
    logMessage('ERROR', 'Ошибка API настроек: ' . $e->getMessage());
    sendError('Внутренняя ошибка сервера', 500);
}
