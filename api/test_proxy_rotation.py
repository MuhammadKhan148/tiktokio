"""
Test script for proxy rotation system
Run this to verify that proxies are rotating correctly
"""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from api.proxies import ProxyRotator
from api.db import db_connection

def test_proxy_rotation():
    """Test that proxies rotate in round-robin fashion"""
    print("=" * 60)
    print("Testing YTDLP Proxy Rotation System")
    print("=" * 60)
    
    # Initialize rotator
    rotator = ProxyRotator('ytdlp')
    
    # Check proxy count
    count = rotator.get_proxy_count()
    print(f"\n[OK] Active proxies available: {count}")
    
    if count == 0:
        print("\n[WARNING] No active proxies found!")
        print("   Please run the SQL schema first:")
        print("   mysql -u root -p tiktokio.mobi < api/schema_proxies.sql")
        return False
    
    # Test rotation by fetching proxies multiple times
    print(f"\n[Testing rotation with {min(count * 2, 10)} requests]")
    print("-" * 60)
    
    proxies_used = []
    for i in range(min(count * 2, 10)):
        proxy = rotator.next_proxy()
        if proxy:
            # Hide credentials in output
            display_proxy = proxy.split('@')[-1] if '@' in proxy else proxy
            proxies_used.append(proxy)
            print(f"Request #{i+1}: {display_proxy}")
        else:
            print(f"Request #{i+1}: No proxy available")
    
    # Verify rotation
    print("\n" + "=" * 60)
    print("Rotation Analysis:")
    print("=" * 60)
    
    if len(set(proxies_used)) > 1:
        print("[SUCCESS] Proxies are rotating correctly!")
        print(f"   Used {len(set(proxies_used))} different proxies")
    elif len(set(proxies_used)) == 1 and count > 1:
        print("[WARNING] Only one proxy was used (expected rotation)")
    else:
        print("[OK] Only one proxy available, no rotation needed")
    
    # Show current state
    print("\n" + "=" * 60)
    print("Current Proxy State in Database:")
    print("=" * 60)
    
    with db_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT 
                    id,
                    proxy_uri,
                    is_active,
                    last_used_at,
                    TIMESTAMPDIFF(SECOND, last_used_at, NOW()) as seconds_ago
                FROM api_proxies
                WHERE provider_key = 'ytdlp'
                ORDER BY last_used_at ASC
            """)
            rows = cursor.fetchall()
            
            if rows:
                for row in rows:
                    status = "[Active]" if row['is_active'] else "[Inactive]"
                    last_used = row['last_used_at'] or 'Never'
                    seconds = row['seconds_ago'] if row['seconds_ago'] is not None else 'N/A'
                    print(f"ID {row['id']}: {row['proxy_uri']}")
                    print(f"  Status: {status} | Last used: {last_used} ({seconds}s ago)")
            else:
                print("No proxies found in database")
    
    return True

if __name__ == '__main__':
    try:
        success = test_proxy_rotation()
        sys.exit(0 if success else 1)
    except Exception as e:
        print(f"\n[ERROR] {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)

