/**
 * js/sms_sos_listener.js
 * Persistent Global Real-Time SMS SOS Background Listener for DisasterSafe
 * Automatically polls for new incoming SMS emergency alerts and triggers an instant SweetAlert2 popup & chime.
 */

window.DisasterSafeSmsListener = (function() {
    let lastSeenId = 0;
    let isPolling = false;
    let pollInterval = null;

    // Detect base API URL dynamically
    function getApiUrl() {
        const path = window.location.pathname;
        if (path.includes('/DisasterSafe/')) {
            return window.location.origin + '/DisasterSafe/api/poll_sms_alerts.php';
        }
        return window.location.origin + '/api/poll_sms_alerts.php';
    }

    // Play subtle emergency notification chime
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(950, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(520, ctx.currentTime + 0.35);
            gain.gain.setValueAtTime(0.35, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch(e) {}
    }

    // Display SweetAlert2 Emergency Banner for SMS Alert
    function showSmsPopup(alert) {
        playChime();

        const vName = alert.sender_name || 'Emergency Citizen';
        const phone = alert.sender_phone || 'Cellular SMS';
        const eType = alert.emergency_type || 'General Emergency';
        const persons = alert.persons_count || 1;
        const lat = parseFloat(alert.gps_lat) || 28.6139;
        const lng = parseFloat(alert.gps_lng) || 77.2090;
        const msg = alert.message || 'Distress signal received via cellular SMS.';
        const sosId = alert.id;
        const priority = alert.priority || 'Critical';

        // Fire custom window event for dashboard map and SOS feed to react dynamically
        window.dispatchEvent(new CustomEvent('sms_sos_received', {
            detail: { data: alert, dbId: sosId }
        }));

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '📱 CELLULAR SMS SOS ALERT!',
                html: `
                    <div style="text-align:left; font-size:13px; line-height:1.6; color:#1e293b;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                            <span style="font-family:monospace; font-weight:900; background:#0f172a; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px;">
                                SOS #${sosId}
                            </span>
                            <span style="font-weight:800; background:#fee2e2; color:#dc2626; padding:2px 6px; border-radius:4px; font-size:10px; text-transform:uppercase;">
                                ${priority}
                            </span>
                        </div>
                        <b>Victim:</b> <span style="font-size:14px; font-weight:800; color:#0f172a;">${vName}</span><br/>
                        <b>Phone:</b> <a href="tel:${phone}" style="font-family:monospace; color:#2563eb; font-weight:700; text-decoration:none;">📞 ${phone}</a><br/>
                        <b>Emergency:</b> <span style="color:#dc2626; font-weight:700;">${eType}</span> (${persons} Affected)<br/>
                        <b>GPS:</b> <span style="font-family:monospace; color:#475569;">${lat.toFixed(4)}, ${lng.toFixed(4)}</span><br/>
                        <b>Distress:</b> <span style="font-style:italic; color:#475569;">"${msg}"</span><br/>
                        <div style="margin-top:6px; padding:4px 6px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; font-size:11px; color:#166534; font-weight:700;">
                            ✓ Ingested to Database &amp; Plotted on Tactical GIS Map
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Open SOS Hub & Chat →',
                cancelButtonText: 'Dismiss',
                confirmButtonColor: '#dc2626',
                timer: 15000,
                timerProgressBar: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'sos.php?id=' + sosId;
                }
            });
        }
    }

    // Poll for new SMS alerts
    async function checkNewAlerts() {
        if (isPolling) return;
        isPolling = true;

        try {
            const url = getApiUrl() + '?last_id=' + lastSeenId;
            const resp = await fetch(url);
            const data = await resp.json();

            if (data.success) {
                if (lastSeenId === 0) {
                    // Initial load: initialize lastSeenId without firing alerts for past records
                    lastSeenId = data.latest_id || 0;
                } else if (data.has_new && data.alert) {
                    lastSeenId = data.latest_id;
                    showSmsPopup(data.alert);
                }
            }
        } catch (e) {
            // Silently fail network errors during polling
        } finally {
            isPolling = false;
        }
    }

    // Initialize listener on page load
    function init() {
        checkNewAlerts();
        pollInterval = setInterval(checkNewAlerts, 2500);
    }

    document.addEventListener('DOMContentLoaded', () => {
        init();
    });

    return {
        checkNow: checkNewAlerts,
        showPopup: (alert) => showSmsPopup(alert),
        injectTestAlert: function() {
            showSmsPopup({
                id: Math.floor(Math.random() * 900) + 100,
                sender_name: "Aarti Sharma (Simulated SMS)",
                sender_phone: "+91 98765 43210",
                emergency_type: "Flood / Rising Waters",
                priority: "Critical",
                gps_lat: 28.6280,
                gps_lng: 77.2650,
                persons_count: 4,
                message: "Water entering 2nd floor near Yamuna Bank, need immediate rescue boat."
            });
        }
    };
})();

// Global helper to trigger test SMS popup from console or buttons
function injectTestSmsAlert() {
    if (window.DisasterSafeSmsListener) {
        window.DisasterSafeSmsListener.injectTestAlert();
    }
}
