<?php
// Modals for all forms
?>
<!-- Add Server Modal -->
<div class="modal fade" id="addServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-server"></i> Добавить сервер</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" data-ajax-form data-action="add">
                <input type="hidden" name="section" value="servers">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название сервера</label>
                        <input type="text" name="name" class="form-control" placeholder="Server-1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Расположение</label>
                        <input type="text" name="location" class="form-control" placeholder="Нидерланды" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP адрес</label>
                        <input type="text" name="ip" class="form-control" placeholder="192.168.1.10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Порты</label>
                        <input type="text" name="ports" class="form-control" placeholder="443, 80">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Добавить пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" data-ajax-form data-action="add">
                <input type="hidden" name="section" value="users">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Имя</label>
                        <input type="text" name="name" class="form-control" placeholder="Иван Петров" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Лимит трафика (GB)</label>
                        <input type="number" name="traffic_limit" min="0" class="form-control" placeholder="50">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Inbound Modal -->
<div class="modal fade" id="addInboundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plug"></i> Добавить Inbound</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" data-ajax-form data-action="add">
                <input type="hidden" name="section" value="inbounds">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название</label>
                        <input type="text" name="name" class="form-control" placeholder="VLESS + TLS" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Тип</label>
                        <select name="type" class="form-select">
                            <option value="vless">VLESS + TLS</option>
                            <option value="vmess">VMESS + WS</option>
                            <option value="trojan">Trojan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Порт</label>
                        <input type="number" name="port" min="1" max="65535" class="form-control" placeholder="443" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Сервер</label>
                        <select name="server_id" class="form-select">
                            <?php
                            // моковый список серверов если переменная не задана
                            $serversForSelect = isset($servers) ? $servers : [
                                ['id' => 1, 'name' => 'Server-1', 'location' => 'Нидерланды'],
                                ['id' => 2, 'name' => 'Server-2', 'location' => 'Германия'],
                            ];
                            foreach($serversForSelect as $server): ?>
                            <option value="<?php echo $server['id']; ?>"><?php echo htmlspecialchars($server['name'] . ' (' . $server['location'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Outbound Modal -->
<div class="modal fade" id="addOutboundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-share-alt"></i> Добавить Outbound</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" data-ajax-form data-action="add">
                <input type="hidden" name="section" value="outbounds">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название</label>
                        <input type="text" name="name" class="form-control" placeholder="Proxy NL->US" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Тип</label>
                        <select name="type" class="form-select">
                            <option value="direct">Direct</option>
                            <option value="block">Block</option>
                            <option value="proxy">Proxy</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <input type="text" name="description" class="form-control" placeholder="Proxy NL->US">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Приоритет</label>
                        <input type="number" name="priority" class="form-control" placeholder="1" min="1" max="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>