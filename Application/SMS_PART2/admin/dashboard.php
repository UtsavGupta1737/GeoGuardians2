<?php
/**
 * Command Center Overview Dashboard
 * 
 * 100% Focused on Live Mapping, Active SOS Emergencies, Metrics, and Gateway health telemetry.
 */

$page_title = 'Overview Dashboard';

// Include layouts and configurations
define('SECURE_ACCESS', true);
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/../models/SmsMessage.php';
require_once __DIR__ . '/../models/SosRequest.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// 1. Fetch KPI metrics from DB
$metrics = SosRequest::getMetrics();

// 2. Fetch all SOS alerts for Leaflet JS Map and the feed
$activeSosAlerts = SosRequest::getAll();
$allRequests = $activeSosAlerts;

// 3. Fetch recent system activity events (Audit Logs)
$auditLogs = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>

<!-- KPI Cards -->
<section class="metrics-row">
    <div class="metric-card messages">
        <div class="metric-title">Total Messages Logged</div>
        <div class="metric-value"><?php echo number_format($metrics['total_messages']); ?></div>
        <div class="metric-footer">
            <span class="trend-up">▲ Active SIM</span> Gateway Connection Active
        </div>
    </div>
    
    <div class="metric-card sos">
        <div class="metric-title">SOS Alerts Registered</div>
        <div class="metric-value"><?php echo number_format($metrics['total_sos']); ?></div>
        <div class="metric-footer">
            <span class="trend-up">▲ Active Parser</span> Matches Rule Index
        </div>
    </div>

    <div class="metric-card critical">
        <div class="metric-title">Critical SOS Incidents</div>
        <div class="metric-value" style="color: var(--accent-red);"><?php echo number_format($metrics['critical_sos']); ?></div>
        <div class="metric-footer">
            <span class="status-indicator online" style="background-color: var(--accent-red); box-shadow: 0 0 6px var(--accent-red);"></span> Action Required Instantly
        </div>
    </div>

    <div class="metric-card pending">
        <div class="metric-title">Active / Pending Cases</div>
        <div class="metric-value" style="color: var(--accent-orange);"><?php echo number_format($metrics['pending_sos']); ?></div>
        <div class="metric-footer">
            <span class="trend-up">➜ In-Progress</span> Coordinating Rescue Actions
        </div>
    </div>
</section>

<!-- Dashboard Main Grid (Map & Active SOS vs Diagnostics Sidebar) -->
<section class="dashboard-grid">
    
    <!-- LEFT COLUMN: Live Map Coordinates + Active Emergencies list -->
    <div style="display:flex; flex-direction:column; gap:24px;">
        
        <!-- Live Map Coordinates -->
        <div class="panel" style="padding: 16px;">
            <div class="panel-title" style="margin-bottom: 12px; padding-bottom: 8px;">
                <span>📍 Live Geographic Coordination Center</span>
                <span style="font-size: 11px; color: var(--text-secondary);">Plotting Leaflet GPS Locations</span>
            </div>
            <div id="map" class="map-container" style="height: 380px;"></div>
        </div>

        <!-- Active SOS Alerts Feed -->
        <div class="panel">
            <div class="panel-title">
                <span>🚨 Active Emergency alerts feed</span>
                <a href="sos.php" class="panel-action">Manage SOS Hub &gt;</a>
            </div>
            <div class="sos-stream" style="max-height: 400px; overflow-y: auto; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding-right: 4px;">
                <?php if (empty($activeSosAlerts)): ?>
                    <div style="grid-column: span 2; text-align: center; color: var(--text-muted); padding: 60px 0;">
                        <span style="font-size: 32px; display: block; margin-bottom: 8px;">🟢</span>
                        No active emergencies logged. System clear.
                    </div>
                <?php else: ?>
                    <?php foreach ($activeSosAlerts as $item): ?>
                        <div class="sos-card" onclick="location.href='sos.php?id=<?php echo $item['id']; ?>'" style="display:flex; flex-direction:column; justify-content:space-between; height: 160px;">
                            <div>
                                <div class="sos-card-header">
                                    <span class="sos-card-id" style="color: var(--accent-red); font-weight:700;">SOS-<?php echo $item['id']; ?></span>
                                    <span class="priority-tag <?php echo strtolower($item['priority']); ?>"><?php echo htmlspecialchars($item['priority']); ?></span>
                                </div>
                                <div class="sos-card-title" style="font-size: 13.5px; margin-top:4px;"><?php echo htmlspecialchars($item['sender_phone']); ?></div>
                                <div style="font-size: 12px; color: var(--text-primary); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.4;">
                                    "<?php echo htmlspecialchars($item['message_body'] ?? 'No messages.'); ?>"
                                </div>
                            </div>
                            <div>
                                <div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.03); padding-top: 8px;">
                                    <span class="status-tag critical" style="font-size: 8px; padding: 2px 6px; text-transform:uppercase;"><?php echo htmlspecialchars($item['disaster_type']); ?></span>
                                    <span style="font-size: 11px; color: var(--text-secondary);">📍 <?php echo $item['latitude'] ? htmlspecialchars($item['latitude'] . ',' . $item['longitude']) : 'Unknown'; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Gateway status diagnostics + Operations logs -->
    <div style="display:flex; flex-direction:column; gap:24px;">
        
        <!-- Live Gateway Status Diagnostics Widget -->
        <div class="panel">
            <div class="panel-title">
                <span>📡 SMS Gateway Status</span>
                <a href="settings.php" class="panel-action">Configure &gt;</a>
            </div>
            
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; background: rgba(255,255,255,0.02); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.04);">
                <div id="dashboard-health-indicator" style="width: 14px; height: 14px; border-radius: 50%; background-color: var(--text-muted); transition: all 0.3s ease;"></div>
                <div>
                    <strong id="dashboard-health-title" style="font-size: 14px; color: var(--text-primary); text-transform: uppercase;">SYNCING...</strong>
                    <span id="dashboard-health-desc" style="display: block; font-size: 11px; color: var(--text-secondary); margin-top:2px;">Querying gateway telemetry...</span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 12.5px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 16px;">
                <div>
                    <span style="font-size: 10px; color: var(--text-muted); text-transform: uppercase; display: block; font-weight: 600;">Reachability</span>
                    <span id="dashboard-reachability" class="status-tag low" style="margin-top: 4px; font-size: 9px; padding: 2px 6px;">Syncing...</span>
                </div>
                <div>
                    <span style="font-size: 10px; color: var(--text-muted); text-transform: uppercase; display: block; font-weight: 600;">Authentication</span>
                    <span id="dashboard-authentication" class="status-tag low" style="margin-top: 4px; font-size: 9px; padding: 2px 6px;">Syncing...</span>
                </div>
            </div>
        </div>

        <!-- Operations Audit Log -->
        <div class="panel" style="flex-grow: 1;">
            <div class="panel-title">
                <span>📋 Operations Audit Log</span>
                <span style="font-size: 11px; color: var(--text-secondary);">System events log</span>
            </div>
            <div class="audit-list" style="max-height: 400px; overflow-y: auto; padding-right: 4px;">
                <?php if (empty($auditLogs)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px 0;">No system logs captured yet.</div>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <?php 
                        $class = strtolower($log['action']);
                        ?>
                        <div class="audit-item <?php echo $class; ?>" style="flex-direction:column; gap:4px; padding: 10px 12px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="color: var(--accent-blue); text-transform: uppercase; font-size: 10.5px;">
                                    [<?php echo htmlspecialchars($log['action']); ?>]
                                </strong>
                                <span class="audit-time" style="font-feature-settings: 'tnum'; font-size:9.5px;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                            </div>
                            <span style="color: var(--text-primary); font-size: 12px; line-height: 1.4;"><?php echo htmlspecialchars($log['details']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</section>

<!-- Dynamic Map Marker Export Array -->
<script>
    // Map coordinates requires lat, lng, disaster_type, priority, id, people_count, injured_count
    // Let's transform all requests to expose 'location' as lat,lng string
    window.sosAlertsData = <?php 
        $exportData = [];
        foreach ($allRequests as $req) {
            $exportData[] = [
                'id' => $req['id'],
                'priority' => $req['priority'],
                'disaster_type' => $req['disaster_type'],
                'location' => $req['latitude'] . ',' . $req['longitude'],
                'latitude' => $req['latitude'],
                'longitude' => $req['longitude'],
                'people_count' => $req['people_count'],
                'injured_count' => $req['injured_count']
            ];
        }
        echo json_encode($exportData); 
    ?>;
</script>

<!-- Load dashboard map handler -->
<script src="js/dashboard.js"></script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
