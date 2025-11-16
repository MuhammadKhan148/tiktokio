# Complete Setup Guide for Running This Project

This guide will help you set up and run the TikTok/YouTube downloader project on a new computer.

## Prerequisites

Before starting, make sure you have installed:

1. **PHP 8.2+** - Download from https://www.php.net/downloads.php
   - Make sure PHP is added to your system PATH
   - Verify: `php --version`

2. **MySQL/MariaDB 9.5+** - Download from https://dev.mysql.com/downloads/installer/
   - Or use XAMPP/WAMP which includes MySQL
   - Verify: `mysql --version`

3. **Python 3.8+** - Download from https://www.python.org/downloads/
   - Make sure "Add Python to PATH" is checked during installation
   - Verify: `python --version`

4. **FFmpeg** (Required for video/audio conversion)
   - Download from: https://www.gyan.dev/ffmpeg/builds/
   - Extract and add `ffmpeg.exe` to your system PATH (or copy to `C:\Windows\System32\`)
   - Verify: `ffmpeg -version`

## Step-by-Step Setup

### Step 1: Clone/Download the Repository

```bash
git clone https://github.com/MuhammadKhan148/tiktokio.git
cd tiktokio
```

### Step 2: Set Up the Database

**Option A: Using the Batch File (Windows - Easiest)**
```bash
.\setup_db.bat
```
You'll be prompted for your MySQL root password.

**Option B: Using PHP Script**
```bash
php setup_database.php
```

**Option C: Manual Setup**
```bash
# Create database and user
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS \`tiktokio.mobi\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; CREATE USER IF NOT EXISTS 'tiktokio.mobi'@'localhost' IDENTIFIED BY 'TfjfPrtjC4Z4wmBm'; GRANT ALL PRIVILEGES ON \`tiktokio.mobi\`.* TO 'tiktokio.mobi'@'localhost'; FLUSH PRIVILEGES;"

# Import the database
mysql -u root -p tiktokio.mobi < tiktokio_mobi.sql
```

### Step 3: Configure Database Connection

If `includes/config.php` doesn't exist, copy the example:
```bash
copy includes\config.php.example includes\config.php
```

Edit `includes/config.php` and update the database credentials if needed:
- Database: `tiktokio.mobi`
- User: `tiktokio.mobi`
- Password: `TfjfPrtjC4Z4wmBm`

### Step 4: Set Up Python Backend (FastAPI)

**Option A: Using the Batch File (Windows - Easiest)**
```bash
.\start_fastapi.bat
```

**Option B: Manual Setup**
```bash
cd api
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
```

The FastAPI backend will run on: **http://127.0.0.1:8001**

### Step 5: Start PHP Server

Open a **new terminal window** and run:
```bash
php -S localhost:8000 php_router.php
```

The frontend will be available at: **http://localhost:8000**

## Testing the Application

1. **Test the Frontend:**
   - Open: http://localhost:8000
   - You should see the homepage

2. **Test YouTube Downloader:**
   - Go to: http://localhost:8000/yt1s/
   - Paste a YouTube URL (e.g., `https://youtu.be/-3KT1f7WZIo`)
   - Click "Convert"
   - You should see video details and download options

3. **Test FastAPI Backend:**
   - Open: http://127.0.0.1:8001/health
   - You should see: `{"status":"ok"}`

4. **Access Admin Panel:**
   - Go to: http://localhost:8000/admin/login.php
   - Username: `admin`
   - Password: `Admin@2025!`

## Common Issues and Solutions

### Issue 1: "PHP is not recognized"
**Solution:** Add PHP to your system PATH or use full path to PHP executable.

### Issue 2: "MySQL command not found"
**Solution:** Add MySQL bin directory to your system PATH, or use full path to mysql.exe.

### Issue 3: "FFmpeg is required for conversion but not found"
**Solution:** 
- Install FFmpeg (see Prerequisites)
- Add FFmpeg to your system PATH
- Restart the FastAPI server after installing FFmpeg

### Issue 4: "Connection failed" (Database)
**Solution:**
- Make sure MySQL service is running
- Check database credentials in `includes/config.php`
- Verify database was created: `mysql -u root -p -e "SHOW DATABASES;"`

### Issue 5: "Port 8000 or 8001 already in use"
**Solution:**
- Stop other services using these ports
- Or change ports in the startup commands

### Issue 6: "Module not found" (Python)
**Solution:**
- Make sure virtual environment is activated
- Run: `pip install -r api/requirements.txt`

### Issue 7: "FastAPI backend not responding"
**Solution:**
- Check if FastAPI is running on port 8001
- Verify: http://127.0.0.1:8001/health
- Check FastAPI terminal for error messages

## Quick Start Commands Summary

```bash
# Terminal 1: Start FastAPI Backend
.\start_fastapi.bat

# Terminal 2: Start PHP Server
php -S localhost:8000 php_router.php
```

Then open: **http://localhost:8000**

## File Structure

- `includes/config.php` - Database configuration (create from .example if needed)
- `tiktokio_mobi.sql` - Database schema
- `setup_db.bat` - Database setup script
- `start_fastapi.bat` - FastAPI startup script
- `api/requirements.txt` - Python dependencies
- `api/main.py` - FastAPI application

## Need Help?

If you encounter any issues:
1. Check the error messages in the terminal
2. Verify all prerequisites are installed
3. Make sure both servers (PHP and FastAPI) are running
4. Check that the database is set up correctly

