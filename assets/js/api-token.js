/**
 * Automatic JWT Token Management for FastAPI
 * 
 * This script automatically fetches and manages JWT tokens for API authentication.
 * Tokens are stored in sessionStorage and automatically refreshed when needed.
 */

(function() {
    'use strict';
    
    const TOKEN_STORAGE_KEY = 'api_jwt_token';
    const TOKEN_EXPIRY_KEY = 'api_jwt_token_expires';
    const TOKEN_ENDPOINT = '/api_token.php'; // PHP proxy endpoint
    
    /**
     * Get the current token from storage
     */
    function getStoredToken() {
        try {
            const token = sessionStorage.getItem(TOKEN_STORAGE_KEY);
            const expires = sessionStorage.getItem(TOKEN_EXPIRY_KEY);
            
            if (token && expires) {
                const expiryTime = parseInt(expires, 10);
                // Check if token is still valid (with 1 hour buffer)
                if (expiryTime > (Date.now() + 3600000)) {
                    return token;
                }
            }
        } catch (e) {
            console.warn('Failed to read token from storage:', e);
        }
        return null;
    }
    
    /**
     * Store token in sessionStorage
     */
    function storeToken(token, expiresIn) {
        try {
            sessionStorage.setItem(TOKEN_STORAGE_KEY, token);
            const expiryTime = Date.now() + (expiresIn * 1000);
            sessionStorage.setItem(TOKEN_EXPIRY_KEY, expiryTime.toString());
        } catch (e) {
            console.warn('Failed to store token:', e);
        }
    }
    
    /**
     * Fetch a new token from the API
     */
    async function fetchToken() {
        try {
            const response = await fetch(TOKEN_ENDPOINT, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.token) {
                    storeToken(data.token, data.expires_in || 86400);
                    return data.token;
                }
            }
        } catch (e) {
            console.warn('Failed to fetch API token:', e);
        }
        return null;
    }
    
    /**
     * Get a valid token, fetching a new one if needed
     */
    async function getToken() {
        let token = getStoredToken();
        if (!token) {
            token = await fetchToken();
        }
        return token;
    }
    
    /**
     * Make an authenticated API request
     */
    async function authenticatedFetch(url, options = {}) {
        const token = await getToken();
        
        const headers = options.headers || {};
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch(url, {
            ...options,
            headers: headers,
        });
        
        // If we get a 401, try refreshing the token once
        if (response.status === 401 && token) {
            sessionStorage.removeItem(TOKEN_STORAGE_KEY);
            sessionStorage.removeItem(TOKEN_EXPIRY_KEY);
            const newToken = await fetchToken();
            if (newToken) {
                headers['Authorization'] = `Bearer ${newToken}`;
                return fetch(url, {
                    ...options,
                    headers: headers,
                });
            }
        }
        
        return response;
    }
    
    // Expose functions globally
    window.API_TOKEN = {
        getToken: getToken,
        fetchToken: fetchToken,
        authenticatedFetch: authenticatedFetch,
    };
    
    // Auto-fetch token on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', getToken);
    } else {
        getToken();
    }
})();

