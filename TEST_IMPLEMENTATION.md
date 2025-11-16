# Implementation Test Guide

## ✅ Completed Implementation

### 1. Proxy Rotation (Claude's Logic)
- **File**: `api/proxies.py`
- **Status**: ✅ Updated to match Claude's exact logic
- **Features**:
  - Uses `SELECT ... FOR UPDATE` for atomic proxy selection
  - Prevents race conditions with transaction locking
  - Rotates proxies based on `last_used_at` (least recently used first)
  - Supports authentication (username/password)
  - Only active proxies are selected

### 2. Slug Redirect Display
- **File**: `includes/slug_helper.php` (new)
- **Status**: ✅ Added to all pages
- **Features**:
  - Shows current active slug
  - Lists all old slugs that redirect to current slug
  - Works for homepage, MP3, MP4, Stories, HOW, and Custom pages
  - Displayed at bottom of all pages

### 3. JWT Authentication
- **File**: `api/main.py`
- **Status**: ✅ Already implemented and verified
- **Features**:
  - `/search` and `/download` endpoints require authentication
  - Supports two methods:
    - `X-Internal-Key` header (for PHP backend)
    - `Bearer` token in `Authorization` header (JWT)
  - Prevents direct access without valid token

## 🧪 Testing Instructions

### Test 1: Proxy Rotation
```bash
# 1. Add dummy proxies to database
mysql -u root -p tiktokio.mobi << EOF
INSERT INTO api_proxies (provider_key, proxy_uri, auth_username, auth_password, is_active, notes) VALUES
('ytdlp', 'http://proxy1.example.com:8080', 'user1', 'pass1', 1, 'DUMMY TEST PROXY 1'),
('ytdlp', 'http://proxy2.example.com:8080', 'user2', 'pass2', 1, 'DUMMY TEST PROXY 2'),
('ytdlp', 'http://proxy3.example.com:8080', NULL, NULL, 1, 'DUMMY TEST PROXY 3');
EOF

# 2. Test proxy rotation
python api/test_proxy_rotation.py

# 3. Check proxy usage
mysql -u root -p tiktokio.mobi -e "SELECT id, proxy_uri, last_used_at FROM api_proxies WHERE provider_key='ytdlp' ORDER BY last_used_at;"
```

### Test 2: Slug Redirects
```bash
# 1. Test homepage redirects
# Visit: http://localhost/
# Should redirect to current home slug (e.g., /en5)

# 2. Test old homepage slugs
# Visit: http://localhost/en
# Should redirect to current home slug (e.g., /en5)

# 3. Test MP3 page redirects
# Visit: http://localhost/en1/youtube-to-mp3
# Should redirect to current MP3 slug (e.g., /en5/youtube-to-mp3)

# 4. Check slug display at bottom of page
# Should show: "Slug Redirects: Current: /en5 | Old slugs redirecting here: /en, /en1, /en2, /en3, /en4"
```

### Test 3: JWT Authentication
```bash
# 1. Test without token (should fail)
curl -X POST http://localhost:8000/search \
  -H "Content-Type: application/json" \
  -d '{"query": "test", "provider": "ytdlp"}'
# Expected: 401 Unauthorized

# 2. Get token
curl http://localhost:8000/token
# Response: {"token": "...", "expires_at": "...", "expires_in": 86400}

# 3. Test with token (should succeed)
curl -X POST http://localhost:8000/search \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{"query": "test", "provider": "ytdlp"}'
# Expected: 200 OK with search results
```

### Test 4: Language Switching
```bash
# 1. Visit homepage
# 2. Click language switcher button
# 3. Select different language (e.g., Spanish)
# 4. Verify:
#    - URL changes to Spanish slug
#    - Content changes to Spanish
#    - Slug redirects display shows Spanish slugs
```

## 📋 Database Schema Verification

### api_proxies Table
```sql
SELECT * FROM api_proxies WHERE provider_key='ytdlp';
```

### languages_home Table (Homepage Slugs)
```sql
SELECT l.code, lh.slug, lh.language_id 
FROM languages_home lh 
JOIN languages l ON lh.language_id = l.id;
```

### languages_home_redirects Table (Old Homepage Slugs)
```sql
SELECT l.code, lhr.old_slug, lhr.language_id 
FROM languages_home_redirects lhr 
JOIN languages l ON lhr.language_id = l.id;
```

### mp3_page_slugs Table
```sql
SELECT mps.slug, mps.status, mp.language_id, l.code
FROM mp3_page_slugs mps
JOIN mp3_pages mp ON mps.mp3_page_id = mp.id
JOIN languages l ON mp.language_id = l.id
ORDER BY mp.language_id, mps.status;
```

## 🔍 Verification Checklist

- [ ] Proxy rotation works (proxies rotate correctly)
- [ ] Slug redirects work (old slugs redirect to current)
- [ ] Slug display shows at bottom of pages
- [ ] JWT authentication blocks unauthorized access
- [ ] Language switching works correctly
- [ ] No UI or backend functionality broken
- [ ] All pages show slug redirect information

## 📝 Notes

1. **Proxy Rotation**: Only YTDLP provider uses proxies. Other providers don't use proxy rotation.

2. **Slug Redirects**: 
   - Homepage: `/` → `/en` → `/en1` → `/en2` → ... → `/en5` (current)
   - MP3 Page: `/youtube-to-mp3` → `/en/youtube-to-mp3` → `/en1/youtube-to-mp3` → ... → `/en5/youtube-to-mp3` (current)
   - Same pattern for MP4, Stories, HOW, and Custom pages

3. **JWT Authentication**: 
   - Frontend should call `/token` endpoint to get JWT token
   - Token is valid for 24 hours
   - PHP backend uses `X-Internal-Key` header instead

4. **Database Changes**:
   - `api/db.py`: Changed `autocommit=False` to support transactions
   - Added `conn.commit()` to `fetch_one()` and `execute()` functions

