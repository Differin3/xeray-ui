<?php
// Overview page (временные мок-данные, чтобы работать без БД)
if (!isset($servers)) {
    $servers = [
        ['id' => 1, 'name' => 'Server-1', 'location' => 'Нидерланды', 'status' => 'online'],
        ['id' => 2, 'name' => 'Server-2', 'location' => 'Германия',   'status' => 'online'],
        ['id' => 3, 'name' => 'Server-3', 'location' => 'США',        'status' => 'offline'],
    ];
}

if (!isset($inbounds)) {
    $inbounds = [
        ['name' => 'VLESS + TLS', 'port' => 443,  'status' => 'active',   'server' => 'Server-1', 'users' => 12],
        ['name' => 'VMESS + WS',  'port' => 8080, 'status' => 'pending',  'server' => 'Server-2', 'users' => 7],
    ];
}

if (!isset($outbounds)) {
    $outbounds = [
        ['name' => 'Proxy NL->US', 'description' => 'Маршрут через NL POP'],
        ['name' => 'Direct',       'description' => 'Прямое подключение'],
    ];
}

if (!isset($stats)) {
    $stats = [
        'servers' => count($servers),
        'inbounds' => count($inbounds),
        'users' => 25,
        'traffic' => 128,
    ];
}

if (!isset($logs)) {
    $logs = [
        ['type' => 'info',    'message' => 'Система готова к работе',                  'time' => '12:00:01'],
        ['type' => 'success', 'message' => 'Сервер "Server-1 (NL)" успешно обновлён', 'time' => '12:01:22'],
        ['type' => 'warning', 'message' => 'Высокая нагрузка на "Server-2 (DE)"',     'time' => '12:02:10'],
        ['type' => 'error',   'message' => 'Потеряно соединение с "Server-3 (US)"',  'time' => '12:03:45'],
    ];
}
?>
<!-- Обзорная панель (тёмный стиль) -->
<div id="overview" class="content-section">
    <h2 class="mb-4" style="color: var(--text-1);">Обзор системы</h2>

    <!-- Статистика -->
    <div class="row">
        <div class="col-md-3">
            <div class="card stats-card">
                <i class="fas fa-server"></i>
                <h2><?php echo $stats['servers']; ?></h2>
                <p>Серверов</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <i class="fas fa-plug"></i>
                <h2><?php echo $stats['inbounds']; ?></h2>
                <p>Inbounds</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <i class="fas fa-users"></i>
                <h2><?php echo $stats['users']; ?></h2>
                <p>Пользователей</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <i class="fas fa-network-wired"></i>
                <h2><?php echo $stats['traffic']; ?></h2>
                <p>Трафика</p>
            </div>
        </div>
    </div>

    <!-- Карта сети и серверы -->
    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-network-wired"></i> Карта сети</h5>
                </div>
                <div class="card-body">
                    <div id="networkCanvas"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="simulation-panel">
                <div class="panel-header">
                    <h6 class="panel-title">Simulation Panel</h6>
                    <i class="fas fa-gear" style="color: var(--text-2);"></i>
                </div>
                <div class="panel-body">
                    <div class="controls">
                        <button class="control-btn" title="Назад"><i class="fas fa-backward"></i></button>
                        <button class="control-btn" title="Старт"><i class="fas fa-play"></i></button>
                        <button class="control-btn" title="Пауза"><i class="fas fa-pause"></i></button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span style="color: var(--text-2);">События</span>
                        <a href="#" style="color: var(--primary-color); text-decoration: none;">Сбросить</a>
                    </div>
                    <div class="event-list" id="eventList">
                        <div class="event-row"><span class="dot"></span><span>PC → Router · ICMP</span><span style="text-align:right; color: var(--text-2);">0.126s</span></div>
                        <div class="event-row"><span class="dot"></span><span>Router → ISP · ICMP</span><span style="text-align:right; color: var(--text-2);">0.254s</span></div>
                        <div class="event-row"><span class="dot" style="background: var(--warning-color);"></span><span>Hub 1 → PC · TCP</span><span style="text-align:right; color: var(--text-2);">0.356s</span></div>
                    </div>
                    <div class="mt-3" style="color: var(--text-2);">Фильтр событий</div>
                    <div class="filter-grid mt-2">
                        <div class="chip">ARP</div>
                        <div class="chip">DNS</div>
                        <div class="chip">ICMP</div>
                        <div class="chip">BGP</div>
                        <div class="chip">OSPF</div>
                        <div class="chip">DHCP</div>
                        <div class="chip">HSRP</div>
                        <div class="chip">RIP</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inbounds/Outbounds и логи -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-plug"></i> Активные Inbounds</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addInboundModal"><i class="fas fa-plus"></i> Добавить</button>
                </div>
                <div class="card-body">
                    <div class="inbound-list">
                        <div class="list-group">
                            <?php foreach($inbounds as $inbound): ?>
                            <a href="#" class="list-group-item list-group-item-action" style="background: var(--bg-2); color: var(--text-1); border-color: var(--card-border);">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo $inbound['name']; ?> (<?php echo $inbound['port']; ?>)</h6>
                                    <small class="text-<?php echo $inbound['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo $inbound['status'] === 'active' ? 'активен' : 'ожидание'; ?>
                                    </small>
                                </div>
                                <p class="mb-1">Server: <?php echo $inbound['server']; ?> · <?php echo $inbound['users']; ?> пользователей</p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-share-alt"></i> Активные Outbounds</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addOutboundModal"><i class="fas fa-plus"></i> Добавить</button>
                </div>
                <div class="card-body">
                    <div class="outbound-list">
                        <div class="list-group">
                            <?php foreach($outbounds as $outbound): ?>
                            <a href="#" class="list-group-item list-group-item-action" style="background: var(--bg-2); color: var(--text-1); border-color: var(--card-border);">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo $outbound['name']; ?></h6>
                                    <small class="text-success">активен</small>
                                </div>
                                <p class="mb-1"><?php echo $outbound['description']; ?></p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Логи -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-terminal"></i> Системные логи</h5>
                </div>
                <div class="card-body">
                    <div class="log-entries" id="systemLogs">
                        <?php foreach($logs as $log): ?>
                        <div class="log-entry <?php echo $log['type']; ?>">
                            <span>[<?php echo $log['time']; ?>]</span> <?php echo $log['message']; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>