from __future__ import annotations

import asyncio
import logging
import uuid
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from fastapi import Depends, FastAPI, Header, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, JSONResponse

from .db import get_site_profile
from .models import DownloadRequest, DownloadResponse, SearchRequest, SearchResponse
from .providers import build_provider
from .settings import settings
from .utils import (
    cleanup_artifacts,
    delete_manifest,
    expires_at,
    human_file_size,
    isoformat,
    read_manifest,
    sign_token,
    write_manifest,
)

logger = logging.getLogger("tiktokio.api")
logging.basicConfig(level=getattr(logging, settings.log_level.upper(), logging.INFO))

app = FastAPI(
    title=settings.app_name,
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)


async def require_internal_key(
    x_internal_key: Optional[str] = Header(default=None, convert_underscores=False),
    authorization: Optional[str] = Header(default=None),
):
    """
    JWT-based authentication guard for /search and /download endpoints.
    
    Requirements:
    - Must provide valid JWT token in Authorization header (Bearer token)
    - OR provide valid X-Internal-Key header
    
    This prevents direct access to /search and /download by unauthorized users.
    """
    # Check X-Internal-Key first (for PHP backend calls)
    if x_internal_key:
        profile = get_site_profile()
        expected_key = profile.get('fastapi_auth_key') or settings.fastapi_auth_key
        if x_internal_key == expected_key:
            logger.debug("Request authenticated via X-Internal-Key")
            return
    
    # Check JWT token (Bearer token)
    if authorization and authorization.startswith('Bearer '):
        token = authorization[7:]  # Remove "Bearer " prefix
        try:
            import jwt
            profile = get_site_profile()
            jwt_secret = profile.get('jwt_secret', 'change-me')
            
            # Decode and verify JWT
            payload = jwt.decode(token, jwt_secret, algorithms=['HS256'])
            logger.debug(f"Request authenticated via JWT for user: {payload.get('user_id', 'unknown')}")
            return
        except jwt.ExpiredSignatureError:
            raise HTTPException(
                status.HTTP_401_UNAUTHORIZED,
                detail="JWT token has expired"
            )
        except jwt.InvalidTokenError as e:
            raise HTTPException(
                status.HTTP_401_UNAUTHORIZED,
                detail=f"Invalid JWT token: {str(e)}"
            )
    
    # No valid authentication provided
    raise HTTPException(
        status.HTTP_401_UNAUTHORIZED,
        detail="Authentication required. Provide valid JWT token in Authorization header or X-Internal-Key"
    )


@app.on_event("startup")
async def _startup() -> None:
    asyncio.create_task(_cleanup_worker())


async def _cleanup_worker() -> None:
    while True:
        try:
            cleanup_artifacts()
        except Exception as exc:  # pragma: no cover
            logger.warning("Cleanup worker failed: %s", exc)
        await asyncio.sleep(settings.cleanup_frequency_seconds)


@app.get("/health")
async def healthcheck():
    profile = get_site_profile()
    return {
        "status": "ok",
        "app": settings.app_name,
        "default_provider": settings.default_provider,
        "storage": str(settings.storage_root),
        "site": profile.get('site_url'),
    }


@app.get("/token")
async def get_token():
    """
    Generate a JWT token for anonymous users.
    This allows frontend clients to authenticate with the API automatically.
    Tokens are valid for 24 hours by default.
    """
    import jwt
    from datetime import datetime, timedelta, timezone
    
    profile = get_site_profile()
    jwt_secret = profile.get('jwt_secret', 'change-me')
    
    # Generate a unique user ID for this session
    user_id = f"anon_{uuid.uuid4().hex[:16]}"
    
    # Token expires in 24 hours
    now = datetime.now(timezone.utc)
    exp = now + timedelta(hours=24)
    
    payload = {
        'user_id': user_id,
        'type': 'api_access',
        'iat': int(now.timestamp()),
        'exp': int(exp.timestamp()),
    }
    
    token = jwt.encode(payload, jwt_secret, algorithm='HS256')
    
    logger.debug(f"Generated JWT token for anonymous user: {user_id}")
    
    return {
        "token": token,
        "expires_at": exp.isoformat(),
        "expires_in": 86400,  # 24 hours in seconds
    }


@app.post("/search", response_model=SearchResponse)
async def search_media(payload: SearchRequest, _: None = Depends(require_internal_key)):
    site_profile = get_site_profile()
    provider = build_provider(payload.provider or settings.default_provider, site_profile)
    raw = await provider.search(payload)
    return SearchResponse(**raw)


@app.post("/download", response_model=DownloadResponse)
async def download_media(payload: DownloadRequest, _: None = Depends(require_internal_key)):
    site_profile = get_site_profile()
    provider = build_provider(payload.provider or settings.default_provider, site_profile)
    # Iframe provider now supports downloads via freeapi.cyou

    result = await provider.download(payload)
    token = uuid.uuid4().hex
    expiry = expires_at(settings.download_ttl_seconds)
    manifest = {
        "file_path": str(result.file_path),
        "file_name": result.file_name,
        "mime_type": result.mime_type,
        "metadata": result.metadata,
        "expires_at": isoformat(expiry),
    }
    write_manifest(token, manifest)
    signature = sign_token(token)

    return DownloadResponse(
        provider=provider.key,
        file_name=result.file_name,
        file_size_bytes=result.file_size,
        human_size=human_file_size(result.file_size),
        mime_type=result.mime_type,
        download_token=token,
        signature=signature,
        expires_at=expiry,
        metadata=result.metadata,
    )


@app.get("/media/{token}")
async def fetch_media(token: str, sig: Optional[str] = None):
    manifest = read_manifest(token)
    if not manifest:
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Expired download token")

    expected_sig = sign_token(token)
    if sig != expected_sig:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Signature mismatch")

    expires_at_str = manifest.get('expires_at')
    if expires_at_str:
        try:
            expires = datetime.fromisoformat(expires_at_str)
        except ValueError:
            expires = datetime.now(timezone.utc)
        if expires < datetime.now(timezone.utc):
            delete_manifest(token)
            raise HTTPException(status.HTTP_410_GONE, "Download token expired")

    file_path = Path(manifest['file_path'])
    if not file_path.exists():
        delete_manifest(token)
        raise HTTPException(status.HTTP_404_NOT_FOUND, "File missing")

    return FileResponse(
        file_path,
        media_type=manifest.get('mime_type', 'application/octet-stream'),
        filename=manifest.get('file_name'),
    )
