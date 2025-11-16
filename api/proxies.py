from __future__ import annotations

import logging
from typing import Optional
from urllib.parse import urlparse, urlunparse

from .db import db_connection

logger = logging.getLogger(__name__)


class ProxyRotator:
    """Round-robin proxy selector with atomic updates for thread-safe rotation."""

    def __init__(self, provider_key: str):
        self.provider_key = provider_key

    def next_proxy(self) -> Optional[str]:
        """
        Get the next available proxy for this provider.
        Uses atomic SELECT ... FOR UPDATE to prevent race conditions.
        """
        row_data = None
        
        with db_connection() as conn:
            with conn.cursor() as cursor:
                # Use FOR UPDATE to lock the row during selection
                # This prevents multiple requests from selecting the same proxy
                cursor.execute(
                    """
                    SELECT id, proxy_uri, auth_username, auth_password 
                    FROM api_proxies 
                    WHERE provider_key=%s AND is_active=1 
                    ORDER BY COALESCE(last_used_at, '1970-01-01') ASC, id ASC 
                    LIMIT 1
                    FOR UPDATE
                    """,
                    (self.provider_key,),
                )
                row_data = cursor.fetchone()
                
                if not row_data:
                    logger.warning(f"No active proxies found for provider: {self.provider_key}")
                    return None
                
                # Update last_used_at atomically within the same transaction
                cursor.execute(
                    "UPDATE api_proxies SET last_used_at=NOW() WHERE id=%s", 
                    (row_data["id"],)
                )
                
                # Commit the transaction to release the lock
                conn.commit()
                
                logger.debug(f"Selected proxy ID {row_data['id']} for provider {self.provider_key}")

        return format_proxy(row_data)

    def get_proxy_count(self) -> int:
        """Get the count of active proxies for this provider."""
        with db_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT COUNT(*) AS count FROM api_proxies WHERE provider_key=%s AND is_active=1",
                    (self.provider_key,),
                )
                result = cursor.fetchone()
                conn.commit()  # Commit read operation
                return int(result["count"]) if result else 0


def format_proxy(row) -> str:
    proxy_uri: str = row["proxy_uri"]
    username = row.get("auth_username")
    password = row.get("auth_password")

    if username and password:
        parsed = urlparse(proxy_uri)
        netloc = parsed.netloc
        if "@" not in netloc:
            auth = f"{username}:{password}"
            host = parsed.hostname or ""
            if parsed.port:
                host = f"{host}:{parsed.port}"
            netloc = f"{auth}@{host}"
            proxy_uri = urlunparse(
                (parsed.scheme, netloc, parsed.path or "", parsed.params, parsed.query, parsed.fragment)
            )
    return proxy_uri
