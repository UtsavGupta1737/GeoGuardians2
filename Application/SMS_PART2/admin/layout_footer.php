<?php
/**
 * Master UI Layout Footer
 */
?>
            </div>
        </main>
    </div>
    
    <!-- Sync System Clock -->
    <script>
        function updateClock() {
            const display = document.getElementById('clock-display');
            if (display) {
                const now = new Date();
                display.textContent = now.toTimeString().split(' ')[0];
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Background Outbox Queue Worker (Fires every 12 seconds to clear buffered alerts)
        function triggerQueueWorker() {
            fetch('../api/sms/cron.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.processed_count > 0) {
                        console.log('Outbox Queue processed: ' + data.processed_count + ' items');
                    }
                })
                .catch(err => console.warn('Queue worker poll communication failed:', err));
        }
        setInterval(triggerQueueWorker, 12000);
        // Initial delayed trigger
        setTimeout(triggerQueueWorker, 2000);

        // Background Telemetry Heartbeat & Connectivity Poller (Fires every 8 seconds)
        function updateGatewayTelemetry() {
            fetch('../api/sms/health.php')
                .then(res => res.json())
                .then(data => {
                    const card = document.getElementById('sidebar-gateway-card');
                    const indicator = document.getElementById('sidebar-indicator');
                    const title = document.getElementById('sidebar-status-title');
                    const desc = document.getElementById('sidebar-status-desc');
                    
                    if (!card || !indicator || !title || !desc) return;
                    
                    let color = '#53647c'; 
                    let pulse = false;
                    let titleText = 'OFFLINE';
                    let descText = 'Unreachable';
                    
                    if (data.status === 'ONLINE') {
                        color = '#39d353'; 
                        pulse = true;
                        titleText = 'GATEWAY ONLINE';
                        descText = 'Heartbeat: ' + data.telemetry.last_seen_readable;
                    } else if (data.status === 'ONLINE_NO_EVENTS') {
                        color = '#00f0ff'; 
                        titleText = 'GATEWAY ACTIVE';
                        descText = 'Last event: ' + (data.telemetry.last_event ? data.telemetry.last_event : 'none');
                    } else if (data.status === 'CONNECTION_ISSUE') {
                        color = '#ff9900'; 
                        pulse = true;
                        titleText = 'CONN WEAK';
                        descText = 'Last seen: ' + data.telemetry.last_seen_readable;
                    } else if (data.status === 'AUTH_FAILED') {
                        color = '#ff4c4c'; 
                        pulse = true;
                        titleText = 'AUTH INVALID';
                        descText = 'Credentials rejected';
                    } else {
                        color = '#ff4c4c'; 
                        titleText = 'GATEWAY OFFLINE';
                        descText = 'Last seen: ' + data.telemetry.last_seen_readable;
                    }
                    
                    indicator.style.backgroundColor = color;
                    if (pulse) {
                        indicator.style.boxShadow = '0 0 10px ' + color;
                        indicator.style.animation = 'pulse 1.5s infinite ease-in-out';
                    } else {
                        indicator.style.boxShadow = 'none';
                        indicator.style.animation = 'none';
                    }
                    
                    title.textContent = titleText;
                    desc.textContent = descText;
                    
                    // Update settings elements dynamically if operator is on settings panel
                    const settingsReach = document.getElementById('settings-reachability');
                    const settingsAuth = document.getElementById('settings-authentication');
                    const settingsEvent = document.getElementById('settings-last-event');
                    const settingsSeen = document.getElementById('settings-last-seen');
                    
                    if (settingsReach && settingsAuth && settingsEvent && settingsSeen) {
                        settingsReach.textContent = data.reachability;
                        settingsReach.className = 'status-tag ' + (data.reachability === 'SUCCESS' ? 'resolved' : 'new');
                        
                        settingsAuth.textContent = data.authentication;
                        settingsAuth.className = 'status-tag ' + (data.authentication.indexOf('PASSED') !== -1 ? 'resolved' : (data.authentication === 'FAILED' ? 'new' : 'low'));
                        
                        settingsEvent.textContent = data.telemetry.last_event ? data.telemetry.last_event.toUpperCase() : 'NONE';
                        settingsSeen.textContent = data.telemetry.last_seen_readable;
                    }
                })
                .catch(err => console.warn('Telemetry update failed:', err));
        }
        setInterval(updateGatewayTelemetry, 8000);
        setTimeout(updateGatewayTelemetry, 500);
    </script>
</body>
</html>
