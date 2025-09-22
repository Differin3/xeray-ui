<?php
// Xeray UI - Основной конфигурационный файл

// =============================================================================
// ОБЩИЕ НАСТРОЙКИ
// =============================================================================

// Базовый URL для API
define('API_URL', 'http://localhost/api');

// Секретный ключ для API аутентификации
define('API_SECRET', 'your-secret-key-here');

// Включить или отключить режим отладки
define('DEBUG_MODE', true);

// Время жизни сессии (в секундах)
define('SESSION_LIFETIME', 86400); // 24 часа

// =============================================================================
// НАСТРОЙКИ БАЗЫ ДАННЫХ
// =============================================================================

// Тип базы данных
define('DB_TYPE', 'sqlite');

// Путь к файлу базы данных
define('DB_PATH', __DIR__ . '/database/xeray.db');

// Функция для подключения к базе данных
function getDbConnection() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }
}

// =============================================================================
// НАСТРОЙКИ DEMON (PYTHON)
// =============================================================================

// Хост для запуска демона
define('DAEMON_HOST', '0.0.0.0');

// Порт для запуска демона
define('DAEMON_PORT', 8080);

// Секретный ключ для аутентификации с демоном
define('DAEMON_SECRET', 'daemon-secret-key');

// Таймаут для запросов к демону (в секундах)
define('DAEMON_TIMEOUT', 10);

// Интервал проверки статуса серверов (в секундах)
define('SERVER_STATUS_CHECK_INTERVAL', 30);

// =============================================================================
// НАСТРОЙКИ XRAY CORE
// =============================================================================

// Путь к конфигурационному файлу XRay
define('XRAY_CONFIG_PATH', '/etc/xray/config.json');

// Путь к бинарному файлу XRay
define('XRAY_BINARY_PATH', '/usr/local/bin/xray');

// Команда для перезапуска XRay
define('XRAY_RESTART_COMMAND', 'systemctl restart xray');

// Команда для проверки статуса XRay
define('XRAY_STATUS_COMMAND', 'systemctl is-active xray');

// =============================================================================
// НАСТРОЙКИ ЛОГИРОВАНИЯ
// =============================================================================

// Путь к директории с логами
define('LOG_PATH', __DIR__ . '/logs');

// Уровень логирования: DEBUG, INFO, WARNING, ERROR
define('LOG_LEVEL', 'INFO');

// Максимальный размер лог-файла (в мегабайтах)
define('LOG_MAX_SIZE', 10);

// Количество сохраняемых лог-файлов
define('LOG_FILES_COUNT', 5);

// Функция для логирования
function logMessage($level, $message) {
    $logFile = LOG_PATH . '/' . date('Y-m-d') . '.log';
    $logMessage = date('Y-m-d H:i:s') . " [$level] $message" . PHP_EOL;

    if (!file_exists(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // Проверка размера лог-файла и ротация при необходимости
    if (file_exists($logFile) && filesize($logFile) > LOG_MAX_SIZE * 1024 * 1024) {
        $this->rotateLogs();
    }
}

// Функция для ротации логов
function rotateLogs() {
    for ($i = LOG_FILES_COUNT - 1; $i >= 1; $i--) {
        $oldFile = LOG_PATH . '/' . date('Y-m-d') . ".log.$i";
        $newFile = LOG_PATH . '/' . date('Y-m-d') . ".log." . ($i + 1);

        if (file_exists($oldFile)) {
            rename($oldFile, $newFile);
        }
    }

    if (file_exists(LOG_PATH . '/' . date('Y-m-d') . '.log')) {
        rename(LOG_PATH . '/' . date('Y-m-d') . '.log', LOG_PATH . '/' . date('Y-m-d') . '.log.1');
    }
}

// =============================================================================
// НАСТРОЙКИ БЕЗОПАСНОСТИ
// =============================================================================

// Разрешенные хосты
define('ALLOWED_HOSTS', ['localhost', '127.0.0.1']);

// Максимальное количество попыток входа
define('MAX_LOGIN_ATTEMPTS', 5);

// Время блокировки после превышения лимита попыток (в секундах)
define('LOGIN_LOCKOUT_TIME', 300); // 5 минут

// Включить CSRF защиту
define('ENABLE_CSRF_PROTECTION', true);

// Включить двухфакторную аутентификацию
define('ENABLE_2FA', false);

// =============================================================================
// ПОЛЬЗОВАТЕЛЬСКИЕ НАСТРОЙКИ
// =============================================================================

// Максимальное количество пользователей на сервер
define('MAX_USERS_PER_SERVER', 100);

// Квота пользователя по умолчанию (в ГБ)
define('DEFAULT_USER_QUOTA', 100);

// Интервал сброса трафика: no-reset, daily, weekly, monthly
define('TRAFFIC_RESET_INTERVAL', 'monthly');

// Разрешить регистрацию новых пользователей
define('ENABLE_REGISTRATION', false);

// =============================================================================
// ФУНКЦИИ-ПОМОЩНИКИ
// =============================================================================

// Функция для безопасного вывода данных
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Функция для генерации случайной строки
function generateRandomString($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}

// Функция для форматирования трафика в читаемый вид
function formatTraffic($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . ' MB';
    } else {
        return round($bytes / 1073741824, 2) . ' GB';
    }
}

// Функция для проверки авторизации
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: public/index.html');
        exit;
    }
}

// Функция для проверки прав администратора
function isAdmin() {
    return isset($_SESSION['user_id']) && $_SESSION['is_admin'];
}

// Функция для хеширования пароля
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Функция для проверки пароля
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Функция для генерации токена API
function generateApiToken() {
    return bin2hex(random_bytes(32));
}

// Функция для валидации email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Функция для валидации IP адреса
function isValidIp($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

// Функция для получения IP клиента
function getClientIp() {
    $ipAddress = '';

    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipAddress = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    }

    return $ipAddress;
}

// Функция для форматирования даты
function formatDate($date) {
    $timestamp = strtotime($date);
    return date('d.m.Y H:i', $timestamp);
}

// Функция для проверки, является ли строка JSON
function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

// Функция для отправки JSON ответа
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Функция для отправки ошибки
function sendError($message, $status = 400) {
    sendJsonResponse(['error' => $message], $status);
}

// Функция для отправки успешного ответа
function sendSuccess($data = [], $message = 'Success') {
    sendJsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

// Функция для проверки CSRF токена
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Функция для генерации CSRF токена
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Функция для генерация OTP кода для двухфакторной аутентификации
function generateOTPCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Функция для проверки OTP кода
function verifyOTPCode($storedCode, $inputCode) {
    return $storedCode === $inputCode;
}

