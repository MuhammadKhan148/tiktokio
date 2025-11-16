# Complete Verification Checklist

## ✅ Implementation Status

### 1. Proxy Rotation (Claude's Logic)
- [x] Updated `api/proxies.py` with Claude's exact logic
- [x] Uses `SELECT ... FOR UPDATE` for atomic locking
- [x] Updates `last_used_at` within transaction
- [x] Commits transaction properly
- [x] Database connection supports transactions (`autocommit=False`)

### 2. Admin Panel - Proxy Management
**Location:** `/admin/api.php`

**Features to Verify:**
- [ ] Form to add new YTDLP proxy exists
- [ ] Fields: Label, Proxy URI, Username, Password
- [ ] "Add" button works
- [ ] Proxy list table shows:
  - ID
  - Label
  - Proxy URI
  - Status (Active/Disabled)
  - Last Used timestamp
  - Actions (Enable/Disable, Delete)
- [ ] Can toggle proxy active/inactive
- [ ] Can delete proxies

**Screenshot Areas:**
1. Proxy add form (lines 311-332 in admin/api.php)
2. Proxy list table (lines 334-377)

### 3. Slug Redirect Display
**Location:** Bottom of all pages

**Files Modified:**
- [x] `includes/slug_helper.php` - Helper functions created
- [x] `index.php` - Display added
- [x] `includes/footer.php` - Display added
- [x] `custom-page.php` - Display added

**What to Check:**
- [ ] Homepage shows slug redirects at bottom
- [ ] MP3 page shows slug redirects at bottom
- [ ] MP4/Stories page shows slug redirects at bottom
- [ ] HOW page shows slug redirects at bottom
- [ ] Custom pages show slug redirects at bottom
- [ ] Display format: "Slug Redirects: Current: /en5 | Old slugs redirecting here: /en, /en1, /en2..."

### 4. Slug Redirect Functionality
**Router:** `router.php` (already working, not modified)

**Test Cases:**
- [ ] `/` redirects to current home slug (e.g., `/en5`)
- [ ] `/en` redirects to current home slug (301)
- [ ] `/en1` redirects to current home slug (301)
- [ ] `/en2` redirects to current home slug (301)
- [ ] `/en1/youtube-to-mp3` redirects to current MP3 slug (301)
- [ ] `/en2/youtube-to-mp3` redirects to current MP3 slug (301)
- [ ] All redirects are 301 (permanent)

### 5. Language Switching
- [ ] Language switcher button works
- [ ] URL changes to correct language slug
- [ ] Content changes to selected language
- [ ] Slug redirects display updates for new language

## 🧪 Testing Steps

### Step 1: Test Proxy Rotation
```bash
# Run test script
python test_all_functionality.py

# Expected output:
# ✓ Database Connection: PASS
# ✓ Table Structure: PASS
# ✓ Add Dummy Proxies: PASS
# ✓ Proxy Rotation: PASS
```

### Step 2: Test Admin Panel
1. Navigate to: `http://localhost/admin/api.php`
2. Scroll to "YTDLP Rotating Proxies" section
3. Verify form exists with fields:
   - Label
   - Proxy URI
   - Username
   - Password
   - Add button
4. Add a test proxy:
   - Label: "Test Proxy 1"
   - URI: "http://test.proxy.com:8080"
   - Click "Add"
5. Verify proxy appears in table below
6. Check "Last Used" column updates after using proxy

### Step 3: Test Slug Redirects
1. Visit homepage: `http://localhost/`
2. Should redirect to current slug (e.g., `/en5`)
3. Visit old slug: `http://localhost/en`
4. Should redirect to current slug (301)
5. Check browser network tab for 301 status
6. Scroll to bottom of page
7. Verify slug redirect display shows:
   ```
   Slug Redirects: Current: /en5 | Old slugs redirecting here: /en, /en1, /en2, /en3, /en4
   ```

### Step 4: Test Language Switching
1. On homepage, find language switcher
2. Click to open language dropdown
3. Select different language (e.g., Spanish)
4. Verify:
   - URL changes to Spanish slug
   - Content is in Spanish
   - Slug redirects display shows Spanish slugs

## 📸 Screenshot Checklist

### Admin Panel Screenshots Needed:
1. **Proxy Management Section**
   - Full form with all fields visible
   - Proxy list table showing proxies
   - "Last Used" column with timestamps

2. **After Adding Proxy**
   - Success message
   - New proxy in table
   - All fields populated correctly

### Frontend Screenshots Needed:
1. **Homepage Bottom**
   - Slug redirects display visible
   - Shows current slug
   - Shows old slugs list

2. **MP3 Page Bottom**
   - Slug redirects display visible
   - Shows current MP3 slug
   - Shows old MP3 slugs

3. **Language Switcher**
   - Dropdown open
   - Multiple languages visible
   - Current language highlighted

## 🔍 Database Verification Queries

### Check Proxies
```sql
SELECT id, proxy_label, proxy_uri, is_active, last_used_at 
FROM api_proxies 
WHERE provider_key='ytdlp' 
ORDER BY last_used_at DESC;
```

### Check Homepage Slugs
```sql
-- Current slug
SELECT l.code, lh.slug 
FROM languages_home lh 
JOIN languages l ON lh.language_id = l.id;

-- Old slugs
SELECT l.code, lhr.old_slug 
FROM languages_home_redirects lhr 
JOIN languages l ON lhr.language_id = l.id;
```

### Check MP3 Page Slugs
```sql
SELECT mps.slug, mps.status, l.code
FROM mp3_page_slugs mps
JOIN mp3_pages mp ON mps.mp3_page_id = mp.id
JOIN languages l ON mp.language_id = l.id
WHERE l.code = 'en'
ORDER BY mps.status DESC, mps.slug;
```

## ✅ Success Criteria

All tests must pass:
- [x] Proxy rotation code matches Claude's logic exactly
- [ ] Admin panel has working proxy add form
- [ ] Proxies can be added via admin panel
- [ ] Proxy rotation works (different proxies used)
- [ ] Slug redirects work (301 redirects)
- [ ] Slug display shows at bottom of all pages
- [ ] Language switching works
- [ ] No UI or backend functionality broken

