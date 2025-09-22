<?php
// Routing management page
?>
<!-- Routing Management -->
<div id="routing" class="content-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-route"></i> Маршрутизация</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoutingRuleModal">
            <i class="fas fa-plus"></i> Добавить правило
        </button>
    </div>

    <!-- Routing Rules -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-list"></i> Правила маршрутизации</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Тип</th>
                            <th>Условие</th>
                            <th>Outbound</th>
                            <th>Приоритет</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="routingTableBody">
                        <?php foreach($routing_rules as $rule): ?>
                        <tr>
                            <td><?php echo $rule['id']; ?></td>
                            <td><?php echo htmlspecialchars($rule['name']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $rule['type'] === 'domain' ? 'danger' : 
                                        ($rule['type'] === 'ip' ? 'info' : 'warning'); 
                                ?>">
                                    <?php echo strtoupper($rule['type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($rule['condition']); ?></td>
                            <td><?php echo htmlspecialchars($rule['outbound']); ?></td>
                            <td><?php echo $rule['priority']; ?></td>
                            <td>
                                <span class="badge bg-success">Активно</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editRoutingRule(<?php echo $rule['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteRoutingRule(<?php echo $rule['id']; ?>)">
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

    <!-- Routing Configuration -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-cog"></i> Настройка правила</h5>
                </div>
                <div class="card-body">
                    <form id="routingConfigForm">
                        <div class="mb-3">
                            <label class="form-label">Название правила</label>
                            <input type="text" class="form-control" id="ruleName" placeholder="Блокировка рекламы">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Тип условия</label>
                            <select class="form-select" id="ruleType">
                                <option value="domain">Домен</option>
                                <option value="ip">IP адрес</option>
                                <option value="geoip">Страна (GeoIP)</option>
                                <option value="port">Порт</option>
                                <option value="protocol">Протокол</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Условие</label>
                            <input type="text" class="form-control" id="ruleCondition" placeholder="ads.example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Outbound</label>
                            <select class="form-select" id="ruleOutbound">
                                <option value="direct">Direct</option>
                                <option value="block">Block</option>
                                <option value="proxy">Proxy NL->US</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Приоритет</label>
                            <input type="number" class="form-control" id="rulePriority" placeholder="1" min="1" max="100">
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить правило</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Статистика маршрутизации</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-primary"><?php echo count($routing_rules); ?></h3>
                            <p class="text-muted">Всего правил</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?php echo count($routing_rules); ?></h3>
                            <p class="text-muted">Активных</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-info">1.2K</h3>
                            <p class="text-muted">Запросов/час</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-warning">15ms</h3>
                            <p class="text-muted">Средняя задержка</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Routing Diagram -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-project-diagram"></i> Схема маршрутизации</h5>
        </div>
        <div class="card-body">
            <div class="routing-diagram">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="routing-node">
                            <i class="fas fa-user fa-2x text-primary"></i>
                            <p>Пользователь</p>
                        </div>
                    </div>
                    <div class="col-md-1 text-center">
                        <i class="fas fa-arrow-right fa-2x text-muted"></i>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="routing-node">
                            <i class="fas fa-filter fa-2x text-warning"></i>
                            <p>Правила маршрутизации</p>
                        </div>
                    </div>
                    <div class="col-md-1 text-center">
                        <i class="fas fa-arrow-right fa-2x text-muted"></i>
                    </div>
                    <div class="col-md-4">
                        <div class="row">
                            <div class="col-6 text-center">
                                <div class="routing-node">
                                    <i class="fas fa-share-alt fa-2x text-success"></i>
                                    <p>Direct</p>
                                </div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="routing-node">
                                    <i class="fas fa-ban fa-2x text-danger"></i>
                                    <p>Block</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>