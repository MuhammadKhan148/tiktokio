# Complete Context for Claude AI

## Quick Summary
I have a YouTube downloader website with:
- Multi-language support (English, Spanish, French, etc.)
- DMCA-resistant slug system (already working - DO NOT MODIFY)
- Rotating proxy system for YTDLP (just implemented)
- JWT authentication for /search and /download (just implemented)

## What Was Just Implemented

### 1. Proxy Rotation System
- **File:** `api/proxies.py` - Thread-safe proxy rotation with FOR UPDATE lock
- **File:** `api/providers/ytdlp_provider.py` - Enhanced logging for proxy usage
- **File:** `api/schema_proxies.sql` - Database schema for proxy tables
- **File:** `admin/proxy_management.php` - Admin interface to manage proxies
- **File:** `api/test_proxy_rotation.py` - Test script to verify rotation

**How it works:**
- Only YTDLP provider uses proxies (not other providers)
- Round-robin rotation: least recently used proxy gets selected next
- Thread-safe with `SELECT ... FOR UPDATE` to prevent race conditions
- Supports authentication (username + password)
- Admin can enable/disable proxies dynamically

### 2. JWT Authentication
- **File:** `api/main.py` - Modified `require_internal_key()` to validate JWT tokens
- **File:** `api/requirements.txt` - Added `pyjwt>=2.8.0`

**/search and /download endpoints now require:**
- JWT token in Authorization header (`Bearer <token>`), OR
- X-Internal-Key header (for PHP backend calls)

## Database Info
```
Host: localhost
Database: tiktokio.mobi
User: root
Password: Aakashkkkkkkkkkk1!
```

## Setup Commands

### Step 1: Install PyJWT
```bash
cd D:\100DaysPython\updated.lol\tiktokio.lol
.\api\venv\Scripts\activate
pip install pyjwt>=2.8.0
```

### Step 2: Create Proxy Tables
```bash
mysql -u root -p'Aakashkkkkkkkkkk1!' tiktokio.mobi < api/schema_proxies.sql
```

### Step 3: Test Proxy Rotation
```bash
python api/test_proxy_rotation.py
```

**Or run all at once:**
```bash
.\SETUP_PROXY_SYSTEM.bat
```

## Slug System (DO NOT MODIFY!)

**CRITICAL WARNING:** The slug/routing system is already implemented and working. Previous attempts to modify it broke the UI and backend. DO NOT TOUCH:
- `router.php`
- `php_router.php`
- Database tables: `languages_home`, `languages_home_redirects`, `mp3_page_slugs`, `mp4_page_slugs`, etc.

**How slug system works:**

### Homepage Slugs
- English: `/` → `/en` → `/en1` → `/en2` → ... → `/en5` (current)
- Spanish: `/es` → `/es1` → `/es2` → ... → `/es4` (current)
- All old slugs automatically 301 redirect to current slug

### Inner Page Slugs (MP3, MP4, etc.)
- English MP3: `/youtube-to-mp3` → `/en/youtube-to-mp3` → `/en1/youtube-to-mp3` → ... → `/en5/youtube-to-mp3`
- All old slugs automatically 301 redirect to current slug

**This is already working! Don't change it!**

## Admin Panels

### Main Admin
- URL: http://localhost:8000/admin/login.php
- Username: `admin`
- Password: `Admin@2025!`

### Proxy Management
- URL: http://localhost:8000/admin/proxy_management.php
- Add/remove/enable/disable proxies
- Reset usage times for testing

## Testing Checklist

### ✅ Test 1: Proxy Rotation
```bash
# Run test script
cd D:\100DaysPython\updated.lol\tiktokio.lol
python api/test_proxy_rotation.py

# Expected output:
# ✓ Active proxies available: 3
# Request #1: proxy1.example.com:8080
# Request #2: proxy2.example.com:8080
# Request #3: proxy3.example.com:8080
# ✅ SUCCESS: Proxies are rotating correctly!
```

### ✅ Test 2: Language Switching
```bash
# Start PHP server
cd D:\100DaysPython\updated.lol\tiktokio.lol
php -S localhost:8000 php_router.php

# Open in browser:
http://localhost:8000/

# Steps:
1. Click language dropdown
2. Select Spanish (Español)
3. Verify page content changes to Spanish
4. Take screenshot
5. Test in incognito mode
6. Test in normal mode (check for cache issues)
```

### ✅ Test 3: JWT Authentication
```bash
# Start FastAPI
cd D:\100DaysPython\updated.lol\tiktokio.lol
.\start_fastapi.bat

# Test without auth (should fail with 401)
curl http://localhost:8001/search -X POST \
  -H "Content-Type: application/json" \
  -d "{\"query\": \"test\", \"limit\": 5}"

# Test with X-Internal-Key (should work)
curl http://localhost:8001/search -X POST \
  -H "Content-Type: application/json" \
  -H "X-Internal-Key: your-key-from-database" \
  -d "{\"query\": \"test\", \"limit\": 5}"
```

## Known Issues

### UI Distortion in Normal Browser
**Symptom:** UI looks fine in incognito but broken in normal browser  
**Cause:** Cached CSS/JS files  
**Fix:** Hard refresh (Ctrl + Shift + R) or clear browser cache

### Two FastAPI Apps
**Important:** Don't confuse these two separate apps:
1. **Download API** - Port 8001 (`api/main.py`)
2. **Translation API** - Port 8000 (`updated_frontend/client_frontend/backend/main.py`)

## File Structure

```
tiktokio.lol/
├── api/
│   ├── main.py                         # ✅ Modified: JWT auth
│   ├── proxies.py                      # ✅ Modified: FOR UPDATE lock
│   ├── requirements.txt                # ✅ Modified: Added pyjwt
│   ├── schema_proxies.sql              # ✅ New: Proxy tables
│   ├── test_proxy_rotation.py          # ✅ New: Test script
│   └── providers/
│       └── ytdlp_provider.py           # ✅ Modified: Logging
├── admin/
│   ├── login.php                       # Existing
│   └── proxy_management.php            # ✅ New: Proxy admin
├── router.php                          # ⚠️ DO NOT MODIFY
├── php_router.php                      # ⚠️ DO NOT MODIFY
├── SETUP_PROXY_SYSTEM.bat              # ✅ New: Setup script
├── IMPLEMENTATION_SUMMARY_FOR_CLAUDE.md # ✅ New: Full docs
└── PROMPT_FOR_CLAUDE.md                # ✅ New: This file
```

## Code Examples

### Add Proxy via SQL
```sql
INSERT INTO api_proxies (provider_key, proxy_uri, auth_username, auth_password, is_active, notes) 
VALUES ('ytdlp', 'http://your-proxy.com:8080', 'username', 'password', 1, 'Production proxy');
```

### Check Proxy Rotation Status
```sql
SELECT 
    id,
    proxy_uri,
    is_active,
    last_used_at,
    TIMESTAMPDIFF(SECOND, last_used_at, NOW()) as seconds_ago
FROM api_proxies
WHERE provider_key = 'ytdlp'
ORDER BY last_used_at ASC;
```

### Generate JWT Token (Python)
```python
import jwt
from datetime import datetime, timedelta

jwt_secret = 'your-jwt-secret-from-database'

payload = {
    'user_id': 1,
    'exp': datetime.utcnow() + timedelta(hours=24)
}

token = jwt.encode(payload, jwt_secret, algorithm='HS256')
print(token)
```

### Test Proxy Rotation (Python)
```python
from api.proxies import ProxyRotator

rotator = ProxyRotator('ytdlp')
for i in range(5):
    proxy = rotator.next_proxy()
    print(f"Request {i+1}: {proxy}")
```

## What to Ask Claude

### If you need help with proxies:
"I've implemented a proxy rotation system for YTDLP. See `IMPLEMENTATION_SUMMARY_FOR_CLAUDE.md` for full context. [Your specific question]"

### If you need help with slugs:
"I have a slug system that's already working (DO NOT MODIFY IT). It handles DMCA redirects. See `IMPLEMENTATION_SUMMARY_FOR_CLAUDE.md` for context. [Your specific question]"

### If you need help with language switching:
"I have a multi-language system with translation API on port 8000. See `IMPLEMENTATION_SUMMARY_FOR_CLAUDE.md` for context. [Your specific question]"

## Important Reminders for Claude

1. ⚠️ **DO NOT modify** `router.php`, `php_router.php`, or slug-related database tables
2. ✅ **Proxy system** is for YTDLP only (not other providers)
3. ✅ **JWT auth** is required for `/search` and `/download` endpoints
4. ⚠️ **Two FastAPI apps** running on different ports (8000 = translation, 8001 = download)
5. ⚠️ **UI distortion** in normal browser is a cache issue (hard refresh fixes it)

## Next Steps

1. ✅ Run setup script: `.\SETUP_PROXY_SYSTEM.bat`
2. ⏳ Test proxy rotation: `python api/test_proxy_rotation.py`
3. ⏳ Test language switching in browser
4. ⏳ Add real proxies via admin panel
5. ⏳ Test JWT authentication
6. ⏳ Deploy to production

---

**Last Updated:** 2024  
**Status:** Implementation complete, ready for testing

**Full Documentation:** See `IMPLEMENTATION_SUMMARY_FOR_CLAUDE.md`

