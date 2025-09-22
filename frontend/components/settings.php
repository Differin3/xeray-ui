<?php
// Settings management page
?>
<!-- Settings Management -->
<div id="settings" class="content-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cog"></i> Настройки системы</h2>
        <button class="btn btn-success" onclick="saveAllSettings()">
            <i class="fas fa-save"></i> Сохранить все
        </button>
    </div>

    <!-- General Settings -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-sliders-h"></i> Общие настройки</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Название панели</label>
                        <input type="text" class="form-control" id="panelName" value="<?php echo htmlspecialchars($config['app_name']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Язык интерфейса</label>
                        <select class="form-select" id="panelLanguage">
                            <option value="ru" selected>Русский</option>
                            <option value="en">English</option>
                            <option value="zh">中文</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Автообновление (секунды)</label>
                        <input type="number" class="form-control" id="autoRefresh" value="<?php echo $config['auto_refresh']; ?>" min="5" max="300">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enableNotifications" checked>
                            <label class="form-check-label" for="enableNotifications">
                                Уведомления в браузере
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> Информация о системе</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Версия Xeray UI:</strong></td>
                            <td><?php echo $config['version']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP версия:</strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Использование памяти:</strong></td>
                            <td><?php echo round(memory_get_usage() / 1024 / 1024, 1); ?> MB</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Статус системы:</strong></td>
                            <td><span class="badge bg-success">Работает</span></td>
                        </tr>
                        <tr>
                            <td><strong>Последнее обновление:</strong></td>
                            <td><?php echo date('Y-m-d H:i:s'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>