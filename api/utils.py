import hashlib
import hmac
import json
import re
import shutil
import time
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Dict, Optional
import logging

try:
    import orjson
except ImportError:  # pragma: no cover - fallback only when orjson missing
    orjson = None  # type: ignore

try:
    from mutagen.easyid3 import EasyID3  # type: ignore
    from mutagen.id3 import ID3, ID3NoHeaderError, COMM  # type: ignore
    from mutagen.mp4 import MP4  # type: ignore
except Exception:  # pragma: no cover - optional dependency
    EasyID3 = None  # type: ignore
    ID3 = None  # type: ignore
    ID3NoHeaderError = Exception  # type: ignore
    COMM = None  # type: ignore
    MP4 = None  # type: ignore

from .settings import settings


logger = logging.getLogger(__name__)


def slugify(value: str, fallback: str = "media") -> str:
    cleaned = re.sub(r"[^a-zA-Z0-9]+", "-", value).strip("-").lower()
    cleaned = cleaned or fallback
    return cleaned[:120]


def now_utc() -> datetime:
    return datetime.now(timezone.utc)


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def sign_token(token: str) -> str:
    key = settings.fastapi_auth_key or "change-me"
    return hmac.new(key.encode("utf-8"), token.encode("utf-8"), hashlib.sha256).hexdigest()


def manifest_path(token: str) -> Path:
    return settings.storage_manifests / f"{token}.json"


def write_manifest(token: str, payload: Dict[str, Any]) -> None:
    ensure_parent(manifest_path(token))
    serialized = (
        orjson.dumps(payload) if orjson else json.dumps(payload, ensure_ascii=False).encode("utf-8")
    )
    manifest_path(token).write_bytes(serialized)


def read_manifest(token: str) -> Optional[Dict[str, Any]]:
    path = manifest_path(token)
    if not path.exists():
        return None
    raw = path.read_bytes()
    try:
        return orjson.loads(raw) if orjson else json.loads(raw.decode("utf-8"))
    except Exception:
        return None


def delete_manifest(token: str) -> None:
    path = manifest_path(token)
    if path.exists():
        path.unlink(missing_ok=True)


def cleanup_artifacts() -> None:
    """Delete stale files (default: anything older than cleanup_grace_seconds)."""
    threshold = time.time() - settings.cleanup_grace_seconds
    for folder in (settings.storage_ready, settings.storage_work):
        if not folder.exists():
            continue
        for item in folder.glob("*"):
            try:
                if item.is_dir():
                    # Remove directory if empty/old
                    if item.stat().st_mtime < threshold:
                        shutil.rmtree(item, ignore_errors=True)
                    continue
                if item.stat().st_mtime < threshold:
                    item.unlink(missing_ok=True)
            except FileNotFoundError:
                continue
    for manifest in settings.storage_manifests.glob("*.json"):
        try:
            if manifest.stat().st_mtime < threshold:
                manifest.unlink(missing_ok=True)
        except FileNotFoundError:
            continue


def derive_mime(extension: str) -> str:
    ext = extension.lower().lstrip(".")
    mapping = {
        "mp3": "audio/mpeg",
        "m4a": "audio/mp4",
        "mp4": "video/mp4",
        "mkv": "video/x-matroska",
        "webm": "video/webm",
    }
    return mapping.get(ext, "application/octet-stream")


def brand_file_name(site_name: str, title: str, extension: str) -> str:
    # Always use "yt1s" in filename for consistent branding
    base = slugify(title or "media", "media")
    # Force "yt1s" branding in filename regardless of site_name parameter
    site_slug = "yt1s"
    ext = extension.lower().lstrip(".") or "mp4"
    # Format: title-yt1s.ext
    return f"{base}-{site_slug}.{ext}"


def isoformat(dt: datetime) -> str:
    return dt.astimezone(timezone.utc).isoformat()


def expires_at(seconds: int) -> datetime:
    return now_utc() + timedelta(seconds=seconds)


def human_file_size(size_bytes: int) -> str:
    if size_bytes <= 0:
        return "0 B"
    units = ["B", "KB", "MB", "GB"]
    idx = 0
    value = float(size_bytes)
    while value >= 1024 and idx < len(units) - 1:
        value /= 1024
        idx += 1
    return f"{value:.2f} {units[idx]}"


def apply_branding_metadata(file_path: Path, site_name: str, title: str) -> None:
    """Embed lightweight branding inside MP3/MP4 containers with YT1S branding."""
    suffix = file_path.suffix.lower()
    if suffix == '.mp3' and EasyID3:
        try:
            tags = EasyID3(file_path)  # type: ignore
        except ID3NoHeaderError:
            if ID3:
                empty = ID3()  # type: ignore
                empty.save(file_path)
            tags = EasyID3(file_path)  # type: ignore
        # Set artist and album to site name (YT1S)
        tags['artist'] = site_name
        tags['album'] = site_name
        tags['title'] = title or file_path.stem
        tags.save(file_path)
        
        # Add comment using ID3 directly (EasyID3 doesn't support comment)
        if ID3 and COMM:
            try:
                id3_tags = ID3(file_path)  # type: ignore
                # Remove existing COMM frames and add new one
                id3_tags.delall('COMM')
                id3_tags.add(COMM(encoding=3, lang='eng', desc='', text=f"Downloaded via {site_name}"))  # type: ignore
                id3_tags.save(file_path)
            except Exception as exc:
                logger.warning("Failed to add MP3 comment metadata for %s: %s", file_path, exc)
    elif suffix in ('.mp4', '.m4a') and MP4:
        try:
            mp4 = MP4(file_path)  # type: ignore
            # Set artist to site name (YT1S)
            mp4['\xa9ART'] = [site_name]
            # Set album to site name (YT1S)
            mp4['\xa9alb'] = [site_name]
            # Add comment with YT1S branding
            mp4['\xa9cmt'] = [f"Downloaded via {site_name}"]
            # Set title
            mp4['\xa9nam'] = [title or file_path.stem]
            # Add copyright/encoder info with site name
            mp4['\xa9too'] = [f"{site_name} Downloader"]
            mp4.save(file_path)
        except Exception as exc:  # pragma: no cover - best effort tagging
            logger.warning("Failed to write MP4 metadata for %s: %s", file_path, exc)
