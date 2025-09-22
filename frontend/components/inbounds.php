<!-- Inbounds management page -->
<!-- Inbounds Management -->
<div id="inbounds" class="content-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plug"></i> Управление Inbounds</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInboundModal">
            <i class="fas fa-plus"></i> Добавить Inbound
        </button>
    </div>

    <!-- Inbounds List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-list"></i> Список Inbounds</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Тип</th>
                            <th>Порт</th>
                            <th>Сервер</th>
                            <th>Статус</th>
                            <th>Пользователи</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="inboundsTableBody">
                        <?php foreach($inbounds as $inbound): ?>
                        <tr>
                            <td><?php echo $inbound['id']; ?></td>
                            <td><?php echo htmlspecialchars($inbound['name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $inbound['type'] === 'vless' ? 'primary' : 
                                        ($inbound['type'] === 'vmess' ? 'info' : 'warning'); 
                                ?>">
                                    <?php echo strtoupper($inbound['type']); ?>
                                </span>
                            </td>
                            <td><?php echo $inbound['port']; ?></td>
                            <td><?php echo htmlspecialchars($inbound['server']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $inbound['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo $inbound['status'] === 'active' ? 'Активен' : 'Ожидание'; ?>
                                </span>
                            </td>
                            <td><?php echo $inbound['users']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editInbound(<?php echo $inbound['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteInbound(<?php echo $inbound['id']; ?>)">
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

    <!-- Inbound Configuration -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-cog"></i> Конфигурация Inbound</h5>
                </div>
                <div class="card-body">
                    <form id="inboundConfigForm">
                        <div class="mb-3">
                            <label class="form-label">Тип протокола</label>
                            <select class="form-select" id="inboundProtocol">
                                <option value="vless">VLESS + TLS</option>
                                <option value="vmess">VMESS + WS</option>
                                <option value="trojan">Trojan</option>
                                <option value="shadowsocks">Shadowsocks</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Порт</label>
                            <input type="number" class="form-control" id="inboundPort" placeholder="443">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Сервер</label>
                            <select class="form-select" id="inboundServer">
                                <?php foreach($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>"><?php echo htmlspecialchars($server['name'] . ' (' . $server['location'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">TLS сертификат</label>
                            <input type="text" class="form-control" id="inboundTLS" placeholder="Путь к сертификату">
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить конфигурацию</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Статистика Inbounds</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-primary"><?php echo count($inbounds); ?></h3>
                            <p class="text-muted">Всего Inbounds</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?php echo count(array_filter($inbounds, fn($i) => $i['status'] === 'active')); ?></h3>
                            <p class="text-muted">Активных</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-info"><?php echo array_sum(array_column($inbounds, 'users')); ?></h3>
                            <p class="text-muted">Пользователей</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-warning">1.2 TB</h3>
                            <p class="text-muted">Трафика</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>