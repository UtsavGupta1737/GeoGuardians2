# DisasterSafe - Multi-Agency Emergency Coordination Platform

## Overview

DisasterSafe is a disaster management and multi-agency emergency coordination platform developed for Smart India Hackathon (SIH). It connects Superadmin Disaster Command, Police Emergency Dispatch, Volunteer Relief Corps, and Public Citizen SOS Beacons with real-time Leaflet GIS mapping over an embedded SQLite database.

The platform is designed for emergency operators and disaster response coordinators to manage SOS alerts, dispatch agencies, coordinate volunteers, and track incidents on a live tactical map.

---

## Key Features

- **Tactical Command Map** - Interactive Leaflet GIS map showing live SOS incidents, shelters, resource depots, and hazard zones
- **SOS Alerts Hub** - Centralized triage queue for incoming emergency distress signals with one-click agency dispatch
- **Citizen SOS Beacon** - Public-facing page for citizens to send GPS-tagged emergency signals without login
- **Multi-Agency Dispatch** - Direct dispatch buttons for NDRF, Police, Fire, EMS, and Volunteer Corps
- **Volunteer Management** - Volunteer grid with pre-mission checklists, hazard risk assessment, task assignments, and field communication
- **Agency Hubs** - Dedicated dashboards for Fire, Police, and Medical departments
- **Disaster Incident Tracking** - Crisis management with casualty counts, displaced population, and resource allocation
- **Role-Based Access Control** - Multi-role authentication (Superadmin, Police Commander, Volunteer, Admin, Citizen)
- **ESP32 Integration** - IoT mesh network support for hardware-based emergency SOS beacons
- **Activity Logging** - Full audit trail with IP tracking and system event history

---

## Changes in This Branch

These are the uncommitted changes in the working directory compared to the last commit on `main` (`c4de85f`).

### UI/UX Changes

- **Removed decorative emojis** from across the entire application - dropdown options, button labels, headers, map popups, modal dialogs, and status indicators no longer use Unicode emoji characters
- **Replaced emojis with Font Awesome icons** in the main navigation header (`includes/header.php`) - brand logo, nav links, siren toggle, theme toggle, user badge, logout, and SOS button all use `<i class="fa-solid ...">` icons instead of emojis
- **Replaced emojis with Font Awesome icons** in the volunteer management page (`volunteers.php`) - section headers and broadcast target dropdowns
- **Consistent neutral styling** applied to form elements - reduced border radius from `rounded-2xl`/`rounded-3xl` to `rounded-xl`/`rounded-lg`, reduced font weights from `font-extrabold` to `font-semibold`, and removed decorative shadows
- **Professional color palette** - removed bright decorative colors from non-critical UI elements, using slate/grey tones as the primary interface colors

### SOS Alerts Hub Changes

The SOS Alerts Hub (`sos.php`) received a significant visual redesign:

- **Removed 4 large statistic cards** (Total Recorded SOS, Pending Critical, Responders Dispatched, Rescued & Safe) that previously occupied significant screen space
- **Added compact filter bar** with 4 tab-style buttons: All, SOS (Pending), Active, and Rescued - each showing a live count badge
- **Unified active tab styling** - all filter tabs use `bg-slate-900 text-white` when selected, instead of each tab having a different color
- **Redesigned SOS alert cards** for a cleaner, more professional appearance:
  - Reduced left border from `border-l-4` with 6 different colors to `border-l-[3px]` with only 3 colors (red for Pending, emerald for Resolved, grey for all dispatched statuses)
  - Removed colored background boxes from phone numbers, GPS coordinates, persons count, and blood type badges
  - Changed dispatch buttons from colored solid buttons (orange, blue, red, teal, emerald) to uniform white buttons with light grey borders
  - Removed `animate-pulse` effect from Pending status badges
  - Reduced card border radius and shadows
- **Redesigned both modals** (Detail Modal and Manual Log Modal):
  - Removed animated red ping indicator from detail modal header
  - Changed modal border radius from `rounded-2xl`/`rounded-3xl` to `rounded-xl`
  - Muted all label colors from `text-slate-500 font-bold` to `text-slate-400 font-semibold`
  - Changed dispatch form background from `bg-blue-50/60` to `bg-slate-50`
  - Changed submit buttons from colored (blue/red) to `bg-slate-900`
  - Removed emojis from all dropdown option labels
- **Page header simplified** - removed the red icon box around the title, plain text heading only
- **"Log Distress Call" button** changed from solid red to white with grey border

### Navigation Changes

- Main navigation header emojis replaced with Font Awesome icons throughout (`includes/header.php`)
- Brand logo emoji replaced with `<i class="fa-solid fa-shield-halved">`
- ESP32 status display cleaned up in navbar

### Filtering Changes

- SOS filter bar redesigned from 4 large stat cards to compact horizontal tab pills
- Filter controls section reduced from `rounded-2xl` with shadows to `rounded-xl` without shadows
- Filter button changed from blue to dark charcoal (`bg-slate-900`)

### Visual Design Changes

- **Color reduction** across the entire application - bright teal, cyan, purple, orange, and emerald decorative colors replaced with neutral slate/grey tones
- **Semantic colors preserved** only where they carry meaning:
  - Red: Critical/Pending states and emergency indicators
  - Green/Emerald: Resolved/Rescued success states
  - Amber: High priority warnings
  - Blue: Primary UI accent (links, focus states, active navigation)
- **Typography hierarchy** improved through consistent font weight reduction (`font-extrabold` to `font-semibold`/`font-bold`)
- **Border radius consistency** - reduced from overly rounded (`rounded-2xl`, `rounded-3xl`) to more professional (`rounded-xl`, `rounded-lg`)
- **Removed excessive shadows** - `shadow-2xs` and `shadow-xs` removed from many card elements
- **Removed animations** - `animate-pulse` removed from non-critical elements, `animate-ping` removed from modal headers

### Other Changes

- **ESP32 HTML files** (`data/index.html`, `templates/index.html`, `web_serial_dashboard.html`) - removed emojis from form labels, section titles, chip buttons, and status indicators
- **JavaScript files** (`js/esp32_global_serial.js`, `assets/js/app.js`) - removed emojis from log messages, toast notifications, console output, and GPS coordinate displays
- **Agency hub pages** (`fire_hub.php`, `medical_hub.php`, `police_hub.php`) - removed emojis from emergency type dropdown options
- **Map page** (`map.php`) - removed emojis from map popup labels and contact buttons, replaced emoji phone icon with Font Awesome `<i class="fa-solid fa-phone">`
- **Citizen pages** (`citizen.php`, `citizen_contacts.php`) - removed emojis from form options and flash messages
- **Dashboard** (`dashboard.php`) - significant CSS restructuring for cleaner layout, simplified metric display
- **Resources page** (`resources.php`) - cleaned up resource category labels
- **Settings page** (`settings.php`) - removed emojis from GPS coordinate displays
- **Sidebar** (`sidebar.php`) - cleaned up navigation labels
- **Volunteer page** (`volunteer.php`) - removed emojis from dropdown options, checklist items, hazard assessment labels, map popup buttons, triage status badges, and procedural step indicators (approximately 150+ emoji removals across this file)

---

## Difference from Main Branch

The current working directory contains uncommitted changes compared to the last commit on `main` (`c4de85f`). The `edits` branch (`347e2e5`) is one commit behind `main` and does not contain the ESP32 integration commit.

| Area | Main Branch (Committed) | Current Working Directory |
|------|------------------------|--------------------------|
| SOS Dashboard | 4 large colored stat cards, multi-colored dispatch buttons, emoji labels | Compact filter bar tabs, neutral dispatch buttons, clean text labels |
| Card Styling | `rounded-2xl`, colored left borders (6 colors), shadows, pulse animations | `rounded-xl`, muted left borders (3 colors), no shadows, no animations |
| Modals | `rounded-2xl`/`rounded-3xl`, animated red ping dot, colored backgrounds | `rounded-xl`, static grey dot, neutral backgrounds |
| Navigation | Emoji icons in header nav links and controls | Font Awesome icons throughout |
| Dropdown Options | Emoji prefixes on all option labels | Plain text option labels |
| Map Popups | Emoji icons in popup headers and buttons | Font Awesome icons or plain text |
| ESP32 UI | Emoji labels on form fields and status indicators | Plain text labels |
| Color Usage | Bright decorative colors (teal, cyan, purple, orange) for non-critical elements | Neutral slate/grey for non-critical, semantic colors only for status |
| Button Styles | Colored solid buttons (red, blue, orange, teal, emerald) for dispatch | Uniform white buttons with grey borders for dispatch |
| Border Radius | `rounded-2xl`, `rounded-3xl` | `rounded-xl`, `rounded-lg` |
| Font Weights | `font-extrabold` common | `font-semibold` / `font-bold` |

---

## Technical Changes

### Files Modified (21 files, uncommitted)

**Core UI Files:**
- `sos.php` - Major visual redesign of SOS Alerts Hub
- `dashboard.php` - CSS restructuring and layout cleanup
- `includes/header.php` - Emoji to Font Awesome icon migration
- `navbar.php` - Minor cleanup
- `sidebar.php` - Cleaned navigation labels

**Agency Hub Pages:**
- `fire_hub.php` - Removed emojis from dropdown options
- `medical_hub.php` - Removed emojis from dropdown options
- `police_hub.php` - Removed emojis from dropdown options

**Volunteer & Citizen Pages:**
- `volunteer.php` - Extensive emoji removal (~150+ changes)
- `volunteers.php` - Emoji to icon replacement, dropdown cleanup
- `citizen.php` - Removed emojis from form options
- `citizen_contacts.php` - Minor cleanup

**Map & Resources:**
- `map.php` - Emoji to Font Awesome icon replacement in popups
- `resources.php` - Resource category label cleanup
- `settings.php` - GPS coordinate display cleanup

**JavaScript & IoT:**
- `assets/js/app.js` - Removed emojis from GPS toasts and fire hub alerts
- `js/esp32_global_serial.js` - Removed emojis from serial communication UI
- `ESP32FIles/data/index.html` - Form label cleanup
- `ESP32FIles/templates/index.html` - Form label cleanup
- `ESP32FIles/web_serial_dashboard.html` - Status indicator cleanup

**Data:**
- `database/app.sqlite` - Minor database update

### No Files Added or Deleted

All changes are modifications to existing files. No new files were created and no files were deleted.

### No Database Schema Changes

The SQLite database file was updated but no new tables or columns were added. The changes are limited to existing data.

### No Backend Logic Changes

All PHP logic, API endpoints, database queries, form processing, authentication, and routing remain unchanged. The modifications are purely visual/CSS/Tailwind class changes.

---

## Technologies Used

- **Backend:** PHP 8.x with PDO (SQLite)
- **Database:** SQLite (embedded, zero-configuration)
- **Frontend:** HTML5, Tailwind CSS (via CDN), Vanilla JavaScript
- **Mapping:** Leaflet.js for interactive GIS maps
- **Icons:** Font Awesome 6.5.1 (via CDN)
- **Notifications:** SweetAlert2 for toast alerts
- **IoT Integration:** ESP32 microcontroller support with web serial communication
- **Authentication:** Session-based with role-based access control (RBAC)
- **Security:** CSRF tokens, input sanitization, prepared statements

---

## Project Structure

```
DisasterSafe/
|
+-- database/
|   +-- app.sqlite                  SQLite database (zero setup)
|
+-- config/
|   +-- db.php                      PDO connection & schema migrations
|   +-- fire_db.php                 Fire department database config
|
+-- includes/
|   +-- header.php                  HTML head, CDN links, navigation header
|
+-- api/
|   +-- esp32_sos_ingest.php        ESP32 IoT SOS data ingestion endpoint
|
+-- assets/
|   +-- css/                        Stylesheets (style.css, fire_cad.css)
|   +-- js/                         Client-side JavaScript (app.js, map.js)
|
+-- js/
|   +-- esp32_global_serial.js      ESP32 serial communication handler
|
+-- ESP32FIles/                     ESP32 microcontroller firmware & web UI
|   +-- Level-3.ino                 Arduino firmware
|   +-- server.py                   Python backend for ESP32
|   +-- data/index.html             ESP32 captive portal page
|   +-- templates/index.html        ESP32 template page
|   +-- web_serial_dashboard.html   ESP32 serial monitoring dashboard
|
+-- Core PHP Files:
|   +-- index.php                   Smart role-based router gateway
|   +-- login.php                   Multi-role authentication
|   +-- logout.php                  Session termination
|   +-- auth.php                    Session, CSRF, RBAC management
|   +-- dashboard.php               Tactical Command Center with GIS map
|   +-- sos.php                     SOS Alerts & Triage Hub
|   +-- citizen.php                 Public Citizen SOS Beacon
|   +-- citizen_contacts.php        Emergency helpline directory
|   +-- citizen_guides.php          Disaster safety guides
|   +-- volunteer.php               Volunteer management & field ops
|   +-- volunteers.php              Volunteer administration
|   +-- fire_hub.php                Fire department hub
|   +-- police_hub.php              Police department hub
|   +-- medical_hub.php             Medical department hub
|   +-- disasters.php               Disaster incident tracking
|   +-- deployments.php             Police deployment management
|   +-- tasks.php                   Volunteer mission board
|   +-- relief.php                  Relief supply distribution
|   +-- resources.php               Resource management
|   +-- map.php                     Full-screen tactical map
|   +-- settings.php                ESP32 gateway settings
|   +-- users.php                   User management
|   +-- roles.php                   Role & permission matrix
|   +-- activity_logs.php           Audit trail & logging
|   +-- missing_persons.php         Missing persons registry
|   +-- profile.php                 User account settings
|   +-- navbar.php                  Top navigation bar
|   +-- sidebar.php                 Collapsible sidebar
|   +-- footer.php                  Footer & script tags
```

---

## How to Run Locally

### Prerequisites

- **WAMP Server** (recommended) or any PHP server with SQLite support
- **PHP 8.x** with PDO SQLite extension enabled
- A modern web browser (Chrome, Firefox, Edge)

### Method 1: WAMP Server (Recommended)

1. Install and start WAMP Server (ensure the tray icon is green)

2. Copy the project folder to your WAMP www directory:
   ```
   C:\wamp64\www\DisasterSafe\
   ```

3. Open your browser and go to:
   ```
   http://localhost/DisasterSafe/
   ```

4. The application will load with the SQLite database already configured. No additional database setup is needed.

### Method 2: PHP Built-in Server

1. Open PowerShell or Terminal in the project folder

2. Run:
   ```powershell
   php -S localhost:8000
   ```

3. Visit `http://localhost:8000` in your browser

### Demo Login Accounts

| Role | Email | Password |
|------|-------|----------|
| Super Administrator | `superadmin@system.local` | `admin123` |
| Police Commander | `police.command@disaster.local` | `admin123` |
| Disaster Volunteer | `volunteer@disaster.local` | `admin123` |
| Administrator | `alex.admin@system.local` | `admin123` |
| Citizen (Public) | No login needed | - |

Both `login.php` and `navbar.php` have 1-click demo role switcher buttons for quick testing.

---

## Notes

- The SQLite database (`database/app.sqlite`) comes pre-configured with demo data. No migration or seeding steps are required.
- The ESP32 integration files are included but require physical ESP32 hardware to function.
- The application uses Tailwind CSS via CDN, so an internet connection is needed for the first load.
- All form submissions use CSRF tokens for security.
- The platform is designed for the Delhi-NCR region but can be adapted for other areas by updating the map center coordinates.

---

## Future Improvements

- Real-time WebSocket updates for live SOS alerts
- Push notifications for agency dispatch assignments
- Offline support with service workers for field volunteers
- Multi-language support for broader accessibility
- Integration with government disaster management APIs
- Advanced analytics dashboard for incident pattern analysis
