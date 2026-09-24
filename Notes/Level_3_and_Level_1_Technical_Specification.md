# 🛡️ GeoGuardians2 (DisasterSafe) — Level 3 & Level 1 Technical Specification & Architecture

> **Project:** GeoGuardians2 / DisasterSafe (Smart India Hackathon)  
> **Topic:** Level 3 (ESP32 Zero-Connectivity Edge Node) and Level 1 (Central Command HQ) Interconnect & Technical Architecture  
> **Companion Word Document:** [`Level_3_and_Level_1_Technical_Specification.docx`](file:///c:/Users/Yashg/New%20folder/GeoGuardians2/Notes/Level_3_and_Level_1_Technical_Specification.docx)

---

## 1. Executive System Topology & Architectural Scope

GeoGuardians2 implements an edge-to-core hierarchical disaster response architecture engineered for zero-connectivity catastrophic operational theaters. When primary cellular infrastructure collapses during floods or earthquakes, the physical ecosystem decouples local survivor distress reporting from central agency dispatch through two tightly coupled computing tiers:

* **Level 3 (Ground Edge SOS Node):** An autonomous, battery-powered ESP32 microcontroller deployed in zero-connectivity disaster zones. Operates an unauthenticated Wi-Fi SoftAP (`EMERGENCY-SOS-PORTAL`) with captive DNS interception, an offline HTML5 Voice Note SOS recording engine, persistent LittleFS flash logging, and priority FreeRTOS queues.
* **Level 1 (Central Tactical Command HQ):** The supreme incident management hub (DisasterSafe). Ingests real-time hardware telemetry over high-speed USB Web Serial (115200 baud), executes automated multi-agency triage classification, commits dispatches to SQLite in Write-Ahead Logging (WAL) mode, and renders interactive Leaflet GIS tracking.

---

## 2. Hardware Wiring Diagram & Electrical Interconnect

The physical connection between the Level 3 Edge Node and Level 1 Host Command Gateway operates via an asynchronous Serial UART over USB bridge (CP2102, CH340, or FTDI) running at 115200 baud with 8-N-1 framing.

| Signal / Pin Name | ESP32 Board Pin | Host / Bridge Pin | Signal Type | Electrical Spec | Functional Description |
|---|---|---|---|---|---|
| **VBUS (Power In)** | `VIN` / `5V` | USB Pin 1 | DC Power | 5.0V DC (500mA - 1A) | Powers ESP32 board & 3.3V LDO regulator from host PC. |
| **GND (Ground)** | `GND` | USB Pin 4 | Reference Ground | 0V Common Ground | Shared electrical reference plane for noise immunity. |
| **UART TX (Transmit)** | `GPIO 1 (TX0)` | Bridge RXD -> Host D+ | Serial Output | 3.3V TTL (115200 bps) | Transmits framed JSON alerts (`---SOS_START--- ... ---SOS_END---`). |
| **UART RX (Receive)** | `GPIO 3 (RX0)` | Bridge TXD -> Host D- | Serial Input | 3.3V TTL (115200 bps) | Receives host acknowledgment and mesh coordination pings. |
| **Wi-Fi RF Link** | PCB Antenna | Air Interface | 802.11 b/g/n RF | 2.4 GHz (+20dBm TX) | Broadcasts EMERGENCY-SOS-PORTAL to victim smartphones. |
| **Flash SPI** | Internal SPI | 4MB SPI Flash | High-Speed SPI | 3.3V Logic | LittleFS partition storing non-volatile `/alerts_log.jsonl` log. |
| **Optional GPS** | `GPIO 16 (RX2)` | NEO-6M TX | Serial Input | 3.3V / 9600 Baud | Dynamic NMEA coordinate feed for mobile edge beacons. |

---

## 3. Level 3: ESP32 Edge Node Firmware Architecture

The Level 3 node runs custom FreeRTOS C++ firmware (`Level-3.ino`) on an ESP32-WROOM-32 (Xtensa Dual-Core 32-bit LX6 @ 240MHz). Its subsystems operate completely offline with zero internet access:

* **Wi-Fi SoftAP & Captive DNS:** Broadcasts SSID `EMERGENCY-SOS-PORTAL` on IP `192.168.4.1/24`. A DNS server on Port 53 intercepts wildcard queries (`* -> 192.168.4.1`), while ESPAsyncWebServer routes OS connectivity probes (`/generate_204`, `/hotspot-detect.html`, `/ncsi.txt`) to force captive popups on Android, iOS, Windows, and macOS.
* **Client-Side Voice Note SOS Engine:** Built directly into LittleFS `/index.html` using the HTML5 `MediaRecorder` API. Encodes up to 15 seconds of spoken audio into Base64 (`data:audio/webm;base64,...`), allowing injured or illiterate victims to transmit emergencies without typing.
* **FreeRTOS Core 1 Worker Task (`edgeWorkerTask`):** Allocated with an 8KB stack at Priority 2. Decouples flash file writing (`/alerts_log.jsonl`) and UDP subnet broadcasting (`192.168.4.255:19700`) from the Core 0 network stack, completely eliminating watchdog resets.
* **Deterministic Priority Queueing:** Critical distress calls and volunteer reinforcement requests invoke `xQueueSendToFront(meshTxQueue)` to preempt routine background telemetry.

---

## 4. Inter-Tier Serialization Protocol (Framed JSON)

Data sent over the USB Serial link (115200 baud) is encapsulated in unambiguous boundary markers to protect against buffer fragmentation:

```json
---SOS_START---
{
  "event": "SOS_DISPATCH",
  "timestamp_ms": 48210,
  "beacon_node_id": "RESCUE-BEACON-04",
  "sender_name": "Rohan Sharma",
  "victim_name": "Rohan Sharma",
  "sender_phone": "+91 98765 43210",
  "phone": "+91 98765 43210",
  "latitude": 28.613900,
  "gps_lat": 28.613900,
  "longitude": 77.209000,
  "gps_lng": 77.209000,
  "emergency_type": "Flood",
  "priority": "Critical",
  "medical_needs": "Inflatable Boat Rescue, Clean Water",
  "quick_needs": "Inflatable Boat Rescue, Clean Water",
  "blood_type": "O+",
  "age": 34,
  "message": "Water reaching 1st floor level, 2 elderly trapped.",
  "voice_note": "data:audio/webm;base64,GkXfo59ChoEBQveBAULygQ8...",
  "voice_duration_sec": 8,
  "is_voice_sos": true
}
---SOS_END---
```

---

## 5. Level 1: DisasterSafe Central Command HQ & Ingest Engine

Level 1 processes raw hardware telemetry into actionable multi-agency incident management:

* **Host Web Serial API Daemon (`js/esp32_global_serial.js`):** Runs in Google Chrome/Edge, maintaining persistent port binding across page navigations with automatic reconnect tokens in `localStorage`. Includes Web Audio API synthetic siren chiming (880Hz -> 440Hz sweep).
* **Headless Python Daemon (`server.py`):** Alternative standalone Python backend utilizing `pyserial` and `Flask-SocketIO` for mobile command trailers.
* **Ingest & Auto-Triage Endpoint (`/api/esp32_sos_ingest.php`):** Deserializes JSON payloads, performs cryptographic deduplication, executes automated agency assignment (NDRF for floods, EMS for trauma, Fire for hazmat/collapse, Police for cordons, Volunteers for logistics), and writes to the database.
* **SQLite Write-Ahead Logging (WAL Mode):** Configured with `PRAGMA journal_mode = WAL;` and `PRAGMA busy_timeout = 10000;` ensuring simultaneous background pollers and write bursts never trigger database lock exceptions.
* **Tactical Leaflet GIS & Agency CAD Matrix:** Dynamically plots incoming beacons onto tactical map overlays, embeds in-browser audio players for voice notes, and notifies commanders across the 7-tier RBAC hierarchy.

---

## 6. Technical Specifications Comparison Matrix

| Parameter | Level 3 (ESP32 Edge Node) | Level 1 (DisasterSafe Command HQ) |
|---|---|---|
| **Primary Function** | Zero-connectivity edge beacon & victim portal | Central multi-agency command, triage & dispatch HQ |
| **Processor / Architecture** | Tensilica Xtensa Dual-Core LX6 @ 240 MHz | x86_64 Multi-Core Server / Host Workstation |
| **RAM / Volatile Memory** | 520 KB SRAM | 8 GB - 64 GB Host RAM |
| **Storage Medium** | 4 MB SPI Flash with LittleFS | NVMe SSD with SQLite 3 (WAL Engine) |
| **Operating System** | FreeRTOS V8.2+ | Linux / Windows Server / macOS |
| **Local Radio / Network** | 802.11 b/g/n SoftAP (20 dBm, 2.4 GHz) | Gigabit LAN / WAN / Satellite Uplink |
| **Physical Transport Link** | Hardware UART0 (115200 Baud, 8-N-1) | Host USB Controller / Web Serial API (115200 Baud) |
| **Survivor Input Channel** | HTML5 MediaRecorder Audio + Captive Portal | Multi-Agency CAD Web Consoles (7-Tier RBAC) |
| **Local Data Retention** | Persistent `/alerts_log.jsonl` flash ledger | ACID-compliant SQLite relational tables |
| **System Latency** | Touch to flash commit: < 15 ms | Serial ingest to GIS map render: < 200 ms |

---

## 7. Non-Technical Presentation Guide (Evaluation & Stakeholders)

For presenting this system to non-technical evaluators, judges, or government disaster officials:

* **The Analogy:** *"Level 3 is a digital lifebuoy dropped into a flooded city. It gives trapped victims an instant emergency Wi-Fi hotspot where they can speak a 10-second voice note without any SIM card or app download. Level 1 is the air-traffic control tower that immediately hears the voice note, plots the victim on a map, and dispatches NDRF boats."*
* **Key Life-Saving Advantage:** Zero installation, zero signal requirement, 1-tap voice dispatch for panic situations, and immediate multi-agency coordination instead of dialing separate emergency numbers.
