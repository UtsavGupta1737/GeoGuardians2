# 🛡️ GeoGuardians - DisasterSafe

**DisasterSafe** is an AI-powered, enterprise-grade Disaster Management & Multi-Agency Emergency Coordination Platform developed for Smart India Hackathon (SIH). It integrates **Superadmin Disaster Command**, **Police Emergency Dispatch**, **Volunteer Relief Corps**, and **Public Citizen SOS Beacons** with real-time **Leaflet Geospatial (GIS) Mapping** over an embedded SQLite database engine.

---

## 🏗️ Unified Modular Architecture

```plaintext
DisasterSafe/
│
├── database/
│   └── app.sqlite             # Embedded standalone SQLite database (Zero setup required!)
│
├── config & core:
│   ├── db.php                 # SQLite PDO connection & automated schema migrations/seeders
│   ├── auth.php               # Session management, CSRF tokens, Flash toasts & RBAC permissions
│   ├── header.php             # HTML head, Google Fonts, Tailwind CSS CDN & Leaflet GIS CSS/JS
│   ├── navbar.php             # Top navigation, live crisis ticker & 1-click role switcher
│   ├── sidebar.php            # Collapsible permission-guarded multi-agency sidebar
│   └── footer.php             # SweetAlert2 toast triggers & script tags
│
├── modules & portals:
│   ├── index.php              # Smart role-based router gateway
│   ├── login.php              # Multi-role authentication portal with 1-click demo test drive
│   ├── logout.php             # Safe session termination
│   ├── dashboard.php          # Tactical Command Center with interactive Leaflet GIS Map & Dispatch
│   ├── citizen.php            # Public Citizen Emergency SOS Beacon with GPS auto-detection
│   ├── disasters.php          # Disaster Command Hub (Active crisis, casualties, displaced population)
│   ├── deployments.php        # Police Emergency Dispatch (Tactical cordons, roadblocks, convoys)
│   ├── missing_persons.php    # Missing Persons Registry & search operations tracker
│   ├── tasks.php              # Volunteer Missions Board (Search & rescue, medical aid triage)
│   ├── relief.php             # Relief Supply Distribution Ledger (Food rations, water, trauma kits)
│   ├── users.php              # User Management CRUD & custom permissions override
│   ├── roles.php              # Dynamic Role & Permission Matrix
│   ├── activity_logs.php      # Audit trail & system security logging with IP tracking
│   └── profile.php            # User account settings & password updates
│
├── .gitignore
└── README.md
```

---

## 🚀 How to Run

### Method 1: Under WAMP Server (Recommended)
1. Copy or place the `DisasterSafe` folder into your WAMP `www` directory:
   ```plaintext
   C:\wamp64\www\DisasterSafe
   ```
2. Ensure WAMP Server is running (Green tray icon).
3. Open your browser and navigate to:
   ```plaintext
   http://localhost/DisasterSafe/
   ```

### Method 2: Under PHP CLI Built-in Server
1. Open PowerShell or Terminal in this folder:
   ```powershell
   & "C:\wamp64\bin\php\php8.2.29\php.exe" -S localhost:8000
   # or simply:
   php -S localhost:8000
   ```
2. Visit **`http://localhost:8000`** in your browser.

---

## 🔑 Demo Login Accounts

| Role | Email | Password | Primary Dashboard / Module |
| :--- | :--- | :--- | :--- |
| **Super Administrator** | `superadmin@system.local` | `admin123` | Master Crisis & System Control (`dashboard.php`) |
| **Police Commander** | `police.command@disaster.local` | `admin123` | Squad Dispatch & Cordons (`deployments.php`) |
| **Disaster Volunteer** | `volunteer@disaster.local` | `admin123` | Missions Board & Relief Ledger (`tasks.php`) |
| **Administrator** | `alex.admin@system.local` | `admin123` | User & Operations Governance (`users.php`) |
| **Citizen (Public)** | *No login needed* | - | Emergency SOS Beacon (`citizen.php`) |

> [!TIP]
> **1-Click Test Drive**: Both `login.php` and `navbar.php` feature instant 1-click demo role switcher buttons so you can seamlessly test every agency's workflow during presentations and hackathon judging!

---

## 📤 Push Changes to Team Organization Repository

```bash
# 1. Update remote origin
git remote set-url origin https://github.com/GeoGuardians/DiasterSafe.git

# 2. Stage all files
git add .

# 3. Commit
git commit -m "feat: merge backend multi-agency modules with GIS command map and citizen SOS"

# 4. Push to main branch
git push -u origin main
```

---

## 👥 Team
**GeoGuardians** — Smart India Hackathon (SIH)
