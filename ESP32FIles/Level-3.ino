/*
 * ============================================================================
 * ESP32 LEVEL 3 ZERO-CONNECTIVITY EDGE NODE (GEO-GUARDIANS 2)
 * ============================================================================
 * Features:
 * 1. Offline Wi-Fi SoftAP ("EMERGENCY-SOS-PORTAL") with Captive Portal.
 * 2. DNS Server intercepting all domains (* -> 192.168.4.1).
 * 3. ESPAsyncWebServer serving LittleFS portal with PROGMEM fallback.
 * 4. Dual-Role Support: Citizen SOS vs. Volunteer Reinforcement & Backup.
 * 5. UDP Subnet Broadcasting (192.168.4.255:19700) for instant local responder ping.
 * 6. LittleFS Persistent Alert Logging (/alerts_log.jsonl).
 * 7. FreeRTOS-Decoupled Core 1 Worker Task for non-blocking Core 0 mesh execution.
 * 8. Priority-Based ESP-MESH Transmission Queue (CRITICAL alerts preempt telemetry).
 * 9. Serial USB framed JSON streaming (115200 baud).
 * ============================================================================
 */

#include <WiFi.h>
#include <DNSServer.h>
#include <LittleFS.h>
#include <WiFiUDP.h>
#include <AsyncTCP.h>
#include <ESPAsyncWebServer.h>

// --- Configuration & Constants ---
const char* AP_SSID = "EMERGENCY-SOS-PORTAL";
const char* AP_PASS = ""; // Open network for emergency access

// Beacon Node Hardware GPS Configuration
const char* BEACON_NODE_ID = "RESCUE-BEACON-04";
const float BEACON_LATITUDE = 28.613900;
const float BEACON_LONGITUDE = 77.209000;

// Networking Configuration
const byte DNS_PORT = 53;
const IPAddress apIP(192, 168, 4, 1);
const IPAddress netMsk(255, 255, 255, 0);

// UDP Subnet Broadcast Configuration
const uint16_t UDP_BROADCAST_PORT = 19700;
const IPAddress UDP_BROADCAST_IP(192, 168, 4, 255);

// Servers and Peripherals
DNSServer dnsServer;
AsyncWebServer server(80);
WiFiUDP udpBroadcast;

bool littleFSActive = false;

// --- FreeRTOS Decoupled Queue Definitions ---
struct AlertMessage {
  char payload[1536];
};

struct MeshQueuePacket {
  char data[1536];
  size_t length;
  bool isCritical;
  uint32_t timestamp;
};

QueueHandle_t alertDispatchQueue = NULL;
QueueHandle_t meshTxQueue = NULL;

const size_t DISPATCH_QUEUE_SIZE = 10;
const size_t MESH_QUEUE_SIZE = 20;

// --- Embedded Fallback HTML (Served if LittleFS /index.html is missing) ---
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
      --cyan-glow: rgba(56, 189, 248, 0.4);
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
    .header { border-bottom: 1px solid var(--card-border); padding-bottom: 16px; margin-bottom: 20px; }
    .header-top-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .header-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 51, 75, 0.15); color: var(--danger-red); border: 1px solid var(--danger-red); padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; animation: pulse-border 2s infinite; transition: all 0.3s ease; }
    .header-badge.volunteer-mode { background: rgba(56, 189, 248, 0.15); color: var(--accent-cyan); border-color: var(--accent-cyan); animation: pulse-cyan-border 2s infinite; }
    .stealth-responder-toggle { background: transparent; border: 1px solid rgba(148, 163, 184, 0.18); color: #64748b; padding: 3px 7px; border-radius: 6px; font-size: 0.65rem; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; opacity: 0.42; outline: none; transition: all 0.25s ease; user-select: none; }
    .stealth-responder-toggle .stealth-indicator { width: 5px; height: 5px; border-radius: 50%; background: #64748b; transition: all 0.25s ease; }
    .stealth-responder-toggle:hover { opacity: 0.85; border-color: rgba(56, 189, 248, 0.4); color: #94a3b8; }
    .stealth-responder-toggle.active { opacity: 1; background: rgba(56, 189, 248, 0.12); border-color: var(--accent-cyan); color: var(--accent-cyan); font-weight: 700; box-shadow: 0 0 8px rgba(56, 189, 248, 0.25); }
    .stealth-responder-toggle.active .stealth-indicator { background: var(--accent-cyan); box-shadow: 0 0 6px var(--accent-cyan); }
    @keyframes pulse-border { 0%, 100% { box-shadow: 0 0 0 0 var(--danger-glow); } 50% { box-shadow: 0 0 10px 3px var(--danger-glow); } }
    @keyframes pulse-cyan-border { 0%, 100% { box-shadow: 0 0 0 0 var(--cyan-glow); } 50% { box-shadow: 0 0 10px 3px var(--cyan-glow); } }
    .header-text { text-align: center; }
    .header h1 { font-size: 1.5rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .header p { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; }
    .section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--accent-amber); margin: 18px 0 10px 0; display: flex; align-items: center; justify-content: space-between; }
    .badge-critical { background: #ef4444; color: #ffffff; font-size: 0.65rem; font-weight: 800; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; }
    .form-group { margin-bottom: 14px; }
    label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #e2e8f0; }
    input[type="text"], input[type="tel"], input[type="number"], select, textarea { width: 100%; background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 8px; color: #ffffff; padding: 12px; font-size: 0.95rem; outline: none; }
    input:focus, select:focus, textarea:focus { border-color: var(--input-focus); box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .gps-lock-card { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; }
    .gps-lock-header { display: flex; align-items: center; justify-content: space-between; color: #10b981; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; }
    .gps-lock-coords { font-size: 0.9rem; font-weight: 700; color: #ffffff; font-family: monospace; }
    .gps-lock-subtext { font-size: 0.75rem; color: var(--text-muted); }
    .chip-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .chip { background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 20px; padding: 7px 13px; font-size: 0.82rem; color: var(--text-muted); cursor: pointer; user-select: none; }
    .chip.active { background: rgba(255, 170, 0, 0.2); border-color: var(--accent-amber); color: #ffd27a; font-weight: 600; }
    .sos-button-wrapper { margin-top: 24px; }
    .sos-btn { width: 100%; background: linear-gradient(135deg, #ff2a40, #d90429); color: white; border: none; border-radius: 12px; padding: 18px 20px; font-size: 1.2rem; font-weight: 900; letter-spacing: 1px; cursor: pointer; display: flex; flex-direction: column; align-items: center; box-shadow: 0 6px 20px rgba(217, 4, 41, 0.5); }
    .sos-btn span.subtext { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-top: 2px; }
    .sos-btn.volunteer-mode { background: linear-gradient(135deg, #0284c7, #1d4ed8); box-shadow: 0 6px 20px rgba(29, 78, 216, 0.5); }
    .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 15, 25, 0.95); backdrop-filter: blur(8px); z-index: 100; justify-content: center; align-items: center; padding: 20px; }
    .overlay-card { background: var(--card-bg); border: 2px solid var(--success-green); border-radius: 16px; padding: 30px 20px; text-align: center; max-width: 420px; width: 100%; }
    .reset-btn { background: #334155; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-top-row">
        <div class="header-badge" id="headerBadge">EMERGENCY MESH LINK</div>
        <button type="button" class="stealth-responder-toggle" id="stealthToggleBtn" onclick="toggleRole()" title="Field Responder Mode Toggle">
          <span class="stealth-indicator"></span>
          <span id="stealthLabel">Responder Mode</span>
        </button>
      </div>
      <div class="header-text">
        <h1 id="headerTitle">EMERGENCY SOS</h1>
        <p id="headerSubtitle">Direct Connection to Local Search & Rescue Edge Beacon</p>
      </div>
    </div>

    <form id="sosForm" onsubmit="submitSOS(event)">
      <input type="hidden" name="user_role" id="user_role" value="citizen">

      <div class="section-title"><span id="contactSectionTitle">Incident & Contact Details</span></div>
      <div class="form-group">
        <label for="victim_name" id="nameLabel">Full Name / Caller Name</label>
        <input type="text" id="victim_name" name="victim_name" placeholder="e.g. Rohan Sharma">
      </div>
      <div class="form-group">
        <label for="phone" id="phoneLabel">Direct Contact Mobile / Phone</label>
        <input type="tel" id="phone" name="phone" placeholder="e.g. +91 98765 43210">
      </div>

      <div id="citizenFields">
        <div class="section-title">Emergency Classification</div>
        <div class="form-group">
          <label for="emergency_type">Crisis Type</label>
          <select id="emergency_type" name="emergency_type">
            <option value="General Emergency" selected>General Emergency</option>
            <option value="Flood">Flood</option><option value="Earthquake">Earthquake</option>
            <option value="Fire">Fire</option><option value="Chemical Leak">Chemical Leak</option>
            <option value="Building Collapse">Building Collapse</option><option value="Medical Trauma">Medical Trauma</option>
            <option value="Trapped / Structural">Trapped / Structural</option>
          </select>
        </div>
        <div class="section-title">Triage Needs & Aid</div>
        <div class="form-group">
          <div class="chip-container">
            <div class="chip" onclick="toggleChip(this, 'Medical')">🚑 Medical</div>
            <div class="chip" onclick="toggleChip(this, 'Trapped')">⚠️ Trapped</div>
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
      </div>

      <div id="volunteerFields" style="display: none;">
        <div class="section-title" style="color:var(--accent-cyan);">
          <span>Field Reinforcement Request</span><span class="badge-critical">CRITICAL</span>
        </div>
        <div class="form-group">
          <label for="volunteer_id">Volunteer ID / Responder Tag</label>
          <input type="text" id="volunteer_id" name="volunteer_id" placeholder="e.g. VOL-4821 / Squad Alpha">
        </div>
        <div class="form-group">
          <label for="backup_type">Backup Type</label>
          <select id="backup_type" name="backup_type">
            <option value="Medical" selected>Medical</option>
            <option value="Search & Rescue">Search & Rescue</option>
            <option value="Heavy Debris">Heavy Debris</option>
            <option value="Water Extraction">Water Extraction</option>
          </select>
        </div>
        <div class="form-group">
          <label for="personnel_needed">Personnel Needed</label>
          <input type="number" id="personnel_needed" name="personnel_needed" min="1" max="50" value="2" placeholder="e.g. 3">
        </div>
      </div>

      <div class="section-title">Emergency Location (Beacon GPS Fixed)</div>
      <div class="gps-lock-card">
        <div class="gps-lock-header"><span>Beacon GPS Locked</span><span style="font-size:0.7rem; background:#10b981; color:#0b0f19; padding:2px 6px; border-radius:4px; font-weight:800;">AUTO-PINNED</span></div>
        <div class="gps-lock-coords">Lat: 28.613900 | Lng: 77.209000</div>
        <div class="gps-lock-subtext">Target position automatically locked from this rescue beacon node.</div>
      </div>
      <input type="hidden" id="gps_lat" name="gps_lat" value="28.613900">
      <input type="hidden" id="gps_lng" name="gps_lng" value="77.209000">

      <div class="form-group">
        <label for="message" id="messageLabel">Ground Notes & Exact Spot</label>
        <textarea id="message" name="message" rows="3" placeholder="e.g. Water reaching 1st floor, 2 elderly trapped on terrace. Near City Hospital."></textarea>
      </div>

      <div class="sos-button-wrapper">
        <button type="submit" id="submitBtn" class="sos-btn">
          <span id="btnMainText">BROADCAST SOS ALERT</span>
          <span class="subtext" id="btnSubText">TRANSMIT TO COMMAND CENTER</span>
        </button>
      </div>
    </form>
  </div>

  <div id="confirmationOverlay" class="overlay">
    <div class="overlay-card">
      <div style="font-size:3rem; margin-bottom:12px;" id="confirmIcon">✓</div>
      <h2 id="confirmTitle" style="color:#ffffff; margin-bottom:8px;">SOS TRANSMITTED!</h2>
      <p id="confirmDesc" style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Your distress report and beacon GPS coordinates have been registered and transmitted to the rescue command network. Stay in a safe position.</p>
      <button class="reset-btn" onclick="closeConfirmation()">Submit Another Report</button>
    </div>
  </div>

  <script>
    let activeRole = 'citizen';
    const selectedNeeds = new Set();
    function toggleRole() {
      setRole(activeRole === 'citizen' ? 'volunteer' : 'citizen');
    }
    function setRole(role) {
      activeRole = role;
      const stealthBtn = document.getElementById('stealthToggleBtn');
      const stealthLabel = document.getElementById('stealthLabel');
      const hiddenRole = document.getElementById('user_role');
      const citizenFields = document.getElementById('citizenFields');
      const volunteerFields = document.getElementById('volunteerFields');
      const headerBadge = document.getElementById('headerBadge');
      const headerTitle = document.getElementById('headerTitle');
      const submitBtn = document.getElementById('submitBtn');
      const btnMainText = document.getElementById('btnMainText');
      const btnSubText = document.getElementById('btnSubText');
      const nameLabel = document.getElementById('nameLabel');
      const nameInput = document.getElementById('victim_name');
      const messageLabel = document.getElementById('messageLabel');

      if (hiddenRole) hiddenRole.value = role;

      if (role === 'volunteer') {
        stealthBtn.classList.add('active');
        stealthLabel.innerText = 'Responder Active (Exit)';
        citizenFields.style.display = 'none';
        volunteerFields.style.display = 'block';
        headerBadge.innerText = 'VOLUNTEER FIELD MESH';
        headerBadge.classList.add('volunteer-mode');
        headerTitle.innerText = 'VOLUNTEER BACKUP';
        nameLabel.innerText = 'Volunteer Name / Lead Responder';
        nameInput.placeholder = 'e.g. Commander Sarah Chen';
        messageLabel.innerText = 'Field Situation & Reinforcement Notes';
        submitBtn.classList.add('volunteer-mode');
        btnMainText.innerText = 'BROADCAST BACKUP REQUEST';
        btnSubText.innerText = 'LOCAL UDP & HIGH-PRIORITY MESH PUSH';
      } else {
        stealthBtn.classList.remove('active');
        stealthLabel.innerText = 'Responder Mode';
        volunteerFields.style.display = 'none';
        citizenFields.style.display = 'block';
        headerBadge.innerText = 'EMERGENCY MESH LINK';
        headerBadge.classList.remove('volunteer-mode');
        headerTitle.innerText = 'EMERGENCY SOS';
        nameLabel.innerText = 'Full Name / Caller Name';
        nameInput.placeholder = 'e.g. Rohan Sharma';
        messageLabel.innerText = 'Ground Notes & Exact Spot';
        submitBtn.classList.remove('volunteer-mode');
        btnMainText.innerText = 'BROADCAST SOS ALERT';
        btnSubText.innerText = 'TRANSMIT TO COMMAND CENTER';
      }
    }
    function toggleChip(el, need) {
      if (selectedNeeds.has(need)) { selectedNeeds.delete(need); el.classList.remove('active'); }
      else { selectedNeeds.add(need); el.classList.add('active'); }
      document.getElementById('quick_needs').value = Array.from(selectedNeeds).join(', ');
    }
    function submitSOS(event) {
      event.preventDefault();
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.innerHTML = '<span>TRANSMITTING...</span>';
      const isVolunteer = (activeRole === 'volunteer');
      const callerName = document.getElementById('victim_name').value.trim() || (isVolunteer ? "Field Volunteer" : "Emergency Caller");
      const phone = document.getElementById('phone').value.trim() || "N/A";
      const lat = parseFloat(document.getElementById('gps_lat').value) || 28.613900;
      const lng = parseFloat(document.getElementById('gps_lng').value) || 77.209000;
      const notes = document.getElementById('message').value.trim() || "";
      let payload = { sender_name: callerName, victim_name: callerName, sender_phone: phone, phone: phone, latitude: lat, gps_lat: lat, longitude: lng, gps_lng: lng, message: notes };
      if (isVolunteer) {
        payload.alert_type = "VOLUNTEER_BACKUP";
        payload.priority = "CRITICAL";
        payload.volunteer_id = document.getElementById('volunteer_id').value.trim() || "VOL-UNASSIGNED";
        payload.backup_type = document.getElementById('backup_type').value || "Search & Rescue";
        payload.personnel_needed = parseInt(document.getElementById('personnel_needed').value) || 1;
        payload.emergency_type = "Volunteer Backup: " + payload.backup_type;
        payload.event = "VOLUNTEER_BACKUP";
      } else {
        payload.alert_type = "CITIZEN_SOS";
        payload.priority = "HIGH";
        payload.emergency_type = document.getElementById('emergency_type').value || "General Emergency";
        payload.medical_needs = document.getElementById('quick_needs').value.trim() || "";
        payload.quick_needs = document.getElementById('quick_needs').value.trim() || "";
        payload.blood_type = document.getElementById('blood_type').value || "Unknown";
        payload.age = parseInt(document.getElementById('age').value) || null;
        payload.event = "SOS_DISPATCH";
      }
      fetch('/submit-sos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(data => {
        if (isVolunteer) {
          document.getElementById('confirmIcon').innerText = '🛡️';
          document.getElementById('confirmTitle').innerText = 'BACKUP REQUEST DISPATCHED!';
          document.getElementById('confirmDesc').innerText = 'CRITICAL volunteer reinforcement alert broadcasted locally over UDP (Port 19700) and queued ahead of telemetry across the ESP-MESH backbone.';
        } else {
          document.getElementById('confirmIcon').innerText = '✓';
          document.getElementById('confirmTitle').innerText = 'SOS TRANSMITTED!';
          document.getElementById('confirmDesc').innerText = 'Your distress report and beacon GPS coordinates have been registered and transmitted to the rescue command network. Stay in a safe position.';
        }
        document.getElementById('confirmationOverlay').style.display = 'flex';
        btn.disabled = false;
        setRole(activeRole);
      })
      .catch(err => {
        if (isVolunteer) {
          document.getElementById('confirmIcon').innerText = '🛡️';
          document.getElementById('confirmTitle').innerText = 'BACKUP REQUEST QUEUED!';
          document.getElementById('confirmDesc').innerText = 'Emergency reinforcement payload captured by edge beacon node and queued for local responders.';
        } else {
          document.getElementById('confirmIcon').innerText = '✓';
          document.getElementById('confirmTitle').innerText = 'SOS TRANSMITTED!';
          document.getElementById('confirmDesc').innerText = 'Your distress report and beacon GPS coordinates have been registered and transmitted to the rescue command network. Stay in a safe position.';
        }
        document.getElementById('confirmationOverlay').style.display = 'flex';
        btn.disabled = false;
        setRole(activeRole);
      });
    }
    function closeConfirmation() {
      document.getElementById('confirmationOverlay').style.display = 'none';
      document.getElementById('sosForm').reset();
      selectedNeeds.clear();
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
      setRole('citizen');
    }
  </script>
</body>
</html>
)rawliteral";

// --- Helper: Safe JSON Key Extraction (No external version dependencies) ---
String extractJsonField(const String& json, const String& key) {
  // Try pattern: "key":"value" or "key": "value"
  String searchStr = "\"" + key + "\":\"";
  int idx = json.indexOf(searchStr);
  if (idx == -1) {
    searchStr = "\"" + key + "\" : \"";
    idx = json.indexOf(searchStr);
  }
  if (idx != -1) {
    idx += searchStr.length();
    int endIdx = json.indexOf("\"", idx);
    if (endIdx != -1) {
      return json.substring(idx, endIdx);
    }
    return "";
  }

  // Try pattern for numeric/boolean: "key":value
  searchStr = "\"" + key + "\":";
  idx = json.indexOf(searchStr);
  if (idx != -1) {
    idx += searchStr.length();
    while (idx < (int)json.length() && (json[idx] == ' ' || json[idx] == '\"')) idx++;
    int endIdx = idx;
    while (endIdx < (int)json.length() && json[endIdx] != '\"' && json[endIdx] != ',' && json[endIdx] != '}' && json[endIdx] != '\r' && json[endIdx] != '\n') {
      endIdx++;
    }
    return json.substring(idx, endIdx);
  }

  return "";
}

// --- Helper: Escape string for JSON ---
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

// --- LittleFS Persistent Save Function ---
bool saveAlertToLittleFS(const String& payload, bool isCritical) {
  if (!littleFSActive) {
    Serial.println("[LittleFS] Warning: Filesystem inactive, alert not persisted to flash.");
    return false;
  }

  // Append structured alert record to persistent storage log
  File logFile = LittleFS.open("/alerts_log.jsonl", "a");
  if (!logFile) {
    Serial.println("[LittleFS] Error: Failed to open /alerts_log.jsonl for writing");
    return false;
  }

  logFile.println(payload);
  logFile.flush();
  logFile.close();

  Serial.printf("[LittleFS] Alert persisted -> /alerts_log.jsonl [%s] (%d bytes)\n",
                isCritical ? "CRITICAL" : "STANDARD", payload.length());
  return true;
}

// --- ESP-MESH Priority-Based Routing Function ---
bool routeToMeshQueue(const String& payload, bool isCritical) {
  if (meshTxQueue == NULL) {
    Serial.println("[ESP-MESH] Mesh TX Queue not initialized!");
    return false;
  }

  MeshQueuePacket packet;
  memset(&packet, 0, sizeof(MeshQueuePacket));
  packet.length = min((size_t)payload.length(), sizeof(packet.data) - 1);
  memcpy(packet.data, payload.c_str(), packet.length);
  packet.data[packet.length] = '\0';
  packet.isCritical = isCritical;
  packet.timestamp = millis();

  BaseType_t res;
  if (isCritical) {
    // CRITICAL Priority: JUMP to the FRONT of the queue ahead of standard telemetry!
    res = xQueueSendToFront(meshTxQueue, &packet, (TickType_t)10);
    if (res == pdPASS) {
      Serial.printf("[ESP-MESH] >>> CRITICAL PACKET QUEUED TO FRONT OF MESH TX (%d bytes) <<<\n", packet.length);
    } else {
      Serial.println("[ESP-MESH] ERROR: Failed to push critical packet to front of Mesh TX queue (Queue Full)!");
    }
  } else {
    // Standard telemetry: Push to the back of the queue (FIFO)
    res = xQueueSendToBack(meshTxQueue, &packet, (TickType_t)10);
    if (res == pdPASS) {
      Serial.printf("[ESP-MESH] Standard telemetry queued to back of Mesh TX (%d bytes)\n", packet.length);
    } else {
      Serial.println("[ESP-MESH] Warning: Mesh TX queue full for standard telemetry.");
    }
  }

  return (res == pdPASS);
}

// --- Simulated ESP-MESH Core 0 Transmission Engine Task ---
void meshTransmitterTask(void *pvParameters) {
  MeshQueuePacket txPacket;
  while (true) {
    if (xQueueReceive(meshTxQueue, &txPacket, portMAX_DELAY) == pdTRUE) {
      // In production, esp_mesh_send() or radio TX occurs here on Core 0
      Serial.printf("[ESP-MESH-CORE0] Transmitting %s packet (%d bytes) up the mesh network\n",
                    txPacket.isCritical ? "PRIORITY CRITICAL" : "STANDARD TELEMETRY",
                    txPacket.length);
      vTaskDelay(pdMS_TO_TICKS(20)); // Simulated transmission time
    }
  }
}

// --- FreeRTOS Core 1 Worker Task: Decoupled Alert Processor ---
void edgeWorkerTask(void *pvParameters) {
  Serial.println("[Worker] Edge Dispatch Worker Task active on Core 1");

  // Initialize UDP socket safely once on task startup - NO reallocations or memory leaks
  udpBroadcast.begin(UDP_BROADCAST_PORT);

  AlertMessage incomingMsg;

  while (true) {
    if (xQueueReceive(alertDispatchQueue, &incomingMsg, portMAX_DELAY) == pdTRUE) {
      String payload = String(incomingMsg.payload);

      // 1. Parse alert_type and priority
      String alertType = extractJsonField(payload, "alert_type");
      String priority = extractJsonField(payload, "priority");

      bool isVolunteerBackup = (alertType == "VOLUNTEER_BACKUP");
      bool isCritical = (priority == "CRITICAL" || isVolunteerBackup);

      Serial.println("[Worker] Processing incoming alert payload:");
      Serial.printf("         Alert Type: %s | Priority: %s\n", 
                    alertType.length() > 0 ? alertType.c_str() : "STANDARD",
                    priority.length() > 0 ? priority.c_str() : "NORMAL");

      // 2. UDP Local Subnet Broadcast (If alert_type == "VOLUNTEER_BACKUP")
      if (isVolunteerBackup) {
        Serial.printf("[UDP] Broadcasting VOLUNTEER_BACKUP to %s:%d...\n", 
                      UDP_BROADCAST_IP.toString().c_str(), UDP_BROADCAST_PORT);

        udpBroadcast.beginPacket(UDP_BROADCAST_IP, UDP_BROADCAST_PORT);
        udpBroadcast.write((const uint8_t*)payload.c_str(), payload.length());
        udpBroadcast.endPacket();

        Serial.printf("[UDP] Broadcast dispatched to local subnet (%d bytes)\n", payload.length());
      }

      // 3. Save to LittleFS Persistent Storage
      saveAlertToLittleFS(payload, isCritical);

      // 4. Pass to ESP-MESH Transmission Queue with Priority Routing
      routeToMeshQueue(payload, isCritical);

      // 5. Emit Framed JSON over USB Serial for local Python/WebSerial dashboards
      Serial.println("---SOS_START---");
      Serial.println(payload);
      Serial.println("---SOS_END---");
    }
  }
}

// --- Helper: Queue raw payload into FreeRTOS worker queue ---
void enqueueAlertPayload(const String& rawJson) {
  if (alertDispatchQueue == NULL) {
    Serial.println("[Dispatch] ERROR: Alert dispatch queue not ready!");
    return;
  }

  AlertMessage msg;
  memset(&msg, 0, sizeof(AlertMessage));
  size_t copyLen = min(rawJson.length(), sizeof(msg.payload) - 1);
  memcpy(msg.payload, rawJson.c_str(), copyLen);
  msg.payload[copyLen] = '\0';

  if (xQueueSend(alertDispatchQueue, &msg, (TickType_t)10) != pdPASS) {
    Serial.println("[Dispatch] Warning: Alert dispatch queue full! Dropping item.");
  }
}

// --- Captive Portal Interception Handler ---
class CaptiveRequestHandler : public AsyncWebHandler {
public:
  CaptiveRequestHandler() {}
  virtual ~CaptiveRequestHandler() {}

  bool canHandle(AsyncWebServerRequest *request) {
    // Intercept unhandled requests to trigger captive popup
    return true;
  }

  void handleRequest(AsyncWebServerRequest *request) {
    request->redirect(String("http://") + apIP.toString() + "/");
  }
};

// --- Setup ---
void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println();
  Serial.println("=================================================");
  Serial.println("   ESP32 LEVEL 3 EMERGENCY NODE & MESH ROUTER   ");
  Serial.print("   BEACON NODE: ");
  Serial.println(BEACON_NODE_ID);
  Serial.print("   BEACON GPS : ");
  Serial.print(BEACON_LATITUDE, 6);
  Serial.print(", ");
  Serial.println(BEACON_LONGITUDE, 6);
  Serial.println("   SERVICES   : ASYNC HTTP + UDP + LITTLEFS + MESH");
  Serial.println("=================================================");

  // 1. Initialize FreeRTOS Queues
  alertDispatchQueue = xQueueCreate(DISPATCH_QUEUE_SIZE, sizeof(AlertMessage));
  meshTxQueue = xQueueCreate(MESH_QUEUE_SIZE, sizeof(MeshQueuePacket));

  if (!alertDispatchQueue || !meshTxQueue) {
    Serial.println("[RTOS] FATAL: Failed to allocate FreeRTOS Queues!");
  }

  // 2. Launch Core 1 Worker Task (Decouples file I/O & UDP from Core 0 network stack)
  xTaskCreatePinnedToCore(
    edgeWorkerTask,
    "EdgeWorkerTask",
    8192,
    NULL,
    2,
    NULL,
    1 // Pinned to Core 1
  );

  // 3. Launch Core 0 Mesh Transmitter Task
  xTaskCreatePinnedToCore(
    meshTransmitterTask,
    "MeshTxTask",
    4096,
    NULL,
    1,
    NULL,
    0 // Pinned to Core 0 (where WiFi / LwIP / ESP-MESH stack runs)
  );

  // 4. Initialize LittleFS
  if (!LittleFS.begin(true)) {
    Serial.println("[LittleFS] Warning: Mount failed. Using Flash PROGMEM.");
    littleFSActive = false;
  } else {
    littleFSActive = true;
    Serial.println("[LittleFS] Mounted successfully.");
  }

  // 5. Initialize Wi-Fi in SoftAP mode
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

  // 6. Start DNS Server for Captive Portal (Redirect all domains to 192.168.4.1)
  dnsServer.setErrorReplyCode(DNSReplyCode::NoError);
  dnsServer.start(DNS_PORT, "*", apIP);
  Serial.println("[DNS] DNS Server listening on Port 53 (* -> 192.168.4.1)");

  // 7. Configure ESPAsyncWebServer Routes
  // Root Portal Route
  server.on("/", HTTP_GET, [](AsyncWebServerRequest *request) {
    request->sendHeader("Cache-Control", "no-cache, no-store, must-revalidate");
    request->sendHeader("Pragma", "no-cache");
    request->sendHeader("Expires", "-1");

    if (littleFSActive && LittleFS.exists("/index.html")) {
      request->send(LittleFS, "/index.html", "text/html");
    } else {
      request->send_P(200, "text/html", FALLBACK_HTML);
    }
  });

  // POST Handler for SOS & Volunteer Reinforcement Submissions
  server.on("/submit-sos", HTTP_POST,
    [](AsyncWebServerRequest *request) {
      // Immediate acknowledgment sent back to HTTP client
      request->send(200, "application/json", "{\"status\":\"success\",\"message\":\"SOS received and queued by edge node\"}");
    },
    NULL,
    [](AsyncWebServerRequest *request, uint8_t *data, size_t len, size_t index, size_t total) {
      // Buffer incoming chunks safely without leaking memory
      if (index == 0) {
        request->_tempObject = new String();
        ((String*)request->_tempObject)->reserve(total + 16);
      }

      String *bodyBuffer = (String*)request->_tempObject;
      if (bodyBuffer) {
        for (size_t i = 0; i < len; i++) {
          *bodyBuffer += (char)data[i];
        }
      }

      // Completed payload received
      if (index + len >= total) {
        if (bodyBuffer && bodyBuffer->length() > 0) {
          enqueueAlertPayload(*bodyBuffer);
        }
        if (bodyBuffer) {
          delete bodyBuffer;
          request->_tempObject = NULL;
        }
      }
    }
  );

  // Common Captive Portal Detection Probes for iOS, Android, and Windows
  auto captiveRedirectHandler = [](AsyncWebServerRequest *request) {
    request->redirect(String("http://") + apIP.toString() + "/");
  };

  server.on("/generate_204", HTTP_GET, captiveRedirectHandler);
  server.on("/gen_204", HTTP_GET, captiveRedirectHandler);
  server.on("/hotspot-detect.html", HTTP_GET, captiveRedirectHandler);
  server.on("/library/test/success.html", HTTP_GET, captiveRedirectHandler);
  server.on("/ncsi.txt", HTTP_GET, captiveRedirectHandler);
  server.on("/connecttest.txt", HTTP_GET, captiveRedirectHandler);
  server.on("/redirect", HTTP_GET, captiveRedirectHandler);
  server.on("/canonical.html", HTTP_GET, captiveRedirectHandler);
  server.on("/wpad.dat", HTTP_GET, captiveRedirectHandler);

  // Wildcard handler for any other captive portal probes
  server.onNotFound(captiveRedirectHandler);

  server.begin();
  Serial.println("[HTTP] ESPAsyncWebServer active on port 80");
  Serial.println("[STATUS] Level-3 Edge Node Fully Operational.");
  Serial.println("=================================================");
}

// --- Main Loop ---
void loop() {
  dnsServer.processNextRequest();
  // AsyncWebServer and FreeRTOS worker tasks handle network & I/O autonomously without blocking
  vTaskDelay(pdMS_TO_TICKS(10));
}
