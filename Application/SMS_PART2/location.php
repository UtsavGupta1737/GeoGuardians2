<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/SmsNumber.php';

$primary = SmsNumber::getPrimary();
$sosNumber = $primary ? $primary['phone_number'] : '+918767491904';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOS Offline Location Helper</title>
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: #151d30;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-red: #ef4444;
            --accent-green: #10b981;
            --border-color: #1f2937;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            text-align: center;
        }

        h1 {
            font-size: 22px;
            margin-bottom: 8px;
            color: var(--accent-red);
            font-weight: 700;
        }

        .subtitle {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .coords-box {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .coords-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .coords-value {
            font-size: 20px;
            font-family: monospace;
            font-weight: 700;
            color: var(--text-primary);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
            margin-bottom: 12px;
            text-decoration: none;
            box-sizing: border-box;
        }

        .btn-primary {
            background: var(--accent-red);
            color: white;
        }

        .btn-primary:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: #1f2937;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #374151;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn.disabled {
            background: #374151 !important;
            color: #6b7280 !important;
            cursor: not-allowed;
            pointer-events: none;
        }

        .status {
            font-size: 12.5px;
            margin-top: 16px;
            color: var(--text-secondary);
        }

        .info-card {
            background: rgba(239, 68, 68, 0.05);
            border: 1px dashed rgba(239, 68, 68, 0.2);
            border-radius: 8px;
            padding: 12px;
            margin-top: 24px;
            font-size: 12px;
            text-align: left;
            line-height: 1.5;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🚨 SOS Location Helper</h1>
    <div class="subtitle">
        This helper operates <strong>entirely offline</strong> to find your coordinates and draft an emergency message using your phone's built-in GPS.
    </div>

    <div class="coords-box">
        <span class="coords-label" id="status-label">GPS Status</span>
        <div class="coords-value" id="coords-display">Waiting...</div>
    </div>

    <button id="btn-get-location" class="btn btn-secondary" onclick="getLocation()">
        📍 Get Current Location
    </button>

    <a id="btn-send-sms" class="btn btn-primary disabled" href="#">
        💬 Send SOS SMS
    </a>

    <div id="gps-status" class="status"></div>

    <div class="info-card">
        <strong>💡 Offline instructions:</strong>
        <p style="margin: 4px 0 0 0;">
            1. Open and bookmark this page (or add to your home screen) while you have internet.<br>
            2. In an emergency, open the saved page (it works offline).<br>
            3. Tap "Get Current Location", then click "Send SOS SMS" to dispatch your coordinates.
        </p>
    </div>
</div>

<script>
    let currentLat = null;
    let currentLng = null;
    const sosNumber = "<?php echo htmlspecialchars($sosNumber); ?>";

    function getLocation() {
        const statusDisplay = document.getElementById('gps-status');
        const coordsDisplay = document.getElementById('coords-display');
        const statusLabel = document.getElementById('status-label');
        const btnSend = document.getElementById('btn-send-sms');

        if (!navigator.geolocation) {
            statusDisplay.innerHTML = "❌ Geolocation is not supported by your browser.";
            return;
        }

        statusDisplay.innerHTML = "📡 Querying GPS Satellites...";
        statusLabel.innerText = "Querying...";
        
        navigator.geolocation.getCurrentPosition(
            // Success Callback
            (position) => {
                currentLat = position.coords.latitude.toFixed(6);
                currentLng = position.coords.longitude.toFixed(6);

                coordsDisplay.innerText = `${currentLat}, ${currentLng}`;
                statusLabel.innerText = "Location Found";
                statusDisplay.innerHTML = "🟢 Coordinates successfully retrieved offline!";

                // Build standard SOS payload: SOS|[DISASTER_TYPE]|[LOCATION]|[PEOPLE]|[INJURED]|[HELP_REQUIRED]|[PRIORITY]
                const smsBody = `SOS|unknown|${currentLat},${currentLng}|1|0|RESCUE|MEDIUM`;
                
                // Detect OS to use correct SMS URL syntax
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                const smsUrl = isIOS 
                    ? `sms:${sosNumber}&body=${encodeURIComponent(smsBody)}` 
                    : `sms:${sosNumber}?body=${encodeURIComponent(smsBody)}`;

                btnSend.href = smsUrl;
                btnSend.classList.remove('disabled');
            },
            // Error Callback
            (error) => {
                statusLabel.innerText = "GPS Error";
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        statusDisplay.innerHTML = "❌ GPS permission denied. Please enable location permissions.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        statusDisplay.innerHTML = "❌ Location unavailable. Try standing near a window or outdoors.";
                        break;
                    case error.TIMEOUT:
                        statusDisplay.innerHTML = "❌ Location request timed out. Please try again.";
                        break;
                    default:
                        statusDisplay.innerHTML = "❌ Error: " + error.message;
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }
</script>

</body>
</html>
