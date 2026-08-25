/**
 * js/esp32_global_serial.js
 * Persistent Global Web Serial Background Service for DisasterSafe
 * Automatically maintains ESP32 USB connection across all page navigations
 * and ingests incoming SOS alerts into the SQLite/MySQL databases in real time.
 */

window.DisasterSafeSerial = (function() {
    let activePort = null;
    let serialReader = null;
    let keepReading = false;
    let isConnecting = false;

    // Detect base API URL dynamically
    function getApiUrl() {
        const path = window.location.pathname;
        if (path.includes('/DisasterSafe/')) {
            return window.location.origin + '/DisasterSafe/api/esp32_sos_ingest.php';
        }
        return window.location.origin + '/api/esp32_sos_ingest.php';
    }

    // Play subtle emergency notification chime
    function playChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch(e){}
    }

    // Update global navbar pill & settings UI
    function updateUI(isConnected, portInfo = '') {
        const navBtn = document.getElementById('globalEsp32NavBtn');
        const navDot = document.getElementById('globalEsp32Dot');
        const navText = document.getElementById('globalEsp32Text');

        if (navDot && navText) {
            if (isConnected) {
                navDot.className = "w-2 h-2 rounded-full bg-emerald-500 animate-pulse";
                navText.innerText = "ESP32: Live (115200)";
                if (navBtn) navBtn.className = "flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-xs font-bold text-emerald-800 transition-all shadow-xs cursor-pointer";
            } else {
                navDot.className = "w-2 h-2 rounded-full bg-slate-400";
                navText.innerText = "🔌 ESP32: Connect";
                if (navBtn) navBtn.className = "flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-bold text-slate-700 transition-all shadow-xs cursor-pointer";
            }
        }

        // Notify settings page if active
        if (typeof window.updateSettingsSerialUI === 'function') {
            window.updateSettingsSerialUI(isConnected, portInfo);
        }
    }

    // Auto-connect to previously authorized port on page load
    async function autoConnect() {
        if (!('serial' in navigator)) return;

        try {
            const ports = await navigator.serial.getPorts();
            if (ports.length > 0 && localStorage.getItem('esp32_serial_autostart') === 'true') {
                console.log("[ESP32 SERIAL] Found authorized port, auto-connecting in background...");
                await startConnection(ports[0]);
            } else {
                updateUI(false);
            }
        } catch (e) {
            console.warn("[ESP32 SERIAL] Auto-connect check:", e.message);
            updateUI(false);
        }
    }

    // Start connection to a port
    async function startConnection(port) {
        if (isConnecting) return;
        isConnecting = true;

        try {
            activePort = port;
            await activePort.open({ baudRate: 115200 });
            keepReading = true;
            localStorage.setItem('esp32_serial_autostart', 'true');
            updateUI(true);
            console.log("[ESP32 SERIAL] Connected successfully to USB Serial at 115200 baud.");

            if (typeof window.logSettingsTerminal === 'function') {
                window.logSettingsTerminal('[CONNECTED] Background Web Serial listening on 115200 baud.', 'emerald');
            }

            readLoop();
        } catch (err) {
            console.error("[ESP32 SERIAL] Open error:", err);
            activePort = null;
            keepReading = false;
            updateUI(false);
            if (typeof window.logSettingsTerminal === 'function') {
                window.logSettingsTerminal('[ERROR] Connection failed: ' + err.message, 'red');
            }
        } finally {
            isConnecting = false;
        }
    }

    // Disconnect port
    async function disconnect() {
        keepReading = false;
        localStorage.setItem('esp32_serial_autostart', 'false');

        if (serialReader) {
            try { await serialReader.cancel(); } catch(e){}
        }
        if (activePort) {
            try { await activePort.close(); } catch(e){}
        }
        activePort = null;
        updateUI(false);
        console.log("[ESP32 SERIAL] Disconnected.");

        if (typeof window.logSettingsTerminal === 'function') {
            window.logSettingsTerminal('[DISCONNECTED] Serial port closed.', 'yellow');
        }
    }

    // Toggle connection prompt
    async function toggleConnection() {
        if (!('serial' in navigator)) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Web Serial Not Supported', 'Web Serial API is available in Google Chrome, Microsoft Edge, or Opera.', 'warning');
            } else {
                alert('Web Serial API requires Chrome or Edge browser.');
            }
            return;
        }

        if (activePort) {
            await disconnect();
        } else {
            try {
                const port = await navigator.serial.requestPort();
                await startConnection(port);
            } catch (err) {
                console.warn("[ESP32 SERIAL] Port request cancelled or failed:", err);
                updateUI(false);
            }
        }
    }

    // Background Read Loop
    async function readLoop() {
        const textDecoder = new TextDecoderStream();
        const readableStreamClosed = activePort.readable.pipeTo(textDecoder.writable);
        serialReader = textDecoder.readable.getReader();

        let buffer = "";
        let inSosBlock = false;
        let sosLines = [];

        try {
            while (keepReading) {
                const { value, done } = await serialReader.read();
                if (done) break;
                if (value) {
                    buffer += value;
                    let lines = buffer.split("\n");
                    buffer = lines.pop();

                    for (let line of lines) {
                        line = line.trim();
                        if (!line) continue;

                        if (typeof window.logSettingsTerminal === 'function') {
                            let preview = line.length > 90 ? line.substring(0, 50) + '... [TRUNCATED] ...' : line;
                            window.logSettingsTerminal(`RAW: ${preview}`, 'dim');
                        }

                        if (line === "---SOS_START---") {
                            inSosBlock = true;
                            sosLines = [];
                            if (typeof window.logSettingsTerminal === 'function') {
                                window.logSettingsTerminal('🚨 [FRAME] ---SOS_START--- Received', 'yellow');
                            }
                            continue;
                        } else if (line === "---SOS_END---") {
                            inSosBlock = false;
                            if (typeof window.logSettingsTerminal === 'function') {
                                window.logSettingsTerminal('📦 [FRAME] ---SOS_END---. Ingesting alert...', 'indigo');
                            }
                            try {
                                const payload = JSON.parse(sosLines.join(""));
                                processAndIngestAlert(payload);
                            } catch (e) {
                                console.error("[ESP32 SERIAL] JSON Parse Error:", e);
                            }
                            sosLines = [];
                            continue;
                        }

                        if (inSosBlock) {
                            sosLines.push(line);
                        } else if (line.startsWith("{") && line.endsWith("}")) {
                            try {
                                const payload = JSON.parse(line);
                                if (payload.sender_name || payload.victim_name) {
                                    processAndIngestAlert(payload);
                                }
                            } catch(e){}
                        }
                    }
                }
            }
        } catch (e) {
            console.warn("[ESP32 SERIAL] Read loop exited:", e.message);
        } finally {
            if (serialReader) {
                serialReader.releaseLock();
            }
        }
    }

    // Process and send to PHP backend
    async function processAndIngestAlert(data) {
        console.log("[ESP32 SERIAL] Incoming Alert:", data);
        playChime();

        const apiUrl = getApiUrl();
        let dbId = null;

        try {
            const resp = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const resJson = await resp.json();

            if (resJson.status === 'success') {
                dbId = resJson.sos_id;
                console.log(`[ESP32 SERIAL] Ingested into database successfully as SOS #${dbId}`);
                if (typeof window.logSettingsTerminal === 'function') {
                    window.logSettingsTerminal(`✔ Saved to Database as SOS #${dbId}!`, 'emerald');
                }
            } else {
                console.warn("[ESP32 SERIAL] Ingest response warning:", resJson.message);
                if (typeof window.logSettingsTerminal === 'function') {
                    window.logSettingsTerminal(`[INGEST WARNING] ${resJson.message}`, 'yellow');
                }
            }
        } catch (err) {
            console.error("[ESP32 SERIAL] Ingest error:", err);
            if (typeof window.logSettingsTerminal === 'function') {
                window.logSettingsTerminal(`[INGEST ERROR] ${err.message}`, 'red');
            }
        }

        // Fire custom window event for any page to react (e.g. settings or map)
        window.dispatchEvent(new CustomEvent('esp32_sos_received', {
            detail: { data: data, dbId: dbId }
        }));

        // Show SweetAlert2 popup banner on whatever page the user is currently on
        const vName = data.sender_name || data.victim_name || 'Emergency Citizen';
        const eType = data.emergency_type || 'General Emergency';
        const lat = parseFloat(data.gps_lat || data.latitude) || 28.6139;
        const lng = parseFloat(data.gps_lng || data.longitude) || 77.2090;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '🚨 ESP32 SOS ALERT RECEIVED!',
                html: `
                    <div style="text-align:left; font-size:13px; line-height:1.6; color:#1e293b;">
                        <b>Victim:</b> <span style="font-size:14px; font-weight:800; color:#0f172a;">${vName}</span><br/>
                        <b>Emergency:</b> <span style="color:#dc2626; font-weight:700;">${eType}</span><br/>
                        <b>GPS:</b> <span style="font-family:monospace;">${lat.toFixed(4)}°, ${lng.toFixed(4)}°</span><br/>
                        <b>Status:</b> <span style="color:#16a34a; font-weight:800;">✔ Added to Database ${dbId ? '(ID #' + dbId + ')' : ''}</span>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Open SOS Hub →',
                cancelButtonText: 'Dismiss',
                confirmButtonColor: '#dc2626',
                timer: 10000,
                timerProgressBar: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'sos.php' + (dbId ? '?id=' + dbId : '');
                }
            });
        }
    }

    // Initialize auto-connect on document load
    document.addEventListener('DOMContentLoaded', () => {
        autoConnect();
    });

    return {
        toggleConnection: toggleConnection,
        isConnected: () => Boolean(activePort),
        injectTestAlert: (sample) => processAndIngestAlert(sample)
    };
})();

// Global helper for navbar button click
function toggleGlobalSerial() {
    if (window.DisasterSafeSerial) {
        window.DisasterSafeSerial.toggleConnection();
    }
}
