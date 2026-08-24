# 🐘 WAMP Server & MySQL Setup Guide for DisasterSafe

This guide explains how to set up the **PHP + MySQL Backend** using **WAMP Server** and connect it with the **React + Vite Frontend**.

---

## 📋 Prerequisites
1. [WAMP Server](https://www.wampserver.com/) installed (PHP 7.4+ or PHP 8.x, MySQL 5.7+ or 8.0+).
2. [Node.js](https://nodejs.org/) (v18 or higher) for the React frontend.

---

## 🚀 Step 1: Place the Project in WAMP `www` Directory

1. Open your WAMP `www` root directory (typically `C:\wamp64\www\`).
2. Copy or clone this `DisasterSafe` repository directly inside:
   ```plaintext
   C:\wamp64\www\DisasterSafe\
   ├── backend\
   ├── frontend\
   ├── public\
   ├── docs\
   └── README.md
   ```
   *(Or create a Windows Directory Junction / Symlink if keeping it in another folder).*

---

## 🗄️ Step 2: Import the Database in phpMyAdmin

1. Start **WAMP Server** (ensure the WAMP tray icon is **Green**).
2. Open your browser and go to `http://localhost/phpmyadmin`.
   - **Username**: `root`
   - **Password**: *(leave blank if default)*
3. Click on the **Import** tab at the top.
4. Click **Choose File** and select:
   ```plaintext
   DisasterSafe/backend/database/schema.sql
   ```
5. Click **Import** (or **Go** at the bottom).
6. Verify that the `disastersafe` database is created with tables:
   - `users`
   - `sos_alerts`
   - `facilities`
   - `dispatches`

---

## ⚙️ Step 3: Verify Database Connection in `backend/config/db.php`

Check `backend/config/db.php`:
```php
$host = '127.0.0.1';
$port = '3306'; // Change to '3308' if WAMP uses MariaDB default port
$dbname = 'disastersafe';
$username = 'root';
$password = ''; // Your MySQL password (empty by default in WAMP)
```

---

## 🧪 Step 4: Test the PHP API

Open your browser and visit:
```plaintext
http://localhost/DisasterSafe/backend/index.php
```

You should see a JSON healthcheck response:
```json
{
    "status": "success",
    "message": "DisasterSafe GeoGuardians REST API is operational 🛡️",
    "version": "1.0.0",
    "base_url": "http://localhost/DisasterSafe/backend/api"
}
```

---

## 🌐 Step 5: Start the React Frontend

1. Open a terminal inside the `frontend/` directory:
   ```bash
   cd frontend
   npm install
   npm run dev
   ```
2. The frontend runs at `http://localhost:5173`.
3. Verify or create `.env` inside `frontend/` (copied from `.env.example`):
   ```env
   VITE_API_BASE_URL=http://localhost/DisasterSafe/backend/api
   ```

---

## 📡 Available Backend API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/login.php` | Authenticate Citizen / Authority |
| `POST` | `/api/auth/register.php` | Register new user or responder |
| `POST` | `/api/sos/create.php` | Trigger emergency SOS with GPS coords |
| `GET` | `/api/sos/get_active.php` | Fetch active alerts for Authority Dashboard |
| `POST` | `/api/sos/update_status.php` | Resolve or update SOS alert status |
| `GET` | `/api/facilities/list.php` | List shelters, hospitals, fire stations |
| `POST` | `/api/facilities/update_capacity.php` | Update live bed / shelter capacity |
| `POST` | `/api/dispatch/assign.php` | Assign rescue teams to an SOS alert |
| `GET` | `/api/dispatch/list.php` | List active team dispatches |
