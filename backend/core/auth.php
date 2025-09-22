<?php
// Xeray UI - Auth helpers
require_once __DIR__ . '/database.php';

function authLogin(string $username, string $password): bool {
    $db = Database::getInstance();
    $user = $db->fetchOne('SELECT * FROM users WHERE username = ? AND is_active = 1', [$username]);
    if (!$user) {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = (bool)$user['is_admin'];
    $_SESSION['last_activity'] = time();
    return true;
}

function authLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function authRequire(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?section=login');
        exit;
    }
}

function authUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'is_admin' => (bool)($_SESSION['is_admin'] ?? false),
    ];
}
