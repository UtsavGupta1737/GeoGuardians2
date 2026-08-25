# ESP32 Emergency SOS Captive Portal System (with Offline Voice Note SOS)

A complete offline emergency response system built for ESP32 with automatic Wi-Fi captive portal interception, **1-Tap Emergency Voice Note SOS recording**, and real-time USB Serial streaming to local host command center web dashboards.

---

## 🏗️ System Overview

1. **ESP32 Wi-Fi SoftAP**: Broadcasts an open Wi-Fi network named `EMERGENCY-SOS-PORTAL`.
2. **Captive Portal Engine**: Intercepts all DNS requests (`* -> 192.168.4.1`), triggering the automatic browser emergency portal on Android, iOS, Windows, and macOS devices.
3. **🎙️ Voice Note SOS (Fastest Dispatch)**:
   - Built directly into the captive portal using HTML5 `MediaRecorder` (works 100% offline).
   - Allows victims who are injured, in panic, or unable to type to record an urgent voice message (up to 15 seconds) with live sound equalizer visualizer and audio preview.
   - **One-Tap Transmission**: Victims can tap **🎙️ TRANSMIT VOICE SOS NOW** to broadcast their audio recording and auto-pinned GPS coordinates immediately without filling out any text fields.
4. **Emergency Report Form (Dual Mode)**:
   - **Compulsory / Auto-filled Fields**:
     - `victim_name` / `sender_name` (e.g. `"Rohan Sharma"` or `"Emergency Voice Caller"`)
     - `phone` / `sender_phone` (e.g. `"+91 98765 43210"`)
     - `emergency_type` (Flood, Earthquake, Fire, Chemical Leak, Building Collapse, Medical Trauma, Voice Note SOS, General Emergency)
     - `latitude` / `gps_lat` & `longitude` / `gps_lng` (Auto-pinned rescue beacon node coordinates)
   - **Optional Fields**:
     - `voice_note` (Base64-encoded audio data URI, e.g. `data:audio/webm;base64,...`)
     - `voice_duration_sec` (Audio recording duration in seconds)
     - `is_voice_sos` (Boolean flag)
     - `quick_needs` (Ambulance, Oxygen, Boat Rescue, Clean Water, Rope Team, Burn Care)
     - `blood_type` (A+, A-, B+, B-, O+, O-, AB+, AB-, Unknown)
     - `age` (Victim age)
     - `message` (Ground notes and landmark details)
5. **USB Serial Transmission**: When a user submits an alert, the ESP32 outputs framed JSON data to the USB Serial port at **115200 baud**.
6. **Command Center Dashboards**:
   - **Python Command Center**: `python server.py` runs a Flask + WebSocket dashboard at `http://localhost:5000` with live audio player, waveform visualizers, incident counters, and mapping links.
   - **Browser Web Serial API**: Open `web_serial_dashboard.html` in Chrome/Edge, click "Connect ESP32 USB", and receive alerts and listen to voice notes directly with zero installations.

---

## 📦 File Structure

| File | Description |
|------|-------------|
| [`Level-3.ino`](file:///c:/Users/HP/Documents/Arduino/Level-3/Level-3.ino) | ESP32 Arduino Sketch (Wi-Fi AP, DNS Captive Server, Voice SOS engine, Serial JSON output) |
| [`data/index.html`](file:///c:/Users/HP/Documents/Arduino/Level-3/data/index.html) | LittleFS Captive Portal Web App with offline microphone recording & preview |
| [`server.py`](file:///c:/Users/HP/Documents/Arduino/Level-3/server.py) | Python Serial USB listener & Flask WebSocket server with synthetic test audio generator |
| [`templates/index.html`](file:///c:/Users/HP/Documents/Arduino/Level-3/templates/index.html) | Localhost Command Center emergency triage UI with embedded Voice SOS player & waveform visualizer |
| [`web_serial_dashboard.html`](file:///c:/Users/HP/Documents/Arduino/Level-3/web_serial_dashboard.html) | Direct browser-to-USB Web Serial API dashboard with audio playback support |
| [`requirements.txt`](file:///c:/Users/HP/Documents/Arduino/Level-3/requirements.txt) | Python dependencies (`pyserial`, `Flask`, `Flask-SocketIO`) |

---

## 🚀 Getting Started

### 1. Flash ESP32 Firmware
1. Open [`Level-3.ino`](file:///c:/Users/HP/Documents/Arduino/Level-3/Level-3.ino) in Arduino IDE.
2. Select your board: **Tools > Board > ESP32 Dev Module** (or your specific ESP32 variant).
3. Connect ESP32 via USB and select the correct Port (**Tools > Port**).
4. Click **Upload**.

### 2. Connect from a Smartphone or Laptop
1. Connect to Wi-Fi SSID: **`EMERGENCY-SOS-PORTAL`** (no password required).
2. The emergency form will automatically pop up as a captive portal on your phone or device.
3. If it does not pop up automatically, open any browser and navigate to `http://192.168.4.1`.
4. **To Send Voice SOS**:
   - Tap the large 🎙️ **Microphone** button.
   - Speak your emergency message (up to 15 seconds).
   - Tap to stop or let the timer auto-stop.
   - (Optional) Tap **▶️ Listen Preview** to check your audio.
   - Tap **🎙️ TRANSMIT VOICE SOS NOW** for instant dispatch!

---

## 💻 Receiving Data on Local Host PC

### Option 1: Python Real-Time Dashboard (Recommended)
1. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```
2. Run the server (auto-detects the connected ESP32 COM port):
   ```bash
   python server.py
   ```
   *(Or specify port manually: `python server.py --port COM3`)*
3. Open `http://localhost:5000` in your web browser.
4. Click **⚡ Test Voice Alert** to verify real-time alerts and audio playback.

### Option 2: Browser Web Serial API (Zero Setup)
1. Open [`web_serial_dashboard.html`](file:///c:/Users/HP/Documents/Arduino/Level-3/web_serial_dashboard.html) in Google Chrome or Microsoft Edge.
2. Click **🔌 Connect ESP32 USB** in the top right corner.
3. Select the ESP32 USB Serial port from the browser prompt.
4. All incoming SOS dispatches and voice recordings will appear dynamically in real time.

---

## 📡 Serial JSON Protocol Format

When an SOS report is received by the ESP32, it sends the following framed payload over USB Serial (115200 baud):

```json
---SOS_START---
{
  "event": "SOS_DISPATCH",
  "timestamp_ms": 45290,
  "beacon_node_id": "RESCUE-BEACON-04",
  "sender_name": "Rohan Sharma",
  "victim_name": "Rohan Sharma",
  "sender_phone": "+91 98765 43210",
  "phone": "+91 98765 43210",
  "latitude": 28.613900,
  "gps_lat": 28.613900,
  "longitude": 77.209000,
  "gps_lng": 77.209000,
  "emergency_type": "Voice Note SOS",
  "medical_needs": "Boat Rescue, Clean Water",
  "quick_needs": "Boat Rescue, Clean Water",
  "blood_type": "O+",
  "age": 34,
  "message": "Water reaching 1st floor, 2 elderly trapped on terrace.",
  "voice_note": "data:audio/webm;base64,GkXfo59ChoEBQveBAULygQ8...",
  "voice_duration_sec": 8,
  "is_voice_sos": true
}
---SOS_END---
```
