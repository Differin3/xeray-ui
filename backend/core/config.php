<?php
// Xeray UI - Конфигурация

// Режим статических данных (должен быть false для работы с БД)
define('STATIC_MODE', false);

// Настройка базы данных
define('DB_TYPE', 'sqlite');
define('DB_PATH', __DIR__ . '/../../database/xeray.db');

// Настройка API
define('API_SECRET_KEY', 'your-secret-key-here');
define('API_TOKEN_EXPIRATION', 3600); // 1 час

// Настройка XRay
define('XRAY_CONFIG_PATH', '/etc/xray/config.json');
define('XRAY_BINARY_PATH', '/usr/local/bin/xray');

// Настройка логирования
define('LOG_PATH', __DIR__ . '/../../logs');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Настройки безопасности
define('ALLOWED_HOSTS', ['localhost', '127.0.0.1']);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 минут

// Настройки по умолчанию
define('DEFAULT_TRAFFIC_LIMIT', 100); // GB
define('DEFAULT_EXPIRY_DAYS', 30);

// Время жизни сессии (в секундах)
define('SESSION_LIFETIME', 86400); // 24 часа

// Включить или отключить режим отладки
define('DEBUG_MODE', true);

// Настройки почты (для уведомлений)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@xerayui.com');
define('SMTP_FROM_NAME', 'Xeray UI');

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

// Функция для логирования
function logMessage($level, $message) {
    $logDir = LOG_PATH;
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    $logMessage = date('Y-m-d H:i:s') . " [$level] $message" . PHP_EOL;

    // Пытаемся создать каталог
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Пишем в файл, при ошибке — в error_log
    if (@file_put_contents($logFile, $logMessage, FILE_APPEND) === false) {
        error_log($logMessage);
    }
}

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
        header('Location: ../public/index.html');
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
