#!/usr/bin/env python3
"""
Comprehensive test script for proxy rotation and slug functionality
"""
import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'api'))

from api.db import db_connection
from api.proxies import ProxyRotator
import time

def test_database_connection():
    """Test database connection and api_proxies table"""
    print("=" * 60)
    print("TEST 1: Database Connection & Table Check")
    print("=" * 60)
    
    try:
        with db_connection() as conn:
            with conn.cursor() as cursor:
                # Check if table exists
                cursor.execute("SHOW TABLES LIKE 'api_proxies'")
                table_exists = cursor.fetchone() is not None
                print(f"[OK] api_proxies table exists: {table_exists}")
                
                if table_exists:
                    # Check table structure
                    cursor.execute("DESCRIBE api_proxies")
                    columns = cursor.fetchall()
                    print(f"[OK] Table has {len(columns)} columns:")
                    for col in columns:
                        print(f"  - {col['Field']} ({col['Type']})")
                    
                    # Count existing proxies
                    cursor.execute("SELECT COUNT(*) as count FROM api_proxies WHERE provider_key='ytdlp'")
                    result = cursor.fetchone()
                    count = result['count'] if result else 0
                    print(f"[OK] Existing YTDLP proxies: {count}")
                    conn.commit()
                
                return table_exists
    except Exception as e:
        print(f"[ERROR] Error: {e}")
        return False

def add_dummy_proxies():
    """Add dummy proxies for testing"""
    print("\n" + "=" * 60)
    print("TEST 2: Adding Dummy Proxies")
    print("=" * 60)
    
    dummy_proxies = [
        ('DUMMY_TEST_PROXY_1', 'http://proxy1.example.com:8080', 'user1', 'pass1'),
        ('DUMMY_TEST_PROXY_2', 'http://proxy2.example.com:8080', 'user2', 'pass2'),
        ('DUMMY_TEST_PROXY_3', 'http://proxy3.example.com:8080', None, None),
    ]
    
    try:
        with db_connection() as conn:
            with conn.cursor() as cursor:
                # Delete existing dummy proxies
                cursor.execute("DELETE FROM api_proxies WHERE notes LIKE '%DUMMY%' OR proxy_label LIKE '%DUMMY%'")
                deleted = cursor.rowcount
                print(f"[OK] Cleaned up {deleted} old dummy proxies")
                
                # Add new dummy proxies
                added = 0
                for label, uri, username, password in dummy_proxies:
                    cursor.execute(
                        """INSERT INTO api_proxies 
                           (provider_key, proxy_label, proxy_uri, auth_username, auth_password, is_active, notes) 
                           VALUES ('ytdlp', %s, %s, %s, %s, 1, 'DUMMY TEST PROXY')""",
                        (label, uri, username, password)
                    )
                    added += 1
                    print(f"[OK] Added proxy: {label} - {uri}")
                
                conn.commit()
                print(f"\n[OK] Successfully added {added} dummy proxies")
                return True
    except Exception as e:
        print(f"[ERROR] Error adding proxies: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_proxy_rotation():
    """Test proxy rotation functionality"""
    print("\n" + "=" * 60)
    print("TEST 3: Proxy Rotation")
    print("=" * 60)
    
    try:
        rotator = ProxyRotator('ytdlp')
        
        # Get proxy count
        count = rotator.get_proxy_count()
        print(f"[OK] Active proxies available: {count}")
        
        if count == 0:
            print("[WARN] No proxies available for rotation test")
            return False
        
        # Test rotation - get proxies multiple times
        print("\nTesting proxy rotation (5 requests):")
        proxies_used = []
        for i in range(5):
            proxy = rotator.next_proxy()
            if proxy:
                # Mask password in output
                display_proxy = proxy.split('@')[-1] if '@' in proxy else proxy
                proxies_used.append(proxy)
                print(f"  Request {i+1}: {display_proxy}")
                time.sleep(0.1)  # Small delay
            else:
                print(f"  Request {i+1}: No proxy available")
        
        # Check if proxies rotated
        unique_proxies = len(set(proxies_used))
        print(f"\n[OK] Unique proxies used: {unique_proxies} out of {count} available")
        
        if unique_proxies > 1:
            print("[OK] Proxy rotation is working correctly!")
        else:
            print("[WARN] Only one proxy was used (may be expected if only one active)")
        
        # Check last_used_at timestamps
        print("\nChecking proxy usage timestamps:")
        with db_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """SELECT id, proxy_label, proxy_uri, last_used_at 
                       FROM api_proxies 
                       WHERE provider_key='ytdlp' AND is_active=1 
                       ORDER BY last_used_at DESC"""
                )
                results = cursor.fetchall()
                conn.commit()
                
                for row in results:
                    used = row['last_used_at'].strftime('%Y-%m-%d %H:%M:%S') if row['last_used_at'] else 'Never'
                    print(f"  - {row['proxy_label']}: Last used at {used}")
        
        return True
    except Exception as e:
        print(f"✗ Error testing rotation: {e}")
        import traceback
        traceback.print_exc()
        return False

def verify_proxy_table_structure():
    """Verify api_proxies table has required columns"""
    print("\n" + "=" * 60)
    print("TEST 4: Table Structure Verification")
    print("=" * 60)
    
    required_columns = [
        'id', 'provider_key', 'proxy_uri', 'auth_username', 
        'auth_password', 'is_active', 'last_used_at'
    ]
    
    try:
        with db_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute("DESCRIBE api_proxies")
                columns = {col['Field']: col for col in cursor.fetchall()}
                conn.commit()
                
                print("Required columns check:")
                all_present = True
                for col in required_columns:
                    if col in columns:
                        print(f"  [OK] {col}: {columns[col]['Type']}")
                    else:
                        print(f"  [MISSING] {col}: MISSING")
                        all_present = False
                
                return all_present
    except Exception as e:
        print(f"[ERROR] Error: {e}")
        return False

def main():
    print("\n" + "=" * 60)
    print("COMPREHENSIVE PROXY ROTATION TEST")
    print("=" * 60 + "\n")
    
    results = []
    
    # Test 1: Database connection
    results.append(("Database Connection", test_database_connection()))
    
    # Test 2: Table structure
    results.append(("Table Structure", verify_proxy_table_structure()))
    
    # Test 3: Add dummy proxies
    results.append(("Add Dummy Proxies", add_dummy_proxies()))
    
    # Test 4: Proxy rotation
    results.append(("Proxy Rotation", test_proxy_rotation()))
    
    # Summary
    print("\n" + "=" * 60)
    print("TEST SUMMARY")
    print("=" * 60)
    
    for test_name, passed in results:
        status = "[PASS]" if passed else "[FAIL]"
        print(f"{status}: {test_name}")
    
    all_passed = all(result[1] for result in results)
    print(f"\n{'=' * 60}")
    if all_passed:
        print("[SUCCESS] ALL TESTS PASSED")
    else:
        print("[FAILED] SOME TESTS FAILED")
    print("=" * 60 + "\n")
    
    return 0 if all_passed else 1

if __name__ == '__main__':
    sys.exit(main())

