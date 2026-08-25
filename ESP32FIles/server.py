"""
=============================================================================
LOCAL EMERGENCY COMMAND SERVER - SERIAL TO LOCALHOST DASHBOARD
=============================================================================
Reads SOS emergency dispatches transmitted over USB Serial from the ESP32
and displays them live on a real-time web dashboard at http://localhost:5000

Features:
- USB Serial auto-detection and live stream listener (115200 baud)
- Real-time WebSocket broadcasting to web UI
- Voice Note SOS Audio playback & waveform visualizer
- REST endpoints for SOS records and local simulation testing

Usage:
    pip install -r requirements.txt
    python server.py
    (or python server.py --port COM3)
=============================================================================
"""

import sys
import time
import json
import threading
import argparse
import io
import math
import struct
import base64
from datetime import datetime

try:
    import serial
    import serial.tools.list_ports
    HAS_SERIAL = True
except Exception:
    HAS_SERIAL = False

try:
    from flask import Flask, render_template, jsonify, request
    from flask_socketio import SocketIO, emit
    HAS_FLASK = True
except Exception:
    HAS_FLASK = False

if HAS_FLASK:
    app = Flask(__name__)
    app.config['SECRET_KEY'] = 'emergency-sos-secret-key-991'
    socketio = SocketIO(app, cors_allowed_origins="*")
else:
    app = None
    socketio = None

# In-memory storage for received SOS alerts
sos_records = []
serial_connection = None
serial_running = True

def generate_test_audio_b64():
    """Generates a pleasant, audible emergency double-beep WAV audio as a base64 data URI."""
    sample_rate = 8000
    duration = 1.0
    buf = io.BytesIO()
    with wave_writer(buf, sample_rate) as wav_file:
        total_frames = int(sample_rate * duration)
        frames = bytearray()
        for i in range(total_frames):
            t = i / sample_rate
            # 2 short emergency high-pitch beeps
            if (0.1 <= t <= 0.35) or (0.5 <= t <= 0.75):
                freq = 880 if t <= 0.35 else 660
                sample = int(32767 * 0.45 * math.sin(2 * math.pi * freq * t))
            else:
                sample = 0
            frames.extend(struct.pack('<h', sample))
        wav_file.writeframes(frames)
    return "data:audio/wav;base64," + base64.b64encode(buf.getvalue()).decode('ascii')

class wave_writer:
    """Lightweight context manager wrapper for wave.open"""
    def __init__(self, buf, sample_rate):
        import wave
        self.wav = wave.open(buf, 'wb')
        self.wav.setnchannels(1)
        self.wav.setsampwidth(2)
        self.wav.setframerate(sample_rate)

    def __enter__(self):
        return self.wav

    def __exit__(self, exc_type, exc_val, exc_tb):
        self.wav.close()

def list_available_ports():
    if not HAS_SERIAL:
        return []
    try:
        ports = serial.tools.list_ports.comports()
        return [p.device for p in ports]
    except Exception:
        return []

def sync_to_disastersafe_db(data):
    """Auto-forwards the incoming ESP32 Serial SOS alert to the DisasterSafe PHP/SQLite database."""
    try:
        import urllib.request
        url = "http://localhost/DisasterSafe/api/esp32_sos_ingest.php"
        json_bytes = json.dumps(data).encode('utf-8')
        req = urllib.request.Request(
            url,
            data=json_bytes,
            headers={'Content-Type': 'application/json', 'User-Agent': 'ESP32-Serial-Daemon/1.0'}
        )
        with urllib.request.urlopen(req, timeout=3) as resp:
            res_body = resp.read().decode('utf-8')
            res_json = json.loads(res_body)
            if res_json.get('status') == 'success':
                print(f"[✔ DB SYNC] Successfully synced to DisasterSafe database as SOS #{res_json.get('sos_id')}")
    except Exception as e:
        # Non-blocking if WAMP is on a different port/offline
        pass

def serial_reader_thread(port_name, baud_rate=115200):
    global serial_connection, serial_running, sos_records
    if not HAS_SERIAL:
        print("[!] PySerial is not installed. To listen on physical COM ports, run: pip install pyserial")
        return

    print(f"[*] Connecting to Serial Port: {port_name} at {baud_rate} baud...")

    while serial_running:
        try:
            with serial.Serial(port_name, baud_rate, timeout=1) as ser:
                serial_connection = ser
                print(f"[+] Successfully opened Serial Port: {port_name}")
                
                in_sos_block = False
                buffer_lines = []

                while serial_running:
                    line = ser.readline().decode('utf-8', errors='ignore').strip()
                    if not line:
                        continue

                    # Suppress huge base64 spam in console
                    display_line = line if len(line) < 120 else line[:60] + "... [TRUNCATED] ..." + line[-30:]
                    print(f"[SERIAL RAW] {display_line}")

                    if line == "---SOS_START---":
                        in_sos_block = True
                        buffer_lines = []
                        continue
                    elif line == "---SOS_END---":
                        in_sos_block = False
                        full_json_str = "".join(buffer_lines)
                        try:
                            data = json.loads(full_json_str)
                            data["received_at"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                            data["id"] = len(sos_records) + 1
                            sos_records.insert(0, data)
                            
                            voice_tag = " [🎙️ VOICE NOTE]" if data.get("voice_note") or data.get("is_voice_sos") else ""
                            print(f"\n🚨 [NEW SOS ALERT #{data['id']}]{voice_tag} {data.get('victim_name')} ({data.get('emergency_type')})")
                            
                            # Automatically sync to DisasterSafe database
                            sync_to_disastersafe_db(data)

                            if socketio:
                                socketio.emit('new_sos_alert', data)
                        except Exception as e:
                            print(f"[!] Error parsing SOS JSON: {e}")
                        buffer_lines = []
                        continue

                    if in_sos_block:
                        buffer_lines.append(line)
                    else:
                        # Fallback: Check if standalone JSON line arrived
                        if line.startswith("{") and line.endswith("}"):
                            try:
                                data = json.loads(line)
                                if "sender_name" in data or "victim_name" in data:
                                    data["received_at"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                                    data["id"] = len(sos_records) + 1
                                    sos_records.insert(0, data)
                                    voice_tag = " [🎙️ VOICE NOTE]" if data.get("voice_note") or data.get("is_voice_sos") else ""
                                    print(f"\n🚨 [NEW SOS ALERT #{data['id']}]{voice_tag} {data.get('victim_name')}")
                                    
                                    # Automatically sync to DisasterSafe database
                                    sync_to_disastersafe_db(data)

                                    if socketio:
                                        socketio.emit('new_sos_alert', data)
                            except Exception:
                                pass

        except serial.SerialException as e:
            print(f"[!] Serial Port Error: {e}. Retrying in 3 seconds...")
            time.sleep(3)
        except Exception as e:
            print(f"[!] Unexpected error: {e}. Retrying in 3 seconds...")
            time.sleep(3)

if HAS_FLASK:
    @app.route('/')
    def index():
        return render_template('index.html')

    @app.route('/api/sos-list', methods=['GET'])
    def get_sos_list():
        return jsonify({
            "status": "success",
            "count": len(sos_records),
            "alerts": sos_records
        })

    @app.route('/api/test-alert', methods=['POST'])
    def trigger_test_alert():
        """Simulate an SOS alert with emergency voice note for testing without hardware"""
        test_audio = generate_test_audio_b64()
        sample = {
            "id": len(sos_records) + 1,
            "event": "SOS_DISPATCH",
            "sender_name": "Rohan Sharma (Test)",
            "victim_name": "Rohan Sharma (Test)",
            "sender_phone": "+91 98765 43210",
            "phone": "+91 98765 43210",
            "latitude": 28.6139,
            "gps_lat": 28.6139,
            "longitude": 77.2090,
            "gps_lng": 77.2090,
            "emergency_type": "Flood",
            "medical_needs": "Boat Rescue, Clean Water",
            "quick_needs": "Boat Rescue, Clean Water",
            "blood_type": "O+",
            "age": 34,
            "message": "Water reaching 1st floor, 2 elderly trapped on terrace. [Emergency Voice Note Attached]",
            "voice_note": test_audio,
            "voice_duration_sec": 8,
            "is_voice_sos": True,
            "beacon_node_id": "RESCUE-BEACON-04",
            "received_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }
        sos_records.insert(0, sample)
        socketio.emit('new_sos_alert', sample)
        return jsonify({"status": "success", "sample": sample})

def main():
    if not HAS_FLASK:
        print("[!] Flask / Flask-SocketIO not found. Please install requirements:")
        print("    pip install -r requirements.txt")
        return

    parser = argparse.ArgumentParser(description="ESP32 SOS Serial Command Center Server")
    parser.add_argument('--port', type=str, default=None, help="COM port (e.g. COM3 or /dev/ttyUSB0)")
    parser.add_argument('--baud', type=int, default=115200, help="Baud rate (default 115200)")
    parser.add_argument('--host', type=str, default='0.0.0.0', help="Host IP (default 0.0.0.0)")
    parser.add_argument('--webport', type=int, default=5000, help="Web server port (default 5000)")
    args = parser.parse_args()

    available_ports = list_available_ports()
    selected_port = args.port

    if not selected_port:
        if available_ports:
            selected_port = available_ports[0]
            print(f"[*] Auto-selected detected COM Port: {selected_port}")
            print(f"[*] All available ports: {available_ports}")
        else:
            print("[!] No USB COM ports detected! Running in web-only / test simulation mode.")
            print("[!] You can test alerts via web button or specify --port <PORT_NAME>")

    if selected_port and HAS_SERIAL:
        t = threading.Thread(target=serial_reader_thread, args=(selected_port, args.baud), daemon=True)
        t.start()

    print(f"\n=======================================================")
    print(f" 🚀 LOCAL EMERGENCY DASHBOARD RUNNING AT:")
    print(f"    👉 http://localhost:{args.webport}")
    print(f"=======================================================\n")

    socketio.run(app, host=args.host, port=args.webport, debug=False, allow_unsafe_werkzeug=True)

if __name__ == '__main__':
    main()
