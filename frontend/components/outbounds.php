<?php
// Outbounds management page
?>
<!-- Outbounds Management -->
<div id="outbounds" class="content-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-share-alt"></i> Управление Outbounds</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOutboundModal">
            <i class="fas fa-plus"></i> Добавить Outbound
        </button>
    </div>

    <!-- Outbounds List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-list"></i> Список Outbounds</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Тип</th>
                            <th>Описание</th>
                            <th>Статус</th>
                            <th>Приоритет</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="outboundsTableBody">
                        <?php foreach($outbounds as $outbound): ?>
                        <tr>
                            <td><?php echo $outbound['id']; ?></td>
                            <td><?php echo htmlspecialchars($outbound['name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $outbound['type'] === 'direct' ? 'success' : 
                                        ($outbound['type'] === 'block' ? 'danger' : 'info'); 
                                ?>">
                                    <?php echo ucfirst($outbound['type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($outbound['description']); ?></td>
                            <td>
                                <span class="badge bg-success">Активен</span>
                            </td>
                            <td><?php echo $outbound['priority']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editOutbound(<?php echo $outbound['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteOutbound(<?php echo $outbound['id']; ?>)">
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

    <!-- Outbound Configuration -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-cog"></i> Конфигурация Outbound</h5>
                </div>
                <div class="card-body">
                    <form id="outboundConfigForm">
                        <div class="mb-3">
                            <label class="form-label">Тип Outbound</label>
                            <select class="form-select" id="outboundType">
                                <option value="direct">Direct</option>
                                <option value="block">Block</option>
                                <option value="proxy">Proxy</option>
                                <option value="freedom">Freedom</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Название</label>
                            <input type="text" class="form-control" id="outboundName" placeholder="Proxy NL->US">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" id="outboundDescription" rows="3" placeholder="Описание outbound"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Приоритет</label>
                            <input type="number" class="form-control" id="outboundPriority" placeholder="1" min="1" max="100">
                        </div>
                        <div class="mb-3" id="proxySettings" style="display: none;">
                            <label class="form-label">Сервер назначения</label>
                            <select class="form-select" id="proxyServer">
                                <?php foreach($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>"><?php echo htmlspecialchars($server['name'] . ' (' . $server['location'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить конфигурацию</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Статистика Outbounds</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-primary"><?php echo count($outbounds); ?></h3>
                            <p class="text-muted">Всего Outbounds</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?php echo count($outbounds); ?></h3>
                            <p class="text-muted">Активных</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-info">85%</h3>
                            <p class="text-muted">Прямой трафик</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-warning">15%</h3>
                            <p class="text-muted">Прокси трафик</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>