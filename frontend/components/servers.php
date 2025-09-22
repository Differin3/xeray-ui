<?php
// Servers management page
?>
<!-- Управление серверами -->
<div id="servers" class="content-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-server"></i> Управление серверами</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
            <i class="fas fa-plus"></i> Добавить сервер
        </button>
    </div>

    <!-- Servers List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-list"></i> Список серверов</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Локация</th>
                            <th>Статус</th>
                            <th>IP Адрес</th>
                            <th>Порты</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="serversTableBody">
                        <?php foreach($servers as $server): ?>
                        <tr>
                            <td><?php echo $server['id']; ?></td>
                            <td><?php echo htmlspecialchars($server['name']); ?></td>
                            <td><?php echo htmlspecialchars($server['location']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $server['status'] === 'online' ? 'success' : 'danger'; ?>">
                                    <?php echo $server['status'] === 'online' ? 'Online' : 'Offline'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($server['ip']); ?></td>
                            <td><?php echo htmlspecialchars($server['ports']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editServer(<?php echo $server['id']; ?>)">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteServer(<?php echo $server['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Server Statistics -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Статистика серверов</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-primary"><?php echo $stats['servers']; ?></h3>
                            <p class="text-muted">Всего серверов</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?php echo $stats['online_servers']; ?></h3>
                            <p class="text-muted">Онлайн</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-network-wired"></i> Трафик</h5>
                </div>
                <div class="card-body traffic-chart-container">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>