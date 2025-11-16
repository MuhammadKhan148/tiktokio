<?php

require_once __DIR__ . '/config.php';

if (!function_exists('get_site_settings_cached')) {
    $GLOBALS['_site_settings_cache'] = null;
    function get_site_settings_cached($conn) {
        global $_site_settings_cache;
        if ($_site_settings_cache !== null) {
            return $_site_settings_cache;
        }

        $res = $conn->query("SELECT * FROM site_settings LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $_site_settings_cache = $res->fetch_assoc();
        } else {
            $_site_settings_cache = [
                'site_name' => 'TikTok Downloader',
                'fastapi_base_url' => 'http://127.0.0.1:8000',
                'fastapi_auth_key' => 'change-me',
                'jwt_secret' => 'change-me',
                'active_api_provider' => 'ytdlp',
            ];
        }
        return $_site_settings_cache;
    }
}

if (!function_exists('refresh_site_settings_cache')) {
    function refresh_site_settings_cache() {
        $GLOBALS['_site_settings_cache'] = null;
    }
}

if (!function_exists('fetch_provider_record')) {
    function fetch_provider_record($conn, $provider_key) {
        if (!$provider_key) {
            return null;
        }
        $stmt = $conn->prepare("SELECT provider_key, is_enabled FROM api_providers WHERE provider_key=? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $provider_key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('get_first_enabled_provider_key')) {
    function get_first_enabled_provider_key($conn, array $exclude = []) {
        $enabled = [];
        $res = $conn->query("SELECT provider_key FROM api_providers WHERE is_enabled=1");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $enabled[] = $row['provider_key'];
            }
            $res->free();
        }
        $priority = ['ytdlp', 'cobalt', 'iframe'];
        usort($enabled, function ($a, $b) use ($priority) {
            $posA = array_search($a, $priority, true);
            $posB = array_search($b, $priority, true);
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;
            if ($posA === $posB) {
                return strcmp($a, $b);
            }
            return $posA <=> $posB;
        });
        foreach ($enabled as $provider_key) {
            if (in_array($provider_key, $exclude, true)) {
                continue;
            }
            return $provider_key;
        }
        return null;
    }
}

if (!function_exists('get_active_api_provider')) {
    function get_active_api_provider($conn, $forceRefresh = false) {
        if ($forceRefresh) {
            refresh_site_settings_cache();
        }
        $settings = get_site_settings_cached($conn);
        $preferred = $settings['active_api_provider'] ?? 'ytdlp';
        $record = fetch_provider_record($conn, $preferred);
        if ($record && !empty($record['is_enabled'])) {
            return $preferred;
        }

        $fallback = get_first_enabled_provider_key($conn, [$preferred]);
        if ($fallback) {
            if ($fallback !== $preferred) {
                $stmt = $conn->prepare("UPDATE site_settings SET active_api_provider=? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $fallback);
                    $stmt->execute();
                    $stmt->close();
                    refresh_site_settings_cache();
                }
            }
            return $fallback;
        }

        return $preferred;
    }
}

if (!function_exists('media_api_call')) {
    function media_api_call($conn, $endpoint, array $payload) {
        $settings = get_site_settings_cached($conn);
        $baseUrl = rtrim($settings['fastapi_base_url'] ?? 'http://127.0.0.1:8000', '/');
        $authKey = $settings['fastapi_auth_key'] ?? '';
        
        // Get JWT token as fallback if auth key is not set or empty
        $jwtToken = '';
        if (empty($authKey) || $authKey === 'change-me') {
            $jwtToken = get_api_token($conn);
        }

        $url = $baseUrl . $endpoint;
        $ch = curl_init($url);
        
        // Longer timeout for download endpoint
        $timeout = ($endpoint === '/download') ? 180 : 120;
        
        // Build headers - prefer X-Internal-Key, fallback to JWT token
        $headers = [
            'Content-Type: application/json',
            'User-Agent: TikTokIO-MediaBridge/1.0',
        ];
        
        if (!empty($authKey) && $authKey !== 'change-me') {
            $headers[] = 'X-Internal-Key: ' . $authKey;
        } elseif (!empty($jwtToken)) {
            $headers[] = 'Authorization: Bearer ' . $jwtToken;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => $curlError];
        }

        // Log the raw response for debugging
        error_log("FastAPI Response (Status $statusCode): " . substr($response, 0, 500));

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response from backend',
                'body' => substr($response, 0, 200),
                'status' => $statusCode
            ];
        }

        // If we got 401 and were using X-Internal-Key, try with JWT token
        if ($statusCode === 401 && !empty($authKey) && $authKey !== 'change-me' && empty($jwtToken)) {
            // Retry with JWT token
            $jwtToken = get_api_token($conn);
            if (!empty($jwtToken)) {
                $ch = curl_init($url);
                $headers = [
                    'Content-Type: application/json',
                    'User-Agent: TikTokIO-MediaBridge/1.0',
                    'Authorization: Bearer ' . $jwtToken,
                ];
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => $headers,
                ]);
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [
                        'success' => false,
                        'error' => 'Invalid JSON response from backend',
                        'body' => substr($response, 0, 200),
                        'status' => $statusCode
                    ];
                }
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'success' => false,
                'error' => $decoded['detail'] ?? ($decoded['error'] ?? 'FastAPI error'),
                'status' => $statusCode,
                'details' => $decoded
            ];
        }

        return ['success' => true, 'data' => $decoded];
    }
}

if (!function_exists('media_api_search')) {
    function media_api_search($conn, $query, $provider, $limit = 5, $preferAudio = true) {
        $payload = [
            'query' => $query,
            'provider' => $provider,
            'limit' => $limit,
            'prefer_audio' => $preferAudio,
        ];
        return media_api_call($conn, '/search', $payload);
    }
}

if (!function_exists('media_api_download')) {
    function media_api_download(
        $conn,
        $url,
        $provider,
        $format,
        $quality,
        $titleOverride,
        $siteName = null
    ) {
        $payload = [
            'url' => $url,
            'provider' => $provider,
            'format' => $format,
            'quality' => $quality,
            'title_override' => $titleOverride,
            'site_name' => $siteName,
        ];
        return media_api_call($conn, '/download', $payload);
    }
}

if (!function_exists('get_api_token')) {
    /**
     * Get or generate a JWT token for frontend API access.
     * Tokens are stored in session and automatically refreshed when expired.
     * 
     * @param mysqli $conn Database connection
     * @return string JWT token
     */
    function get_api_token($conn) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if we have a valid token in session
        if (isset($_SESSION['api_token']) && isset($_SESSION['api_token_expires'])) {
            // Token is still valid (with 1 hour buffer)
            if ($_SESSION['api_token_expires'] > (time() + 3600)) {
                return $_SESSION['api_token'];
            }
        }
        
        // Fetch new token from FastAPI
        $settings = get_site_settings_cached($conn);
        $baseUrl = rtrim($settings['fastapi_base_url'] ?? 'http://127.0.0.1:8000', '/');
        $url = $baseUrl . '/token';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: TikTokIO-MediaBridge/1.0',
            ],
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Failed to fetch JWT token: " . $curlError);
            return '';
        }
        
        if ($statusCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['token'])) {
                // Start session if not already started (needed for storing token)
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['api_token'] = $data['token'];
                // Store expiration time (24 hours from now, or use expires_at if provided)
                if (isset($data['expires_at'])) {
                    $_SESSION['api_token_expires'] = strtotime($data['expires_at']);
                } else {
                    $_SESSION['api_token_expires'] = time() + ($data['expires_in'] ?? 86400);
                }
                return $data['token'];
            }
        } else {
            error_log("Failed to get JWT token. Status: $statusCode, Response: " . substr($response, 0, 200));
        }
        
        // Fallback: return empty string if token generation fails
        // The API will still work with X-Internal-Key for backend calls
        return '';
    }
}