<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

// Require admin role
require_role('admin');

// Get current system metrics
$uptime_raw = shell_exec('uptime');
$load_avg = trim(shell_exec("uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//'"));

// Memory usage
$mem_info = shell_exec('free');
preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $mem_info, $mem_matches);
$mem_total = $mem_matches[1] ?? 1;
$mem_used = $mem_matches[2] ?? 0;
$mem_free = $mem_matches[3] ?? 0;
$mem_percent = round(($mem_used / $mem_total) * 100, 1);

// Swap usage
preg_match('/Swap:\s+(\d+)\s+(\d+)/', $mem_info, $swap_matches);
$swap_total = $swap_matches[1] ?? 1;
$swap_used = $swap_matches[2] ?? 0;
$swap_percent = $swap_total > 0 ? round(($swap_used / $swap_total) * 100, 1) : 0;

// Disk usage
$disk_usage = shell_exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'");
$disk_percent = intval(trim($disk_usage));

// Apache processes
$apache_processes = intval(trim(shell_exec("ps aux | grep apache2 | grep -v grep | wc -l")));

// Top CPU processes
$top_cpu = shell_exec("ps aux --sort=-%cpu --no-headers | head -5");
$cpu_processes = [];
if ($top_cpu) {
    foreach (explode("\n", trim($top_cpu)) as $line) {
        if (trim($line)) {
            $parts = preg_split('/\s+/', trim($line), 11);
            if (count($parts) >= 11) {
                $cpu_processes[] = [
                    'user' => $parts[0],
                    'pid' => $parts[1],
                    'cpu' => $parts[2],
                    'mem' => $parts[3],
                    'command' => $parts[10]
                ];
            }
        }
    }
}

// System alerts
$alerts = [];
if (file_exists('/var/log/system_alerts.log')) {
    $alert_lines = shell_exec("tail -20 /var/log/system_alerts.log");
    if ($alert_lines) {
        foreach (explode("\n", trim($alert_lines)) as $line) {
            if (trim($line)) {
                $alerts[] = trim($line);
            }
        }
        $alerts = array_reverse($alerts); // Show newest first
    }
}

// System status determination
$system_status = 'healthy';
$status_color = 'success';
$status_message = 'All systems operating normally';

if ($load_avg > 2.0 || $mem_percent > 90 || $disk_percent > 85 || $swap_percent > 50) {
    $system_status = 'critical';
    $status_color = 'danger';
    $status_message = 'System requires immediate attention';
} elseif ($load_avg > 1.0 || $mem_percent > 80 || $disk_percent > 75 || $swap_percent > 25) {
    $system_status = 'warning';
    $status_color = 'warning';
    $status_message = 'System under moderate stress';
}

// Include header
require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>System Monitor</h1>
        <div class="d-flex gap-2">
            <span class="badge bg-<?php echo $status_color; ?> fs-6 px-3 py-2">
                <?php echo ucfirst($system_status); ?>
            </span>
            <button class="btn btn-outline-primary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>
    
    <div class="alert alert-<?php echo $status_color; ?>">
        <i class="bi bi-<?php echo $status_color === 'success' ? 'check-circle' : ($status_color === 'warning' ? 'exclamation-triangle' : 'x-circle'); ?>"></i>
        <?php echo $status_message; ?>
    </div>
    
    <!-- Resource Usage Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">System Load</h6>
                    <h2 class="card-title mb-1 <?php echo $load_avg > 2.0 ? 'text-danger' : ($load_avg > 1.0 ? 'text-warning' : 'text-success'); ?>">
                        <?php echo $load_avg; ?>
                    </h2>
                    <small class="text-muted">
                        <?php echo trim($uptime_raw); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Memory Usage</h6>
                    <h2 class="card-title mb-1 <?php echo $mem_percent > 90 ? 'text-danger' : ($mem_percent > 80 ? 'text-warning' : 'text-success'); ?>">
                        <?php echo $mem_percent; ?>%
                    </h2>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-<?php echo $mem_percent > 90 ? 'danger' : ($mem_percent > 80 ? 'warning' : 'success'); ?>" 
                             style="width: <?php echo $mem_percent; ?>%"></div>
                    </div>
                    <small class="text-muted">
                        <?php echo round($mem_used / 1024 / 1024, 1); ?>GB / <?php echo round($mem_total / 1024 / 1024, 1); ?>GB
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Disk Usage</h6>
                    <h2 class="card-title mb-1 <?php echo $disk_percent > 85 ? 'text-danger' : ($disk_percent > 75 ? 'text-warning' : 'text-success'); ?>">
                        <?php echo $disk_percent; ?>%
                    </h2>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-<?php echo $disk_percent > 85 ? 'danger' : ($disk_percent > 75 ? 'warning' : 'success'); ?>" 
                             style="width: <?php echo $disk_percent; ?>%"></div>
                    </div>
                    <small class="text-muted">Root filesystem</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Apache Processes</h6>
                    <h2 class="card-title mb-1 <?php echo $apache_processes > 20 ? 'text-danger' : ($apache_processes > 15 ? 'text-warning' : 'text-success'); ?>">
                        <?php echo $apache_processes; ?>
                    </h2>
                    <small class="text-muted">
                        Active workers<br>
                        <span class="badge bg-info">Limit: 500MB RAM</span>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Top Processes -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top CPU Processes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>PID</th>
                                    <th>CPU%</th>
                                    <th>MEM%</th>
                                    <th>Command</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cpu_processes as $proc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proc['pid']); ?></td>
                                        <td>
                                            <span class="<?php echo $proc['cpu'] > 50 ? 'text-danger fw-bold' : ($proc['cpu'] > 20 ? 'text-warning' : ''); ?>">
                                                <?php echo htmlspecialchars($proc['cpu']); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($proc['mem']); ?>%</td>
                                        <td>
                                            <small><?php echo htmlspecialchars(substr($proc['command'], 0, 40)) . (strlen($proc['command']) > 40 ? '...' : ''); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Alerts -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Alerts</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($alerts)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-check-circle fs-3"></i>
                            <p class="mb-0">No recent alerts</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($alerts, 0, 10) as $alert): ?>
                                <div class="list-group-item px-0 py-2">
                                    <small class="text-danger">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <?php echo htmlspecialchars($alert); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Monitoring Status -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Monitoring Status & Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Active Monitors</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle text-success"></i> CPU Monitor (every 5 min)</li>
                                <li><i class="bi bi-check-circle text-success"></i> System Alerts (every 15 min)</li>
                                <li><i class="bi bi-check-circle text-success"></i> Apache Resource Limits</li>
                                <li><i class="bi bi-check-circle text-success"></i> Log Rotation</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>Alert Thresholds</h6>
                            <ul class="list-unstyled">
                                <li><small>CPU: >80%</small></li>
                                <li><small>Memory: >90%</small></li>
                                <li><small>Load: >2.0</small></li>
                                <li><small>Swap: >500MB</small></li>
                                <li><small>Disk: >85%</small></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6>Quick Terminal Commands</h6>
                            <div class="bg-dark text-light p-2 rounded" style="font-family: monospace; font-size: 0.85rem;">
                                <div>tail -20 /var/log/system_alerts.log</div>
                                <div>tail -20 /var/log/cpu_monitor.log</div>
                                <div>htop</div>
                                <div>ps aux --sort=-%cpu | head -10</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 60 seconds
setInterval(() => {
    location.reload();
}, 60000);

// Show last refresh time
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date().toLocaleString();
    console.log('System monitor loaded at:', now);
});
</script>

<!-- Dark theme fixes for system-monitor -->
<style>
/* Fix table visibility issues */
.table td, .table th {
    color: rgba(255, 255, 255, 0.95) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
}

.table thead th {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
}

.table tbody tr:hover {
    background: rgba(255, 255, 255, 0.12) !important;
}

.table .text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Fix list group items */
.list-group-item {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.list-group-item .text-muted {
    color: rgba(255, 255, 255, 0.75) !important;
}

/* Fix system alerts text */
.list-group-item small {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Force white text in all elements */
.card .card-body table td,
.card .card-body table th {
    color: #ffffff !important;
}

.card .card-body .list-group-item {
    color: #ffffff !important;
}

/* Fix CPU percentage colors */
.text-danger, .text-warning {
    color: inherit !important;
}

.fw-bold {
    color: #ffffff !important;
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 