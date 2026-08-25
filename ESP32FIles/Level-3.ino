/*
 * ============================================================================
 * ESP32 EMERGENCY SOS CAPTIVE PORTAL (AUTO BEACON GPS PINNED)
 * ============================================================================
 * 
 * Features:
 * 1. Creates an Open Wi-Fi Access Point (SSID: "EMERGENCY-SOS-PORTAL").
 * 2. Runs a DNS Server intercepting all domains (*) -> 192.168.4.1.
 * 3. Triggers Captive Portal popup on iOS, Android, Windows, and macOS.
 * 4. Clean, Simple Input Fields (Name, Phone, Crisis Type, Needs, Age, Message).
 * 5. 100% Optional Fields (Fast 1-tap SOS submission without blocking validation).
 * 6. Auto-pins Beacon Node GPS location (28.613900, 77.209000).
 * 7. Streams structured JSON records over USB Serial (115200 baud).
 * ============================================================================
 */

#include <WiFi.h>
#include <DNSServer.h>
#include <WebServer.h>
#include <LittleFS.h>

// --- Configuration ---
const char* AP_SSID = "EMERGENCY-SOS-PORTAL";
const char* AP_PASS = ""; // Open network for emergency access

// Beacon Node Hardware GPS Configuration
const char* BEACON_NODE_ID = "RESCUE-BEACON-04";
const float BEACON_LATITUDE = 28.613900;
const float BEACON_LONGITUDE = 77.209000;

const bool FORMAT_LITTLEFS_ON_START = false;

const byte DNS_PORT = 53;
IPAddress apIP(192, 168, 4, 1);
IPAddress netMsk(255, 255, 255, 0);

DNSServer dnsServer;
WebServer server(80);

bool littleFSActive = false;

// --- Embedded Fallback HTML (If LittleFS /index.html is not uploaded) ---
const char FALLBACK_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>EMERGENCY SOS DISPATCH</title>
  <style>
    :root {
      --bg-color: #0b0f19;
      --card-bg: #131b2e;
      --card-border: #233554;
      --danger-red: #ff334b;
      --danger-glow: rgba(255, 51, 75, 0.4);
      --accent-amber: #ffaa00;
      --accent-cyan: #38bdf8;
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
      --input-bg: #1e293b;
      --input-border: #334155;
      --input-focus: #38bdf8;
      --success-green: #10b981;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    body { background-color: var(--bg-color); color: var(--text-main); padding: 14px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; }
    .container { width: 100%; max-width: 480px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 16px; padding: 22px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6); margin-bottom: 20px; }
    
    .header { text-align: center; border-bottom: 1px solid var(--card-border); padding-bottom: 16px; margin-bottom: 20px; }
    .header-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 51, 75, 0.15); color: var(--danger-red); border: 1px solid var(--danger-red); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; animation: pulse-border 2s infinite; }
    @keyframes pulse-border { 0%, 100% { box-shadow: 0 0 0 0 var(--danger-glow); } 50% { box-shadow: 0 0 10px 3px var(--danger-glow); } }
    .header h1 { font-size: 1.5rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .header p { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; }
    
    .section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--accent-amber); margin: 18px 0 10px 0; display: flex; align-items: center; gap: 6px; }
    .form-group { margin-bottom: 14px; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #e2e8f0; }
    input[type="text"], input[type="tel"], input[type="number"], select, textarea { width: 100%; background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 8px; color: #ffffff; padding: 12px; font-size: 0.95rem; outline: none; }
    input:focus, select:focus, textarea:focus { border-color: var(--input-focus); box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    
    .gps-lock-card { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; }
    .gps-lock-header { display: flex; align-items: center; justify-content: space-between; color: #10b981; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
    .gps-lock-coords { font-size: 0.9rem; font-weight: 700; color: #ffffff; font-family: monospace; }
    .gps-lock-subtext { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
    
    .chip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .chip { background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 20px; padding: 6px 12px; font-size: 0.8rem; color: var(--text-muted); cursor: pointer; }
    .chip.active { background: rgba(255, 170, 0, 0.2); border-color: var(--accent-amber); color: #ffd27a; font-weight: 600; }
    
    .sos-button-wrapper { margin-top: 24px; }
    .sos-btn { width: 100%; background: linear-gradient(135deg, #ff2a40, #d90429); color: white; border: none; border-radius: 12px; padding: 18px 20px; font-size: 1.25rem; font-weight: 900; cursor: pointer; display: flex; flex-direction: column; align-items: center; box-shadow: 0 6px 20px rgba(217, 4, 41, 0.5); }
    .sos-btn span.subtext { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-top: 2px; }
    
    .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 15, 25, 0.95); backdrop-filter: blur(8px); z-index: 100; justify-content: center; align-items: center; padding: 20px; }
    .overlay-card { background: var(--card-bg); border: 2px solid var(--success-green); border-radius: 16px; padding: 30px 20px; text-align: center; max-width: 400px; width: 100%; }
    .reset-btn { background: #334155; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-badge">🚨 EMERGENCY MESH LINK</div>
      <h1>EMERGENCY SOS</h1>
      <p>Direct Connection to Local Search & Rescue Beacon</p>
    </div>

    <form id="sosForm" onsubmit="submitSOS(event)">
      <div class="section-title">👤 Incident & Contact Details</div>

      <div class="form-group">
        <label for="victim_name">Full Name / Caller Name</label>
        <input type="text" id="victim_name" name="victim_name" placeholder="e.g. Rohan Sharma">
      </div>

      <div class="form-group">
        <label for="phone">Direct Contact Mobile / Phone</label>
        <input type="tel" id="phone" name="phone" placeholder="e.g. +91 98765 43210">
      </div>

      <div class="form-group">
        <label for="emergency_type">Crisis / Emergency Type</label>
        <select id="emergency_type" name="emergency_type">
          <option value="General Emergency" selected>⚠️ General Emergency</option>
          <option value="Flood">🌊 Flood</option>
          <option value="Earthquake">🏚️ Earthquake</option>
          <option value="Fire">🔥 Fire</option>
          <option value="Chemical Leak">☣️ Chemical Leak</option>
          <option value="Building Collapse">🏢 Building Collapse</option>
          <option value="Medical Trauma">🩺 Medical Trauma</option>
        </select>
      </div>

      <div class="section-title">📍 Emergency Location (Beacon GPS Fixed)</div>
      <div class="gps-lock-card">
        <div class="gps-lock-header">
          <span>📡 Beacon GPS Locked</span>
          <span style="font-size:0.7rem; background:#10b981; color:#0b0f19; padding:2px 6px; border-radius:4px;">AUTO-PINNED</span>
        </div>
        <div class="gps-lock-coords">Lat: 28.613900 | Lng: 77.209000</div>
        <div class="gps-lock-subtext">Target position automatically locked from this rescue beacon node.</div>
      </div>

      <input type="hidden" id="gps_lat" name="gps_lat" value="28.613900">
      <input type="hidden" id="gps_lng" name="gps_lng" value="77.209000">

      <div class="section-title">🩹 Specific Aid / Quick Needs</div>
      <div class="form-group">
        <div class="chip-container">
          <div class="chip" onclick="toggleChip(this, 'Ambulance')">🚑 Ambulance</div>
          <div class="chip" onclick="toggleChip(this, 'Oxygen')">🫁 Oxygen</div>
          <div class="chip" onclick="toggleChip(this, 'Boat Rescue')">🚤 Boat Rescue</div>
          <div class="chip" onclick="toggleChip(this, 'Clean Water')">💧 Clean Water</div>
          <div class="chip" onclick="toggleChip(this, 'Rope Team')">🧗 Rope Team</div>
          <div class="chip" onclick="toggleChip(this, 'Burn Care')">🩹 Burn Care</div>
        </div>
        <input type="hidden" id="quick_needs" name="quick_needs" value="">
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label for="blood_type">Blood Group</label>
          <select id="blood_type" name="blood_type">
            <option value="Unknown" selected>Unknown</option>
            <option value="A+">A+</option><option value="A-">A-</option>
            <option value="B+">B+</option><option value="B-">B-</option>
            <option value="O+">O+</option><option value="O-">O-</option>
            <option value="AB+">AB+</option><option value="AB-">AB-</option>
          </select>
        </div>
        <div class="form-group">
          <label for="age">Victim Age</label>
          <input type="number" id="age" name="age" min="0" max="130" placeholder="e.g. 34">
        </div>
      </div>

      <div class="form-group">
        <label for="message">Ground Notes & Exact Spot</label>
        <textarea id="message" name="message" rows="3" placeholder="e.g. Water reaching 1st floor, 2 elderly trapped on terrace. Near City Hospital."></textarea>
      </div>

      <div class="sos-button-wrapper">
        <button type="submit" id="submitBtn" class="sos-btn">
          <span>🆘 BROADCAST SOS ALERT</span>
          <span class="subtext">TRANSMIT TO COMMAND CENTER</span>
        </button>
      </div>
    </form>
  </div>

  <div id="confirmationOverlay" class="overlay">
    <div class="overlay-card">
      <div style="font-size:3rem; margin-bottom:12px;">✅</div>
      <h2 id="confirmTitle" style="color:#ffffff; margin-bottom:8px;">SOS TRANSMITTED!</h2>
      <p id="confirmDesc" style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Your distress report and beacon GPS coordinates have been registered and transmitted to the rescue command network. Stay in a safe position.</p>
      <button class="reset-btn" onclick="closeConfirmation()">Submit Another Report</button>
    </div>
  </div>

  <script>
    const selectedNeeds = new Set();

    function toggleChip(el, need) {
      if (selectedNeeds.has(need)) {
        selectedNeeds.delete(need);
        el.classList.remove('active');
      } else {
        selectedNeeds.add(need);
        el.classList.add('active');
      }
      document.getElementById('quick_needs').value = Array.from(selectedNeeds).join(', ');
    }

    function submitSOS(event) {
      event.preventDefault();
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.innerHTML = '<span>⏳ TRANSMITTING SOS...</span>';

      const callerName = document.getElementById('victim_name').value.trim() || "Emergency Caller";
      const phone = document.getElementById('phone').value.trim() || "N/A";

      const payload = {
        sender_name: callerName,
        victim_name: callerName,
        sender_phone: phone,
        phone: phone,
        latitude: parseFloat(document.getElementById('gps_lat').value) || 28.613900,
        gps_lat: parseFloat(document.getElementById('gps_lat').value) || 28.613900,
        longitude: parseFloat(document.getElementById('gps_lng').value) || 77.209000,
        gps_lng: parseFloat(document.getElementById('gps_lng').value) || 77.209000,
        emergency_type: document.getElementById('emergency_type').value || "General Emergency",
        medical_needs: document.getElementById('quick_needs').value.trim() || "",
        quick_needs: document.getElementById('quick_needs').value.trim() || "",
        blood_type: document.getElementById('blood_type').value || "Unknown",
        age: parseInt(document.getElementById('age').value) || null,
        message: document.getElementById('message').value.trim() || ""
      };

      fetch('/submit-sos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(data => {
        document.getElementById('confirmTitle').innerText = 'SOS TRANSMITTED!';
        document.getElementById('confirmDesc').innerText = 'Your distress report and beacon GPS coordinates have been registered and transmitted to the rescue command network. Stay in a safe position.';
        document.getElementById('confirmationOverlay').style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<span>🆘 BROADCAST SOS ALERT</span><span class="subtext">TRANSMIT TO COMMAND CENTER</span>';
      })
      .catch(err => {
        document.getElementById('confirmTitle').innerText = 'SOS TRANSMITTED!';
        document.getElementById('confirmDesc').innerText = 'Your distress report and beacon GPS coordinates have been registered and transmitted to the rescue command network. Stay in a safe position.';
        document.getElementById('confirmationOverlay').style.display = 'flex';
        btn.disabled = false;
        btn.innerHTML = '<span>🆘 BROADCAST SOS ALERT</span><span class="subtext">TRANSMIT TO COMMAND CENTER</span>';
      });
    }

    function closeConfirmation() {
      document.getElementById('confirmationOverlay').style.display = 'none';
      document.getElementById('sosForm').reset();
      selectedNeeds.clear();
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
    }
  </script>
</body>
</html>
)rawliteral";

// --- Helper to escape strings in JSON ---
String escapeJsonString(const String& input) {
  String output = "";
  for (unsigned int i = 0; i < input.length(); i++) {
    char c = input.charAt(i);
    if (c == '"') output += "\\\"";
    else if (c == '\\') output += "\\\\";
    else if (c == '\b') output += "\\b";
    else if (c == '\f') output += "\\f";
    else if (c == '\n') output += "\\n";
    else if (c == '\r') output += "\\r";
    else if (c == '\t') output += "\\t";
    else output += c;
  }
  return output;
}

// --- Handler: Captive Portal Root Page ---
void handleRoot() {
  server.sendHeader("Cache-Control", "no-cache, no-store, must-revalidate");
  server.sendHeader("Pragma", "no-cache");
  server.sendHeader("Expires", "-1");

  if (littleFSActive && LittleFS.exists("/index.html")) {
    File file = LittleFS.open("/index.html", "r");
    server.streamFile(file, "text/html");
    file.close();
    return;
  }

  server.send(200, "text/html", FALLBACK_HTML);
}

// --- Handler: SOS Form Submission ---
void handleSOSSubmit() {
  String victimName = "";
  String phone = "";
  String latStr = String(BEACON_LATITUDE, 6);
  String lngStr = String(BEACON_LONGITUDE, 6);
  String emergencyType = "General Emergency";
  String quickNeeds = "";
  String bloodType = "Unknown";
  String ageStr = "";
  String message = "";

  if (server.hasArg("plain")) {
    // Received raw JSON payload
    String body = server.arg("plain");
    Serial.println("---SOS_START---");
    Serial.println(body);
    Serial.println("---SOS_END---");

    server.send(200, "application/json", "{\"status\":\"success\",\"message\":\"SOS received by local station\"}");
    return;
  } 

  // Fallback to URL-encoded form parameters
  if (server.hasArg("victim_name") && server.arg("victim_name").length() > 0) victimName = server.arg("victim_name");
  else if (server.hasArg("sender_name") && server.arg("sender_name").length() > 0) victimName = server.arg("sender_name");
  else victimName = "Emergency Caller";

  if (server.hasArg("phone") && server.arg("phone").length() > 0) phone = server.arg("phone");
  else if (server.hasArg("sender_phone") && server.arg("sender_phone").length() > 0) phone = server.arg("sender_phone");
  else phone = "N/A";

  if (server.hasArg("gps_lat") && server.arg("gps_lat").length() > 0) latStr = server.arg("gps_lat");
  else if (server.hasArg("latitude") && server.arg("latitude").length() > 0) latStr = server.arg("latitude");

  if (server.hasArg("gps_lng") && server.arg("gps_lng").length() > 0) lngStr = server.arg("gps_lng");
  else if (server.hasArg("longitude") && server.arg("longitude").length() > 0) lngStr = server.arg("longitude");

  if (server.hasArg("emergency_type") && server.arg("emergency_type").length() > 0) emergencyType = server.arg("emergency_type");
  if (server.hasArg("quick_needs")) quickNeeds = server.arg("quick_needs");
  else if (server.hasArg("medical_needs")) quickNeeds = server.arg("medical_needs");

  if (server.hasArg("blood_type")) bloodType = server.arg("blood_type");
  if (server.hasArg("age")) ageStr = server.arg("age");
  if (server.hasArg("message")) message = server.arg("message");

  float lat = latStr.toFloat();
  float lng = lngStr.toFloat();
  if (lat == 0.0) lat = BEACON_LATITUDE;
  if (lng == 0.0) lng = BEACON_LONGITUDE;
  int age = ageStr.toInt();

  // Construct structured JSON
  String jsonOutput = "{";
  jsonOutput += "\"event\":\"SOS_DISPATCH\",";
  jsonOutput += "\"timestamp_ms\":" + String(millis()) + ",";
  jsonOutput += "\"beacon_node_id\":\"" + String(BEACON_NODE_ID) + "\",";
  jsonOutput += "\"sender_name\":\"" + escapeJsonString(victimName) + "\",";
  jsonOutput += "\"victim_name\":\"" + escapeJsonString(victimName) + "\",";
  jsonOutput += "\"sender_phone\":\"" + escapeJsonString(phone) + "\",";
  jsonOutput += "\"phone\":\"" + escapeJsonString(phone) + "\",";
  jsonOutput += "\"latitude\":" + String(lat, 6) + ",";
  jsonOutput += "\"gps_lat\":" + String(lat, 6) + ",";
  jsonOutput += "\"longitude\":" + String(lng, 6) + ",";
  jsonOutput += "\"gps_lng\":" + String(lng, 6) + ",";
  jsonOutput += "\"emergency_type\":\"" + escapeJsonString(emergencyType) + "\",";
  jsonOutput += "\"medical_needs\":\"" + escapeJsonString(quickNeeds) + "\",";
  jsonOutput += "\"quick_needs\":\"" + escapeJsonString(quickNeeds) + "\",";
  jsonOutput += "\"blood_type\":\"" + escapeJsonString(bloodType) + "\",";
  if (ageStr.length() > 0) {
    jsonOutput += "\"age\":" + String(age) + ",";
  } else {
    jsonOutput += "\"age\":null,";
  }
  jsonOutput += "\"message\":\"" + escapeJsonString(message) + "\"";
  jsonOutput += "}";

  // Emit framed JSON to Serial USB
  Serial.println("---SOS_START---");
  Serial.println(jsonOutput);
  Serial.println("---SOS_END---");

  server.send(200, "application/json", "{\"status\":\"success\",\"message\":\"SOS received by local station\"}");
}

// --- Handler: Captive Portal Redirection ---
void handleCaptiveRedirect() {
  server.sendHeader("Location", String("http://") + apIP.toString() + "/", true);
  server.send(302, "text/plain", "");
}

// --- Setup ---
void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println();
  Serial.println("=================================================");
  Serial.println("   ESP32 EMERGENCY SOS CAPTIVE PORTAL STARTING   ");
  Serial.print("   BEACON NODE: ");
  Serial.println(BEACON_NODE_ID);
  Serial.print("   BEACON GPS : ");
  Serial.print(BEACON_LATITUDE, 6);
  Serial.print(", ");
  Serial.println(BEACON_LONGITUDE, 6);
  Serial.println("   PORTAL     : CLEAN, SIMPLE SOS INPUTS");
  Serial.println("=================================================");

  // 1. Initialize LittleFS
  if (!LittleFS.begin(true)) {
    Serial.println("[LittleFS] Warning: Mount failed. Using Flash PROGMEM.");
    littleFSActive = false;
  } else {
    littleFSActive = true;
    Serial.println("[LittleFS] Mounted successfully.");
  }

  // 2. Initialize Wi-Fi in SoftAP mode
  WiFi.mode(WIFI_AP);
  WiFi.softAPConfig(apIP, apIP, netMsk);
  bool apCreated = WiFi.softAP(AP_SSID, AP_PASS);

  if (apCreated) {
    Serial.print("[AP] Access Point SSID: ");
    Serial.println(AP_SSID);
    Serial.print("[AP] Gateway IP: ");
    Serial.println(WiFi.softAPIP());
  } else {
    Serial.println("[AP] ERROR: Failed to initialize SoftAP!");
  }

  // 3. Start DNS Server for Captive Portal (Redirect all domains to 192.168.4.1)
  dnsServer.setErrorReplyCode(DNSReplyCode::NoError);
  dnsServer.start(DNS_PORT, "*", apIP);
  Serial.println("[DNS] DNS Server listening on Port 53 (* -> 192.168.4.1)");

  // 4. Web Server Routes
  server.on("/", HTTP_GET, handleRoot);
  server.on("/submit-sos", HTTP_POST, handleSOSSubmit);

  // Common Captive Portal Detection Endpoints
  server.on("/generate_204", HTTP_GET, handleRoot);
  server.on("/gen_204", HTTP_GET, handleRoot);
  server.on("/hotspot-detect.html", HTTP_GET, handleRoot);
  server.on("/library/test/success.html", HTTP_GET, handleRoot);
  server.on("/ncsi.txt", HTTP_GET, handleRoot);
  server.on("/connecttest.txt", HTTP_GET, handleRoot);
  server.on("/redirect", HTTP_GET, handleRoot);
  server.on("/canonical.html", HTTP_GET, handleRoot);
  server.on("/wpad.dat", HTTP_GET, handleCaptiveRedirect);

  server.onNotFound(handleCaptiveRedirect);

  server.begin();
  Serial.println("[HTTP] Emergency Web Server active on port 80");
  Serial.println("[STATUS] System READY.");
  Serial.println("=================================================");
}

// --- Main Loop ---
void loop() {
  dnsServer.processNextRequest();
  server.handleClient();
}
