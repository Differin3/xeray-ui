<?php
// Statistics page
?>
<!-- Statistics -->
<div id="stats" class="content-section">
    <h2 class="mb-4"><i class="fas fa-chart-line"></i> Статистика системы</h2>

    <!-- Main Statistics -->
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

    <!-- Charts -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Распределение трафика</h5>
                </div>
                <div class="card-body">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar"></i> Статистика серверов</h5>
                </div>
                <div class="card-body">
                    <canvas id="serversChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>





