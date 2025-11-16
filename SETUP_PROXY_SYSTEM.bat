@echo off
echo ========================================
echo YTDLP Proxy Rotation System Setup
echo ========================================
echo.

cd /d %~dp0

echo Step 1: Installing PyJWT dependency...
echo.
call api\venv\Scripts\activate.bat
pip install pyjwt>=2.8.0
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Failed to install PyJWT
    pause
    exit /b 1
)

echo.
echo Step 2: Creating proxy database tables...
echo.
echo NOTE: You'll be prompted for MySQL root password: Aakashkkkkkkkkkk1!
echo.
mysql -u root -p tiktokio.mobi < api\schema_proxies.sql
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Failed to create database tables
    echo.
    echo Make sure MySQL is running and password is correct
    pause
    exit /b 1
)

echo.
echo Step 3: Testing proxy rotation...
echo.
python api\test_proxy_rotation.py
if %ERRORLEVEL% NEQ 0 (
    echo WARNING: Proxy rotation test failed
    echo This is expected if you haven't added real proxies yet
)

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Add your real proxies via admin panel:
echo    http://localhost:8000/admin/proxy_management.php
echo.
echo 2. Or add proxies via SQL:
echo    INSERT INTO api_proxies (provider_key, proxy_uri, auth_username, auth_password, is_active) 
echo    VALUES ('ytdlp', 'http://your-proxy.com:8080', 'user', 'pass', 1);
echo.
echo 3. Test rotation again:
echo    python api\test_proxy_rotation.py
echo.
pause

