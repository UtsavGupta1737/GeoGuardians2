# generate_docx.ps1 - Generates a complete, beautiful Microsoft Word (.docx) document
Add-Type -AssemblyName System.IO.Compression.FileSystem

$destDir = "$PSScriptRoot\temp_docx_build"
$outputDocx = "$PSScriptRoot\Level_3_and_Level_1_Technical_Specification.docx"

if (Test-Path $destDir) { Remove-Item -Recurse -Force $destDir }
if (Test-Path $outputDocx) { Remove-Item -Force $outputDocx }

New-Item -ItemType Directory -Path "$destDir\_rels" -Force | Out-Null
New-Item -ItemType Directory -Path "$destDir\word\_rels" -Force | Out-Null

# 1. [Content_Types].xml
$contentTypes = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
'@
[System.IO.File]::WriteAllText("$destDir\[Content_Types].xml", $contentTypes, [System.Text.Encoding]::UTF8)

# 2. _rels/.rels
$rels = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
'@
[System.IO.File]::WriteAllText("$destDir\_rels\.rels", $rels, [System.Text.Encoding]::UTF8)

# 3. word/document.xml
# Helper functions to build WordML
function Escape-Xml([string]$str) {
    if ([string]::IsNullOrEmpty($str)) { return "" }
    return $str.Replace("&", "&amp;").Replace("<", "&lt;").Replace(">", "&gt;").Replace('"', "&quot;").Replace("'", "&apos;")
}

function P-Title([string]$text) {
    return "<w:p><w:pPr><w:jc w:val='center'/><w:spacing w:before='240' w:after='120'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='48'/><w:color w:val='0F172A'/></w:rPr><w:t>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-Subtitle([string]$text) {
    return "<w:p><w:pPr><w:jc w:val='center'/><w:spacing w:before='0' w:after='240'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:i/><w:sz w:val='24'/><w:color w:val='475569'/></w:rPr><w:t>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-H1([string]$text) {
    return "<w:p><w:pPr><w:spacing w:before='360' w:after='120'/><w:pBdr><w:bottom w:val='single' w:sz='12' w:space='4' w:color='2563EB'/></w:pBdr></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='32'/><w:color w:val='1E3A8A'/></w:rPr><w:t>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-H2([string]$text) {
    return "<w:p><w:pPr><w:spacing w:before='240' w:after='80'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='26'/><w:color w:val='1D4ED8'/></w:rPr><w:t>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-H3([string]$text) {
    return "<w:p><w:pPr><w:spacing w:before='160' w:after='60'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='22'/><w:color w:val='334155'/></w:rPr><w:t>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-Text([string]$text, [switch]$bold, [switch]$italic, [string]$color="1E293B") {
    $b = if ($bold) { "<w:b/>" } else { "" }
    $i = if ($italic) { "<w:i/>" } else { "" }
    return "<w:p><w:pPr><w:spacing w:before='0' w:after='120'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/>$b$i<w:sz w:val='22'/><w:color w:val='$color'/></w:rPr><w:t xml:space='preserve'>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-Bullet([string]$lead, [string]$body) {
    return "<w:p><w:pPr><w:ind w:left='360' w:hanging='180'/><w:spacing w:before='40' w:after='80'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='22'/><w:color w:val='2563EB'/></w:rPr><w:t xml:space='preserve'>• </w:t></w:r><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='22'/><w:color w:val='0F172A'/></w:rPr><w:t xml:space='preserve'>$(Escape-Xml $lead) </w:t></w:r><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:sz w:val='22'/><w:color w:val='334155'/></w:rPr><w:t xml:space='preserve'>$(Escape-Xml $body)</w:t></w:r></w:p>"
}

function P-Callout([string]$text) {
    return "<w:p><w:pPr><w:pBdr><w:left w:val='single' w:sz='24' w:space='12' w:color='3B82F6'/></w:pBdr><w:shd w:val='clear' w:color='auto' w:fill='EFF6FF'/><w:ind w:left='240' w:right='240'/><w:spacing w:before='120' w:after='120'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:i/><w:sz w:val='22'/><w:color w:val='1E40AF'/></w:rPr><w:t xml:space='preserve'>$(Escape-Xml $text)</w:t></w:r></w:p>"
}

function P-CodeBlock([string]$code) {
    $lines = $code.Split("`n")
    $xml = ""
    foreach ($line in $lines) {
        $cleanLine = $line.TrimEnd("`r")
        $xml += "<w:p><w:pPr><w:shd w:val='clear' w:color='auto' w:fill='F8FAFC'/><w:spacing w:before='20' w:after='20'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Consolas' w:hAnsi='Consolas'/><w:sz w:val='18'/><w:color w:val='0F172A'/></w:rPr><w:t xml:space='preserve'>$(Escape-Xml $cleanLine)</w:t></w:r></w:p>"
    }
    return $xml
}

function Build-Table($headers, $rows) {
    $tbl = "<w:tbl>"
    $tbl += "<w:tblPr><w:tblW w:w='9500' w:type='dxa'/><w:tblBorders><w:top w:val='single' w:sz='6' w:space='0' w:color='CBD5E1'/><w:left w:val='none'/><w:bottom w:val='single' w:sz='6' w:space='0' w:color='CBD5E1'/><w:right w:val='none'/><w:insideH w:val='single' w:sz='4' w:space='0' w:color='E2E8F0'/><w:insideV w:val='none'/></w:tblBorders><w:tblCellMar><w:top w:w='120' w:type='dxa'/><w:left w:w='160' w:type='dxa'/><w:bottom w:w='120' w:type='dxa'/><w:right w:w='160' w:type='dxa'/></w:tblCellMar></w:tblPr>"
    
    # Header Row
    $tbl += "<w:tr><w:trPr><w:tblHeader/></w:trPr>"
    foreach ($h in $headers) {
        $tbl += "<w:tc><w:tcPr><w:shd w:val='clear' w:color='auto' w:fill='1E293B'/></w:tcPr><w:p><w:pPr><w:spacing w:before='60' w:after='60'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:b/><w:sz w:val='20'/><w:color w:val='FFFFFF'/></w:rPr><w:t>$(Escape-Xml $h)</w:t></w:r></w:p></w:tc>"
    }
    $tbl += "</w:tr>"
    
    # Data Rows
    $rowIndex = 0
    foreach ($r in $rows) {
        $bg = if ($rowIndex % 2 -eq 1) { "F8FAFC" } else { "FFFFFF" }
        $tbl += "<w:tr>"
        foreach ($c in $r) {
            $tbl += "<w:tc><w:tcPr><w:shd w:val='clear' w:color='auto' w:fill='$bg'/></w:tcPr><w:p><w:pPr><w:spacing w:before='40' w:after='40'/></w:pPr><w:r><w:rPr><w:rFonts w:ascii='Calibri' w:hAnsi='Calibri'/><w:sz w:val='20'/><w:color w:val='334155'/></w:rPr><w:t>$(Escape-Xml $c)</w:t></w:r></w:p></w:tc>"
        }
        $tbl += "</w:tr>"
        $rowIndex++
    }
    $tbl += "</w:tbl>"
    return $tbl
}

$sb = [System.Text.StringBuilder]::new()
[void]$sb.Append('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
[void]$sb.Append('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">')
[void]$sb.Append('<w:body>')

# Document Header
[void]$sb.Append((P-Title "GeoGuardians2 (DisasterSafe)"))
[void]$sb.Append((P-Subtitle "Level 3 (Edge Node) and Level 1 (Command HQ) Complete Technical Specification & Wiring Architecture"))
[void]$sb.Append((P-Callout "Project Architecture Specification | Smart India Hackathon (SIH) | Multi-Agency Incident Command System"))

# Section 1: Executive Overview
[void]$sb.Append((P-H1 "1. Executive System Topology & Architectural Scope"))
[void]$sb.Append((P-Text "GeoGuardians2 (DisasterSafe) implements an edge-to-core hierarchical disaster response architecture engineered for zero-connectivity catastrophic operational theaters. When primary cellular infrastructure collapses during floods or earthquakes, the physical ecosystem decouples local survivor distress reporting from central agency dispatch through two tightly coupled computing tiers:"))
[void]$sb.Append((P-Bullet "Level 3 (Ground Edge SOS Node):" "An autonomous, battery-powered ESP32 microcontroller deployed in zero-connectivity disaster zones. Operates an unauthenticated Wi-Fi SoftAP ('EMERGENCY-SOS-PORTAL') with captive DNS interception, an offline HTML5 Voice Note SOS recording engine, persistent LittleFS flash logging, and priority FreeRTOS queues."))
[void]$sb.Append((P-Bullet "Level 1 (Central Tactical Command HQ):" "The supreme incident management hub (DisasterSafe). Ingests real-time hardware telemetry over high-speed USB Web Serial (115200 baud), executes automated multi-agency triage classification, commits dispatches to SQLite in Write-Ahead Logging (WAL) mode, and renders interactive Leaflet GIS tracking."))

# Section 2: Hardware Wiring & Pinout
[void]$sb.Append((P-H1 "2. Hardware Wiring Diagram & Electrical Interconnect"))
[void]$sb.Append((P-Text "The physical connection between the Level 3 Edge Node and Level 1 Host Command Gateway operates via an asynchronous Serial UART over USB bridge (CP2102, CH340, or FTDI) running at 115200 baud with 8-N-1 framing."))

$headersPin = @("Signal / Pin Name", "ESP32 Board Pin", "Host / Bridge Pin", "Signal Type", "Electrical Spec", "Functional Description")
$rowsPin = @(
    @("VBUS (Power In)", "VIN / 5V", "USB Pin 1", "DC Power", "5.0V DC (500mA - 1A)", "Powers ESP32 board & 3.3V LDO regulator from host PC."),
    @("GND (Ground)", "GND", "USB Pin 4", "Reference Ground", "0V Common Ground", "Shared electrical reference plane for noise immunity."),
    @("UART TX (Transmit)", "GPIO 1 (TX0)", "Bridge RXD -> Host D+", "Serial Output", "3.3V TTL (115200 bps)", "Transmits framed JSON alerts (---SOS_START--- ... ---SOS_END---)."),
    @("UART RX (Receive)", "GPIO 3 (RX0)", "Bridge TXD -> Host D-", "Serial Input", "3.3V TTL (115200 bps)", "Receives host acknowledgment and mesh coordination pings."),
    @("Wi-Fi RF Link", "PCB Antenna", "Air Interface", "802.11 b/g/n RF", "2.4 GHz (+20dBm TX)", "Broadcasts EMERGENCY-SOS-PORTAL to victim smartphones."),
    @("Flash SPI", "Internal SPI", "4MB SPI Flash", "High-Speed SPI", "3.3V Logic", "LittleFS partition storing non-volatile /alerts_log.jsonl log."),
    @("Optional GPS", "GPIO 16 (RX2)", "NEO-6M TX", "Serial Input", "3.3V / 9600 Baud", "Dynamic NMEA coordinate feed for mobile edge beacons.")
)
[void]$sb.Append((Build-Table $headersPin $rowsPin))

# Section 3: Level 3 Deep Dive
[void]$sb.Append((P-H1 "3. Level 3: ESP32 Edge Node Firmware Architecture"))
[void]$sb.Append((P-Text "The Level 3 node runs custom FreeRTOS C++ firmware (Level-3.ino) on an ESP32-WROOM-32 (Xtensa Dual-Core 32-bit LX6 @ 240MHz). Its subsystems operate completely offline with zero internet access:"))
[void]$sb.Append((P-Bullet "Wi-Fi SoftAP & Captive DNS:" "Broadcasts SSID 'EMERGENCY-SOS-PORTAL' on IP 192.168.4.1/24. A DNS server on Port 53 intercepts wildcard queries (* -> 192.168.4.1), while ESPAsyncWebServer routes OS connectivity probes (/generate_204, /hotspot-detect.html, /ncsi.txt) to force captive popups on Android, iOS, Windows, and macOS."))
[void]$sb.Append((P-Bullet "Client-Side Voice Note SOS Engine:" "Built directly into LittleFS /index.html using the HTML5 MediaRecorder API. Encodes up to 15 seconds of spoken audio into Base64 (data:audio/webm;base64,...), allowing injured or illiterate victims to transmit emergencies without typing."))
[void]$sb.Append((P-Bullet "FreeRTOS Core 1 Worker Task (edgeWorkerTask):" "Allocated with an 8KB stack at Priority 2. Decouples flash file writing (/alerts_log.jsonl) and UDP subnet broadcasting (192.168.4.255:19700) from the Core 0 network stack, completely eliminating watchdog resets."))
[void]$sb.Append((P-Bullet "Deterministic Priority Queueing:" "Critical distress calls and volunteer reinforcement requests invoke xQueueSendToFront(meshTxQueue) to preempt routine background telemetry."))

# Section 4: Data Protocol Format
[void]$sb.Append((P-H1 "4. Inter-Tier Serialization Protocol (Framed JSON)"))
[void]$sb.Append((P-Text "Data sent over the USB Serial link (115200 baud) is encapsulated in unambiguous boundary markers to protect against buffer fragmentation:"))

$codeJson = @"
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
"@
[void]$sb.Append((P-CodeBlock $codeJson))

# Section 5: Level 1 Central Command
[void]$sb.Append((P-H1 "5. Level 1: DisasterSafe Central Command HQ & Ingest Engine"))
[void]$sb.Append((P-Text "Level 1 processes raw hardware telemetry into actionable multi-agency incident management:"))
[void]$sb.Append((P-Bullet "Host Web Serial API Daemon (js/esp32_global_serial.js):" "Runs in Google Chrome/Edge, maintaining persistent port binding across page navigations with automatic reconnect tokens in localStorage. Includes Web Audio API synthetic siren chiming (880Hz -> 440Hz sweep)."))
[void]$sb.Append((P-Bullet "Headless Python Daemon (server.py):" "Alternative standalone Python backend utilizing pyserial and Flask-SocketIO for mobile command trailers."))
[void]$sb.Append((P-Bullet "Ingest & Auto-Triage Endpoint (/api/esp32_sos_ingest.php):" "Deserializes JSON payloads, performs cryptographic deduplication, executes automated agency assignment (NDRF for floods, EMS for trauma, Fire for hazmat/collapse, Police for cordons, Volunteers for logistics), and writes to the database."))
[void]$sb.Append((P-Bullet "SQLite Write-Ahead Logging (WAL Mode):" "Configured with PRAGMA journal_mode = WAL; and PRAGMA busy_timeout = 10000; ensuring simultaneous background pollers and write bursts never trigger database lock exceptions."))
[void]$sb.Append((P-Bullet "Tactical Leaflet GIS & Agency CAD Matrix:" "Dynamically plots incoming beacons onto tactical map overlays, embeds in-browser audio players for voice notes, and notifies commanders across the 7-tier RBAC hierarchy."))

# Section 6: Technical Comparison
[void]$sb.Append((P-H1 "6. Technical Specifications Comparison Matrix"))
$headersComp = @("Parameter", "Level 3 (ESP32 Edge Node)", "Level 1 (DisasterSafe Command HQ)")
$rowsComp = @(
    @("Primary Function", "Zero-connectivity edge beacon & victim portal", "Central multi-agency command, triage & dispatch HQ"),
    @("Processor / Architecture", "Tensilica Xtensa Dual-Core LX6 @ 240 MHz", "x86_64 Multi-Core Server / Host Workstation"),
    @("RAM / Volatile Memory", "520 KB SRAM", "8 GB - 64 GB Host RAM"),
    @("Storage Medium", "4 MB SPI Flash with LittleFS", "NVMe SSD with SQLite 3 (WAL Engine)"),
    @("Operating System", "FreeRTOS V8.2+", "Linux / Windows Server / macOS"),
    @("Local Radio / Network", "802.11 b/g/n SoftAP (20 dBm, 2.4 GHz)", "Gigabit LAN / WAN / Satellite Uplink"),
    @("Physical Transport Link", "Hardware UART0 (115200 Baud, 8-N-1)", "Host USB Controller / Web Serial API (115200 Baud)"),
    @("Survivor Input Channel", "HTML5 MediaRecorder Audio + Captive Portal", "Multi-Agency CAD Web Consoles (7-Tier RBAC)"),
    @("Local Data Retention", "Persistent /alerts_log.jsonl flash ledger", "ACID-compliant SQLite relational tables"),
    @("System Latency", "Touch to flash commit: < 15 ms", "Serial ingest to GIS map render: < 200 ms")
)
[void]$sb.Append((Build-Table $headersComp $rowsComp))

# Section 7: Plain English Explanations
[void]$sb.Append((P-H1 "7. Non-Technical Presentation Guide (Evaluation & Stakeholders)"))
[void]$sb.Append((P-Text "For presenting this system to non-technical evaluators, judges, or government disaster officials:"))
[void]$sb.Append((P-Bullet "The Analogy:" "'Level 3 is a digital lifebuoy dropped into a flooded city. It gives trapped victims an instant emergency Wi-Fi hotspot where they can speak a 10-second voice note without any SIM card or app download. Level 1 is the air-traffic control tower that immediately hears the voice note, plots the victim on a map, and dispatches NDRF boats.'"))
[void]$sb.Append((P-Bullet "Key Life-Saving Advantage:" "Zero installation, zero signal requirement, 1-tap voice dispatch for panic situations, and immediate multi-agency coordination instead of dialing separate emergency numbers."))

[void]$sb.Append('</w:body></w:document>')

[System.IO.File]::WriteAllText("$destDir\word\document.xml", $sb.ToString(), [System.Text.Encoding]::UTF8)

# 4. Zip into .docx
[System.IO.Compression.ZipFile]::CreateFromDirectory($destDir, $outputDocx)
Remove-Item -Recurse -Force $destDir

Write-Output "SUCCESS: Created $outputDocx"
