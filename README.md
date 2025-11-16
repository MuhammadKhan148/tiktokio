# YouTube/TikTok Downloader - Multi-Language Platform

A powerful, multi-language video downloader supporting YouTube and TikTok with rotating proxy support, JWT authentication, and DMCA-resistant slug management.

## 🚀 Features

- ✅ **Multi-Platform Support** - Download from YouTube, TikTok, and more
- ✅ **21 Languages** - Full internationalization support
- ✅ **Rotating Proxy System** - Avoid rate limiting with automatic proxy rotation
- ✅ **JWT Authentication** - Secure API endpoints
- ✅ **DMCA-Resistant Slugs** - Dynamic URL management with 301 redirects
- ✅ **Multiple Formats** - MP3, MP4, WEBM, M4A, 3GP support
- ✅ **Quality Options** - From 64kbps to 320kbps audio, 360p to 4K video
- ✅ **Modern Admin Panel** - Easy proxy and settings management
- ✅ **Database-Driven** - MySQL backend for configuration and tracking

## 📋 Requirements

### Server Requirements
- **PHP 7.4+** (PHP 8.x recommended)
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Python 3.8+**
- **FFmpeg** (for video/audio conversion)

### PHP Extensions
- `mysqli`
- `curl`
- `json`
- `mbstring`

### Python Packages
See `api/requirements.txt`

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/your-repo-name.git
cd your-repo-name
```

### 2. Database Setup

```bash
# Create database
mysql -u root -p

CREATE DATABASE your_database_name;
CREATE USER 'your_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u root -p your_database_name < api/schema_proxies.sql
```

### 3. PHP Configuration

Copy and configure `includes/config.php`:

```php
<?php
$db_host = 'localhost';
$db_name = 'your_database_name';
$db_user = 'your_user';
$db_pass = 'your_password';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
```

### 4. Python API Setup

```bash
cd api

# Create virtual environment
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Create .env file
cp .env.example .env
```

Edit `api/.env`:

```env
FASTAPI_APP_NAME=Your App Name
FASTAPI_AUTH_KEY=your-secure-random-key-here
FASTAPI_DB_HOST=localhost
FASTAPI_DB_PORT=3306
FASTAPI_DB_NAME=your_database_name
FASTAPI_DB_USER=your_user
FASTAPI_DB_PASSWORD=your_password
FASTAPI_STORAGE_DIR=../uploads/api-cache
FASTAPI_DEFAULT_PROVIDER=ytdlp
FASTAPI_HTTP_USER_AGENT=Mozilla/5.0 (compatible; YourBot/1.0)
```

### 5. Update Database Settings

```sql
UPDATE site_settings 
SET fastapi_auth_key = 'your-secure-random-key-here',
    jwt_secret = 'your-jwt-secret-key',
    fastapi_base_url = 'http://127.0.0.1:8001'
WHERE id = 1;
```

**⚠️ IMPORTANT:** Make sure `fastapi_auth_key` in database matches `FASTAPI_AUTH_KEY` in `api/.env`

### 6. Start Services

```bash
# Terminal 1: Start FastAPI (Download API)
cd api
uvicorn main:app --host 127.0.0.1 --port 8001 --reload

# Terminal 2: Start PHP Server
php -S localhost:8000
```

## 🔧 Configuration

### Admin Panel Access

**URL:** http://localhost:8000/admin/login.php  
**Default Credentials:** 
- Username: `admin`
- Password: `Admin@2025!` (⚠️ Change this immediately!)

### Adding Proxies

1. Login to admin panel
2. Navigate to **YTDLP Settings** (http://localhost:8000/admin/ytdlp_settings.php)
3. Add proxy details:
   - **Proxy Label:** Optional friendly name
   - **Proxy URI:** `http://proxy.example.com:8080`
   - **Username/Password:** If proxy requires authentication
4. Click **Update**

### Proxy Rotation

Proxies automatically rotate based on "least recently used" algorithm:
- Each download uses a different proxy
- Prevents rate limiting
- Thread-safe with database locking
- Real-time usage tracking

## 📁 Project Structure

```
├── api/                          # FastAPI backend
│   ├── main.py                   # Main API application
│   ├── proxies.py                # Proxy rotation logic
│   ├── providers/                # Download providers
│   │   ├── ytdlp_provider.py     # YouTube-DL provider
│   │   └── ...
│   ├── requirements.txt          # Python dependencies
│   └── .env                      # Environment config (not in git)
├── admin/                        # Admin panel
│   ├── login.php                 # Admin login
│   ├── ytdlp_settings.php        # Proxy management
│   └── ...
├── includes/                     # PHP includes
│   ├── api_client.php            # API communication
│   ├── config.php                # Database config (not in git)
│   └── ...
├── assets/                       # Frontend assets
├── uploads/                      # Upload directory (not in git)
└── README.md                     # This file
```

## 🔐 Security

### Authentication

- **JWT Tokens:** Secure API endpoint access
- **X-Internal-Key:** PHP backend authentication
- **Admin Password:** Hashed in database

### Best Practices

1. **Change default passwords** immediately
2. **Use strong JWT secret** (64+ characters)
3. **Rotate API keys** regularly
4. **Use HTTPS** in production
5. **Restrict database access**
6. **Keep `.env` secure** (never commit!)

## 🌐 Multi-Language Support

### Supported Languages

English, Spanish, French, German, Italian, Portuguese, Russian, Japanese, Korean, Chinese, Arabic, Hindi, Turkish, Vietnamese, Thai, Indonesian, Polish, Dutch, Swedish, Norwegian, Danish

### Language Switching

Users can switch languages via dropdown menu. All content is stored in database and fully translatable.

## 📊 Slug System

### DMCA-Resistant URL Management

- **Current Slug:** `/en7/youtube-to-mp3`
- **Old Slugs:** `/en/youtube-to-mp3`, `/en1/youtube-to-mp3`, etc.
- **Redirect:** All old slugs → 301 redirect to current slug

### How It Works

1. Homepage slugs stored in `languages_home`
2. Old slugs stored in `languages_home_redirects`
3. Router automatically redirects old → new
4. SEO-friendly 301 permanent redirects
5. Easy rotation when needed

## 🧪 Testing

### Test Proxy Rotation

```bash
cd api
python test_proxy_rotation.py
```

### Test Download API

```bash
curl -X POST http://127.0.0.1:8001/health
```

## 📝 API Documentation

### Endpoints

- `GET /health` - Health check
- `POST /search` - Search for videos (requires auth)
- `POST /download` - Download video/audio (requires auth)
- `GET /media/{token}` - Serve downloaded media

### Authentication

**Option 1: X-Internal-Key (PHP Backend)**
```bash
curl -H "X-Internal-Key: your-key-here" \
     -X POST http://127.0.0.1:8001/search
```

**Option 2: JWT Token (Frontend)**
```bash
curl -H "Authorization: Bearer your-jwt-token" \
     -X POST http://127.0.0.1:8001/search
```

## 🐛 Troubleshooting

### "Authentication required" error

**Problem:** Frontend shows authentication error when downloading

**Solution:**
1. Check `site_settings.fastapi_auth_key` matches `api/.env` `FASTAPI_AUTH_KEY`
2. Update database:
   ```sql
   UPDATE site_settings SET fastapi_auth_key='your-key-from-env' WHERE id=1;
   ```

### Proxy not rotating

**Problem:** Same proxy used repeatedly

**Solution:**
1. Check proxies are active: `SELECT * FROM api_proxies WHERE is_active=1;`
2. Verify `last_used_at` is updating
3. Run test script: `python api/test_proxy_rotation.py`

### Download fails

**Problem:** Downloads timeout or fail

**Solution:**
1. Check FFmpeg is installed: `ffmpeg -version`
2. Check proxy connectivity
3. Check FastAPI logs: `tail -f api/logs/*.log`
4. Try without proxy first

## 📚 Documentation

- `README_IMPLEMENTATION.md` - Implementation details
- `SLUG_TEST_RESULTS.md` - Slug system testing
- `YTDLP_ENGINE_INTERFACE.md` - Admin interface docs
- `FINAL_COMPLETE_REPORT.md` - Complete feature report

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

[Specify your license here]

## ⚠️ Disclaimer

This tool is for educational purposes only. Ensure you comply with:
- YouTube Terms of Service
- TikTok Terms of Service
- Copyright laws in your jurisdiction
- DMCA regulations

Users are responsible for their use of this software.

## 🙏 Acknowledgments

- **yt-dlp** - YouTube download library
- **FastAPI** - Modern Python web framework
- **FFmpeg** - Multimedia processing

## 📧 Support

For issues and questions:
- GitHub Issues: [Your repo issues URL]
- Documentation: See `docs/` folder

---

**Made with ❤️ for the open-source community**
