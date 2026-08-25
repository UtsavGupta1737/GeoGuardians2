# 🛡️ DisasterSafe (GeoGuardians2) — Multi-Agency Tactical Emergency Command Platform

> **Disaster Management & Multi-Agency Emergency Response Platform**  
> Developed for **Smart India Hackathon (SIH)**.  
> Connecting Superadmin Command, NDRF Force, Police Law Enforcement, Fire & Rescue CAD, Hospital EMS Triage, Volunteer Relief Corps, and Public Citizen Lifelines via Real-Time Leaflet GIS Mapping, SQLite WAL Mode, and ESP32 IoT Mesh Hardware Gateways.

---

## 📌 Executive Summary & Architecture

**DisasterSafe** is a next-generation, zero-dependency, high-resilience incident command and multi-agency tactical platform designed to operate under catastrophic field conditions. The system coordinates emergency distress beacons, field units, resources, and live geospatial tracking in real time.

```
                                  ┌─────────────────────────────┐
                                  │   Superadmin Supreme Hub    │
                                  │   (Full Multi-Agency Root)  │
                                  └──────────────┬──────────────┘
                                                 │
          ┌──────────────────────┬───────────────┴──────────────┬──────────────────────┐
          │                      │                              │                      │
┌─────────▼──────────┐ ┌─────────▼──────────┐         ┌─────────▼──────────┐ ┌─────────▼──────────┐
│  NDRF Tactical Hub │ │  Police Command HQ │         │ Fire & Rescue CAD  │ │ Medical EMS Triage │
│ (Heavy Extraction) │ │ (Perimeter/Search) │         │ (Hazmat/Collapse)  │ │ (ICU Beds/Ambulance│
└────────────────────┘ └────────────────────┘         └────────────────────┘ └────────────────────┘
                                         ▲                      ▲
                                         │                      │
                               ┌─────────┴──────────────────────┴─────────┐
                               │     Volunteer Relief Corps Command       │
                               │   (Ground Tasks, Aid Logistics, Chat)    │
                               └─────────────────┬────────────────────────┘
                                                 │ 2-Way Lifeline & Hotline
                               ┌─────────────────▼────────────────────────┐
                               │   Public Citizen Safety Portal & SOS     │
                               │  (1-Touch GPS Panic, Radar, Survival)    │
                               └──────────────────────────────────────────┘
                                                 ▲
                                                 │ Mesh Beacon / Serial Ingest
                               ┌─────────────────┴────────────────────────┐
                               │   ESP32 IoT Hardware Emergency Mesh      │
                               │  (Captive Portal & Web Serial 115200)    │
                               └──────────────────────────────────────────┘
```

---

## 🌟 Key Platform Features

### 1. 🚨 Universal SOS Alerts & Dispatch Hub (`sos.php`)
- Centralized triage matrix displaying incoming emergency signals with priority categorization (**Critical**, **High**, **Medium**, **Low**).
- 1-Click direct multi-agency dispatch buttons (**NDRF**, **Police**, **Fire**, **Medical**, **Volunteers**).
- Compact filter bar with live incident status badges (*All, SOS Pending, Active Dispatch, Rescued & Safe*).

### 2. 🗺️ Tactical Geospatial GIS Map (`map.php` & `dashboard.php`)
- High-performance interactive Leaflet GIS map with tactical coordinate overlays.
- Real-time plotting of active distress beacons, police patrol cordons, fire engines en route, hospital bed capacities, and volunteer aid warehouses.
- Live interactive popups with direct turn-by-turn navigation via Google Maps.

### 3. 🤝 Disaster Volunteer Corps Hub (`volunteer.php`)
- Real-time field duty badge toggle (**Available**, **Deployed**, **Standby**) with live GPS position locking.
- Direct citizen SOS queue with **2-Way Real-Time Chat Lifeline modal**.
- Ground relief missions board with 1-click **Join Mission** and **Mark Done** tracking.
- Warehouse logistics ledger with fast supply distribution modal (rations, water, first-aid kits, blankets).

### 4. 🛟 Public Citizen / Victim Safety Portal (`citizen.php`)
- **1-Touch Instant GPS SOS Beacon**: Automatically captures browser GPS coordinates (`navigator.geolocation`) and broadcasts emergency distress signals to multi-agency command.
- **Emergency Incident Selector**: Triage options for *Flood, Structural Collapse, Medical Trauma, Fire/Smoke, Missing Relative, and Food/Water Shortage*.
- **Active SOS Lifeline Tracker**: Real-time status display with assigned rescue unit, responder phone, vehicle ID, and live ETA countdown.
- **Verified Evacuation Shelter & Hospital Bed Radar**: Nearby shelters and trauma clinics with real-time bed capacity and driving directions.
- **Life-Saving Survival Protocols & National Speed Dial**: Accordion guides and 1-tap calling for `112`, `108`, `101`, `100`, and `1078`.

### 5. 🔌 ESP32 IoT Mesh Hardware Gateway (`settings.php`)
- High-speed USB Web Serial integration operating at `115200 baud`.
- Automatic packet deserialization parsing JSON payloads into `/api/esp32_sos_ingest.php`.
- **Strict Superadmin Governance**: Hardware connection controls, navbar status pills, and settings are strictly restricted to the Super Administrator root role.

### 6. ⚡ SQLite Concurrency & Write-Ahead Logging (WAL Mode)
- Configured with `PRAGMA journal_mode = WAL;`, `PRAGMA busy_timeout = 10000;`, and `PRAGMA synchronous = NORMAL;`.
- Eliminates SQLite table locking errors across concurrent background pollers, live chat feeds, and field responder dispatches.

---

## 👥 7-Tier Operational Role & Access Matrix

| # | Role Name | Slug | Default Landing Page | Key Granted Permissions | Security Scope & Restrictions |
|---|---|---|---|---|---|
| **1** | **Super Administrator** | `superadmin` | `dashboard.php` | **Root / Full Access (All 15 Permissions)** | Unrestricted global access across all agencies, ESP32 gateway, user RBAC, role matrix, and security audit logs. |
| **2** | **NDRF Force Commander** | `ndrf` | `dashboard.php` | `access_ndrf`, `access_sos_database`, `access_disasters`, `view_analytics`, `view_dashboard`, `edit_profile` | Specialized national rescue, heavy machinery, flood evacuation, and universal SOS triage. Blocked from admin settings and other agency CADs. |
| **3** | **Police Commander** | `police` | `police_hub.php` | `access_police`, `access_missing_persons`, `access_disasters`, `access_sos_database`, `view_dashboard`, `edit_profile` | Law enforcement, perimeter security, roadblocks, missing persons registry, and emergency escorts. Blocked from Fire/Medical/Volunteer hubs. |
| **4** | **Fire & Rescue Chief** | `fire` | `fire_hub.php` | `access_fire`, `access_disasters`, `access_sos_database`, `view_dashboard`, `edit_profile` | Fire suppression CAD, structural collapse cutting, hazmat containment, and fire tender dispatch. Blocked from Police/Medical/Volunteer hubs. |
| **5** | **Medical / EMS Director** | `medical` | `medical_hub.php` | `access_medical`, `access_disasters`, `access_sos_database`, `view_dashboard`, `edit_profile` | Hospital ICU & bed capacity, paramedic fleet dispatch, blood bank tracking, and trauma care. Blocked from Police/Fire/Volunteer hubs. |
| **6** | **Disaster Volunteer Corps** | `volunteer` | `volunteer.php` | `access_volunteer`, `access_disasters`, `view_dashboard`, `edit_profile` | Ground relief missions, food/water distribution, evacuation shelter bedding, and 2-way victim lifeline chat. Blocked from Police/Fire/Medical hubs. |
| **7** | **Public Citizen / Victim** | `user` | `citizen.php` | `view_dashboard`, `edit_profile` | GPS panic beacon, responder tracking & ETA, 2-way emergency responder chat, and shelter/hospital radar. Blocked from all agency command hubs. |

---

## 🔑 Default Demo Accounts & Credentials

For fast testing and evaluator demonstration, the system includes quick-login presets on `login.php`:

| Role | Email Address | Password | Landing Page |
|---|---|---|---|
| **Super Administrator** | `superadmin@system.local` | `password123` | `dashboard.php` |
| **NDRF Force Commander** | `ndrf.commander@disaster.local` | `password123` | `dashboard.php` |
| **Police Commander** | `police.command@disaster.local` | `password123` | `police_hub.php` |
| **Fire & Rescue Chief** | `fire.chief@disaster.local` | `password123` | `fire_hub.php` |
| **Medical / EMS Director** | `medical.ems@disaster.local` | `password123` | `medical_hub.php` |
| **Lead Volunteer** | `volunteer@disaster.local` | `password123` | `volunteer.php` |
| **Public Citizen** | `citizen@example.com` | `password123` | `citizen.php` |

---

## 🛠️ Technology Stack

- **Backend Runtime:** PHP 8.2+ (Vanilla PHP with clean modular architecture)
- **Database:** SQLite 3 with PDO, Write-Ahead Logging (WAL), and Foreign Key integrity
- **GIS & Mapping:** Leaflet.js with OpenStreetMap cartography & custom tactical marker icons
- **Styling:** Vanilla CSS with HSL color tokens, glassmorphism panels, and TailwindCSS utilities
- **Typography & Icons:** Google Fonts (*Inter*, *JetBrains Mono*) + Font Awesome 6 Pro
- **Hardware Telemetry:** Web Serial API (Chrome / Edge) connecting ESP32 Microcontrollers at 115200 baud
- **Notifications:** SweetAlert2 toast notification engine

---

## 🚀 Quick Start & Installation

### Prerequisites
- **Web Server:** Apache (WAMP, XAMPP, or LAMP stack)
- **PHP Version:** 8.2 or higher (with `pdo_sqlite` and `curl` extensions enabled)
- **Browser:** Google Chrome or Microsoft Edge (for Web Serial API support)

### Installation Steps
1. **Clone the repository** into your local web root:
   ```bash
   cd c:\wamp64\www
   git clone https://github.com/UtsavGupta1737/GeoGuardians2.git DisasterSafe
   ```
2. **Ensure write permissions** on the database directory:
   ```bash
   chmod 777 c:\wamp64\www\DisasterSafe\database
   chmod 666 c:\wamp64\www\DisasterSafe\database\app.sqlite
   ```
3. **Open the application** in your browser:
   ```
   http://localhost/DisasterSafe/login.php
   ```
4. **Log in** using any of the quick-login demo accounts listed above.

---

## 📁 Repository Structure

```
DisasterSafe/
├── api/                             # RESTful endpoints (SOS ingest, chats, GIS pins)
│   ├── esp32_sos_ingest.php         # ESP32 Web Serial / WiFi HTTP SOS receiver
│   ├── victim_volunteer_chat_send.php
│   └── victim_volunteer_chat_history.php
├── assets/                          # Static stylesheets, scripts, and media
│   ├── css/style.css                # Core design system tokens & theme variables
│   └── js/app.js                    # Global helpers and map utilities
├── config/                          # Agency database configs (fire_db.php, etc.)
├── database/                        # SQLite storage & runtime transaction logs
│   ├── app.sqlite                   # Central encrypted SQLite database (WAL mode)
│   └── esp32_ingest_log.txt         # Real-time hardware serial logs
├── ESP32FIles/                      # ESP32 firmware code & captive portal assets
├── auth.php                         # Session security, CSRF engine, and role guards
├── citizen.php                      # Public Citizen / Victim Emergency Portal
├── citizen_contacts.php             # National Emergency Helplines Speed Dial
├── citizen_guides.php               # Survival Action Protocols Accordion
├── dashboard.php                    # Multi-Agency Tactical GIS Command Center
├── db.php                           # PDO connection, WAL initialization & seeds
├── disasters.php                    # Disaster Incident Declarations Hub
├── fire_hub.php                     # Fire & Rescue Tactical Incident CAD
├── footer.php                       # Global page footer & Web Serial driver
├── header.php                       # Global header & dynamic role color palettes
├── login.php                        # Multi-role authentication & quick demo switcher
├── logout.php                       # Secure session destruction & redirect
├── map.php                          # Fullscreen Tactical GIS GIS Radar Map
├── medical_hub.php                  # Hospital Beds & EMS Ambulance Command
├── missing_persons.php              # Missing Persons Registry & Search Units
├── navbar.php                       # Top tactical navigation & agency switcher
├── police_hub.php                   # Police Law Enforcement Command HQ
├── profile.php                      # User account profile & credential manager
├── resources.php                    # Logistics, warehouse inventory & aid ledger
├── roles.php                        # 7-Tier Role Matrix & Permission Editor
├── settings.php                     # System Settings & ESP32 Hardware Hub (Superadmin)
├── sidebar.php                      # Dynamic drawer navigation with RBAC filtering
├── sos.php                          # Multi-Agency Universal SOS Alerts Hub
├── users.php                        # User Directory & Role-Based Access Control
├── volunteer.php                    # Volunteer Command Hub & 2-Way Chat Lifeline
├── volunteers.php                   # Volunteer Squad Roster & Mission Assignments
└── merge_instructions.md            # Git development & feature merge documentation
```

---

## 🔀 Branch & Merge History

- **`main`**: The primary production-grade branch containing the complete 7-tier role architecture, unified Government Command Hubs, and strict Superadmin ESP32 governance.
- **`origin/edits`**: Cleaned and standardized all UI elements by migrating Unicode emojis to Font Awesome icons, streamlined SOS alert cards, and applied neutral styling.
- **`origin/vaibhav-edit`**: Introduced 2-way victim hotline concepts, Gemini AI safety advisor integration, SQLite WAL mode optimizations, and `merge_instructions.md`.

---

## 📜 License & Compliance

Developed under the **MIT License** for the **Smart India Hackathon (SIH)**. All emergency helplines (`112`, `108`, `101`, `1078`) and operational agency structures align with the National Disaster Management Authority (NDMA) standards.
