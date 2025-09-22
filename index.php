<?php
// Xeray UI - Main PHP Application
session_start();

// Configuration
$config = [
    'app_name' => 'Xeray Control Panel',
    'version' => '1.0.0',
    'timezone' => 'Europe/Moscow',
    'auto_refresh' => 30,
    'debug' => true
];

// Set timezone
date_default_timezone_set($config['timezone']);

// Error reporting
if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Include database connection
require_once 'backend/core/database.php';
// Include auth helpers
require_once 'backend/core/auth.php';

// Include API functions
require_once 'backend/core/api_functions.php';

// Get current section from URL
$section = $_GET['section'] ?? 'overview';
$action = $_GET['action'] ?? 'index';

// Valid sections
$valid_sections = ['overview', 'servers', 'users', 'stats', 'inbounds', 'outbounds', 'routing', 'settings', 'login'];
if (!in_array($section, $valid_sections)) {
    $section = 'overview';
}

// Auth routes
if ($section === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if (function_exists('verifyCsrfToken') && !verifyCsrfToken($csrf)) {
        $_SESSION['error'] = 'Неверный CSRF токен';
        header('Location: ?section=login');
        exit;
    }
    if (authLogin($username, $password)) {
        header('Location: ?section=overview');
        exit;
    }
    $_SESSION['error'] = 'Неверный логин или пароль';
    header('Location: ?section=login');
    exit;
}
if ($action === 'logout') {
    authLogout();
    header('Location: ?section=login');
    exit;
}

// If not logged in, show login page for any protected section
$protected_sections = ['overview', 'servers', 'users', 'stats', 'inbounds', 'outbounds', 'routing', 'settings'];
if (!isset($_SESSION['user_id']) && in_array($section, $protected_sections)) {
    $section = 'login';
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    handleAjaxRequest($section, $action);
    exit;
}

// Handle form submissions (non-auth)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section !== 'login') {
    handleFormSubmission($section, $action);
}

// If rendering login page, do minimal layout
if ($section === 'login') {
    $success_message = $_SESSION['success'] ?? null;
    $error_message = $_SESSION['error'] ?? null;
    if ($success_message) { unset($_SESSION['success']); }
    if ($error_message) { /* keep for component */ }
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['app_name']; ?> — Вход</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="frontend/css/styles.css">
</head>
<body>
<?php include 'frontend/components/login.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    exit;
}

// Load data from API functions
$servers = getServers();
$users = getUsers();
$inbounds = getInbounds();
$outbounds = getOutbounds();
$routing_rules = getRoutingRules();
$stats = getStats();

// Generate logs
$logs = [
    ['type' => 'success', 'message' => 'Сервер "Server-1 (NL)" успешно обновлен', 'time' => date('H:i:s')],
    ['type' => 'info', 'message' => 'Новый пользователь добавлен во inbound "VLESS + TLS"', 'time' => date('H:i:s')],
    ['type' => 'warning', 'message' => 'Высокая нагрузка на сервер "Server-2 (DE)"', 'time' => date('H:i:s')],
    ['type' => 'error', 'message' => 'Потеряно соединение с сервером "Server-3 (US)"', 'time' => date('H:i:s')]
];

// Check for success/error messages
$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;

// Clear messages after displaying
if ($success_message) {
    unset($_SESSION['success']);
}
if ($error_message) {
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['app_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.2/standalone/umd/vis-network.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="frontend/css/styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $section === 'overview' ? 'active' : ''; ?>" href="?section=overview"><i class="fas fa-tachometer-alt"></i> Панель управления</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small d-none d-md-inline">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>
                    </span>
                    <a class="btn btn-outline-secondary" href="?action=logout"><i class="fas fa-sign-out-alt"></i></a>
                    <button class="btn btn-outline-primary d-none d-md-inline" id="refreshBtn"><i class="fas fa-sync-alt"></i></button>
                    <button class="btn btn-primary d-none d-md-inline" data-bs-toggle="modal" data-bs-target="#addServerModal"><i class="fas fa-plus"></i> Добавить сервер</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar d-md-block">
        <div class="position-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'overview' ? 'active' : ''; ?>" href="?section=overview">
                        <i class="fas fa-home"></i> <span>Обзор</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'servers' ? 'active' : ''; ?>" href="?section=servers">
                        <i class="fas fa-server"></i> <span>Серверы</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'inbounds' ? 'active' : ''; ?>" href="?section=inbounds">
                        <i class="fas fa-plug"></i> <span>Inbounds</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'outbounds' ? 'active' : ''; ?>" href="?section=outbounds">
                        <i class="fas fa-share-alt"></i> <span>Outbounds</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'routing' ? 'active' : ''; ?>" href="?section=routing">
                        <i class="fas fa-route"></i> <span>Маршрутизация</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'users' ? 'active' : ''; ?>" href="?section=users">
                        <i class="fas fa-users"></i> <span>Пользователи</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'stats' ? 'active' : ''; ?>" href="?section=stats">
                        <i class="fas fa-chart-line"></i> <span>Статистика</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $section === 'settings' ? 'active' : ''; ?>" href="?section=settings">
                        <i class="fas fa-cog"></i> <span>Настройки</span>
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="?action=logout">
                        <i class="fas fa-sign-out-alt"></i> <span>Выход</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div id="content-container">
            <?php
            switch($section) {
                case 'overview':
                    include 'frontend/components/overview.php';
                    break;
                case 'servers':
                    include 'frontend/components/servers.php';
                    break;
                case 'users':
                    include 'frontend/components/users.php';
                    break;
                case 'inbounds':
                    include 'frontend/components/inbounds.php';
                    break;
                case 'outbounds':
                    include 'frontend/components/outbounds.php';
                    break;
                case 'routing':
                    include 'frontend/components/routing.php';
                    break;
                case 'settings':
                    include 'frontend/components/settings.php';
                    break;
                case 'stats':
                    include 'frontend/components/stats.php';
                    break;
            }
            ?>
        </div>
    </div>

    <!-- Modals -->
    <div id="modals-container">
        <?php include 'frontend/components/modals.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="frontend/js/api.js" type="module"></script>
    <script>
    // Initialize after page load
    document.addEventListener('DOMContentLoaded', function() {
        const event = new CustomEvent('componentsLoaded');
        window.dispatchEvent(event);
    });
    </script>
    <script src="frontend/js/main.js" type="module"></script>
</body>
</html>