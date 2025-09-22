<?php
// Xeray UI - API Functions

/**
 * Handle AJAX requests
 */
function handleAjaxRequest($section, $action) {
    header('Content-Type: application/json');
    
    try {
        switch ($section) {
            case 'servers':
                $result = handleServerAjax($action);
                break;
            case 'users':
                $result = handleUserAjax($action);
                break;
            case 'inbounds':
                $result = handleInboundAjax($action);
                break;
            case 'outbounds':
                $result = handleOutboundAjax($action);
                break;
            case 'routing':
                $result = handleRoutingAjax($action);
                break;
            case 'stats':
                $result = handleStatsAjax($action);
                break;
            case 'settings':
                $result = handleSettingsAjax($action);
                break;
            default:
                $result = ['error' => 'Invalid section'];
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Handle form submissions
 */
function handleFormSubmission($section, $action) {
    try {
        switch ($section) {
            case 'servers':
                handleServerForm($action);
                break;
            case 'users':
                handleUserForm($action);
                break;
            case 'inbounds':
                handleInboundForm($action);
                break;
            case 'outbounds':
                handleOutboundForm($action);
                break;
            case 'routing':
                handleRoutingForm($action);
                break;
            case 'settings':
                handleSettingsForm($action);
                break;
        }
        
        // Redirect to prevent form resubmission
        $redirect_url = "?section={$section}";
        if (isset($_GET['success'])) {
            $redirect_url .= "&success=1";
        }
        header("Location: {$redirect_url}");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: ?section={$section}&error=1");
        exit;
    }
}

/**
 * Server AJAX handlers
 */
function handleServerAjax($action) {
    switch ($action) {
        case 'get':
            return getServers();
        case 'add':
            return addServer($_POST);
        case 'update':
            return updateServer($_POST['id'], $_POST);
        case 'delete':
            return deleteServer($_POST['id']);
        case 'status':
            return getServerStatus($_POST['id']);
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * User AJAX handlers
 */
function handleUserAjax($action) {
    switch ($action) {
        case 'get':
            return getUsers();
        case 'add':
            return addUser($_POST);
        case 'update':
            return updateUser($_POST['id'], $_POST);
        case 'delete':
            return deleteUser($_POST['id']);
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * Inbound AJAX handlers
 */
function handleInboundAjax($action) {
    switch ($action) {
        case 'get':
            return getInbounds();
        case 'add':
            return addInbound($_POST);
        case 'update':
            return updateInbound($_POST['id'], $_POST);
        case 'delete':
            return deleteInbound($_POST['id']);
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * Outbound AJAX handlers
 */
function handleOutboundAjax($action) {
    switch ($action) {
        case 'get':
            return getOutbounds();
        case 'add':
            return addOutbound($_POST);
        case 'update':
            return updateOutbound($_POST['id'], $_POST);
        case 'delete':
            return deleteOutbound($_POST['id']);
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * Routing AJAX handlers
 */
function handleRoutingAjax($action) {
    switch ($action) {
        case 'get':
            return getRoutingRules();
        case 'add':
            return addRoutingRule($_POST);
        case 'update':
            return updateRoutingRule($_POST['id'], $_POST);
        case 'delete':
            return deleteRoutingRule($_POST['id']);
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * Stats AJAX handlers
 */
function handleStatsAjax($action) {
    switch ($action) {
        case 'get':
            return getStats();
        case 'traffic':
            return getTrafficStats();
        case 'servers':
            return getServerStats();
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * Settings AJAX handlers
 */
function handleSettingsAjax($action) {
    switch ($action) {
        case 'get':
            return getSettings();
        case 'update':
            return updateSettings($_POST);
        default:
            return ['error' => 'Invalid action'];
    }
}

/**
 * Server form handlers
 */
function handleServerForm($action) {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'] ?? '',
                'location' => $_POST['location'] ?? '',
                'ip' => $_POST['ip'] ?? '',
                'ports' => $_POST['ports'] ?? ''
            ];
            addServer($data);
            $_SESSION['success'] = 'Сервер успешно добавлен';
            break;
        case 'update':
            $data = [
                'name' => $_POST['name'] ?? '',
                'location' => $_POST['location'] ?? '',
                'ip' => $_POST['ip'] ?? '',
                'ports' => $_POST['ports'] ?? ''
            ];
            updateServer($_POST['id'], $data);
            $_SESSION['success'] = 'Сервер успешно обновлен';
            break;
        case 'delete':
            deleteServer($_POST['id']);
            $_SESSION['success'] = 'Сервер успешно удален';
            break;
    }
}

/**
 * User form handlers
 */
function handleUserForm($action) {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'traffic_limit' => $_POST['traffic_limit'] ?? 0
            ];
            addUser($data);
            $_SESSION['success'] = 'Пользователь успешно добавлен';
            break;
        case 'update':
            $data = [
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'traffic_limit' => $_POST['traffic_limit'] ?? 0
            ];
            updateUser($_POST['id'], $data);
            $_SESSION['success'] = 'Пользователь успешно обновлен';
            break;
        case 'delete':
            deleteUser($_POST['id']);
            $_SESSION['success'] = 'Пользователь успешно удален';
            break;
    }
}

/**
 * Inbound form handlers
 */
function handleInboundForm($action) {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'port' => $_POST['port'] ?? 0,
                'server_id' => $_POST['server_id'] ?? 0
            ];
            addInbound($data);
            $_SESSION['success'] = 'Inbound успешно добавлен';
            break;
        case 'update':
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'port' => $_POST['port'] ?? 0,
                'server_id' => $_POST['server_id'] ?? 0
            ];
            updateInbound($_POST['id'], $data);
            $_SESSION['success'] = 'Inbound успешно обновлен';
            break;
        case 'delete':
            deleteInbound($_POST['id']);
            $_SESSION['success'] = 'Inbound успешно удален';
            break;
    }
}

/**
 * Outbound form handlers
 */
function handleOutboundForm($action) {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'description' => $_POST['description'] ?? '',
                'priority' => $_POST['priority'] ?? 1
            ];
            addOutbound($data);
            $_SESSION['success'] = 'Outbound успешно добавлен';
            break;
        case 'update':
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'description' => $_POST['description'] ?? '',
                'priority' => $_POST['priority'] ?? 1
            ];
            updateOutbound($_POST['id'], $data);
            $_SESSION['success'] = 'Outbound успешно обновлен';
            break;
        case 'delete':
            deleteOutbound($_POST['id']);
            $_SESSION['success'] = 'Outbound успешно удален';
            break;
    }
}

/**
 * Routing form handlers
 */
function handleRoutingForm($action) {
    switch ($action) {
        case 'add':
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'outbound' => $_POST['outbound'] ?? '',
                'priority' => $_POST['priority'] ?? 1
            ];
            addRoutingRule($data);
            $_SESSION['success'] = 'Правило маршрутизации успешно добавлено';
            break;
        case 'update':
            $data = [
                'name' => $_POST['name'] ?? '',
                'type' => $_POST['type'] ?? '',
                'condition' => $_POST['condition'] ?? '',
                'outbound' => $_POST['outbound'] ?? '',
                'priority' => $_POST['priority'] ?? 1
            ];
            updateRoutingRule($_POST['id'], $data);
            $_SESSION['success'] = 'Правило маршрутизации успешно обновлено';
            break;
        case 'delete':
            deleteRoutingRule($_POST['id']);
            $_SESSION['success'] = 'Правило маршрутизации успешно удалено';
            break;
    }
}

/**
 * Settings form handlers
 */
function handleSettingsForm($action) {
    switch ($action) {
        case 'update':
            $data = $_POST;
            updateSettings($data);
            $_SESSION['success'] = 'Настройки успешно обновлены';
            break;
    }
}

/**
 * Database functions (placeholder - replace with actual database calls)
 */
function getServers() {
    // This should connect to actual database
    return [
        ['id' => 1, 'name' => 'Server-1', 'location' => 'Нидерланды', 'status' => 'online', 'ip' => '192.168.1.101', 'ports' => '443, 80, 8080'],
        ['id' => 2, 'name' => 'Server-2', 'location' => 'Германия', 'status' => 'online', 'ip' => '192.168.1.102', 'ports' => '443, 80'],
        ['id' => 3, 'name' => 'Server-3', 'location' => 'США', 'status' => 'offline', 'ip' => '192.168.1.103', 'ports' => '443, 8443']
    ];
}

function addServer($data) {
    // Implement database insert
    return true;
}

function updateServer($id, $data) {
    // Implement database update
    return true;
}

function deleteServer($id) {
    // Implement database delete
    return true;
}

function getUsers() {
    return [
        ['id' => 1, 'name' => 'Иван Петров', 'email' => 'ivan@example.com', 'traffic' => '12.5/50 GB', 'status' => 'active'],
        ['id' => 2, 'name' => 'Мария Сидорова', 'email' => 'maria@example.com', 'traffic' => '35.2/100 GB', 'status' => 'active'],
        ['id' => 3, 'name' => 'Алексей Иванов', 'email' => 'alex@example.com', 'traffic' => '78.9/50 GB', 'status' => 'limited']
    ];
}

function addUser($data) {
    return true;
}

function updateUser($id, $data) {
    return true;
}

function deleteUser($id) {
    return true;
}

function getInbounds() {
    return [
        ['id' => 1, 'name' => 'VLESS + TLS', 'type' => 'vless', 'port' => 443, 'server' => 'Server-1 (NL)', 'status' => 'active', 'users' => 12],
        ['id' => 2, 'name' => 'VMESS + WS', 'type' => 'vmess', 'port' => 8080, 'server' => 'Server-2 (DE)', 'status' => 'active', 'users' => 8],
        ['id' => 3, 'name' => 'Trojan', 'type' => 'trojan', 'port' => 8443, 'server' => 'Server-3 (US)', 'status' => 'waiting', 'users' => 0]
    ];
}

function addInbound($data) {
    return true;
}

function updateInbound($id, $data) {
    return true;
}

function deleteInbound($id) {
    return true;
}

function getOutbounds() {
    return [
        ['id' => 1, 'name' => 'Direct', 'type' => 'direct', 'description' => 'Прямое соединение', 'status' => 'active', 'priority' => 1],
        ['id' => 2, 'name' => 'Block', 'type' => 'block', 'description' => 'Блокировка трафика', 'status' => 'active', 'priority' => 2],
        ['id' => 3, 'name' => 'Proxy NL->US', 'type' => 'proxy', 'description' => 'Через Server-3 (US)', 'status' => 'active', 'priority' => 3]
    ];
}

function addOutbound($data) {
    return true;
}

function updateOutbound($id, $data) {
    return true;
}

function deleteOutbound($id) {
    return true;
}

function getRoutingRules() {
    return [
        ['id' => 1, 'name' => 'Блокировка рекламы', 'type' => 'domain', 'condition' => 'ads.example.com', 'outbound' => 'Block', 'priority' => 1, 'status' => 'active'],
        ['id' => 2, 'name' => 'Локальная сеть', 'type' => 'ip', 'condition' => '192.168.0.0/16', 'outbound' => 'Direct', 'priority' => 2, 'status' => 'active'],
        ['id' => 3, 'name' => 'Прокси для США', 'type' => 'geoip', 'condition' => 'US', 'outbound' => 'Proxy NL->US', 'priority' => 3, 'status' => 'active']
    ];
}

function addRoutingRule($data) {
    return true;
}

function updateRoutingRule($id, $data) {
    return true;
}

function deleteRoutingRule($id) {
    return true;
}

function getStats() {
    return [
        'servers' => 3,
        'inbounds' => 3,
        'users' => 3,
        'traffic' => '256 GB',
        'online_servers' => 2
    ];
}

function getTrafficStats() {
    return [
        'total' => '256 GB',
        'today' => '12.5 GB',
        'this_month' => '156 GB'
    ];
}

function getServerStats() {
    return [
        'total' => 3,
        'online' => 2,
        'offline' => 1
    ];
}

function getSettings() {
    return [
        'app_name' => 'Xeray Control Panel',
        'timezone' => 'Europe/Moscow',
        'auto_refresh' => 30
    ];
}

function updateSettings($data) {
    return true;
}

function getServerStatus($id) {
    // Implement server status check
    return ['status' => 'online', 'uptime' => '99.9%'];
}
?>
