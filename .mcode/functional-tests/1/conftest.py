"""Pytest fixtures for Magento_Tag REST functional tests."""

import os
import time
import uuid

import pytest
import requests


BASE_URL = os.environ.get("MAGENTO_BASE_URL", "http://127.0.0.1:8082")
ADMIN_USER = os.environ.get("MAGENTO_ADMIN_USER", "admin")
ADMIN_PASSWORD = os.environ.get("MAGENTO_ADMIN_PASSWORD", "admin123")
REQ_TIMEOUT = 30


@pytest.fixture(scope="session")
def base_url():
    return BASE_URL


@pytest.fixture(scope="session", autouse=True)
def _ensure_app_up(base_url):
    """Verify the storefront is reachable before running any tests."""
    deadline = time.time() + 30
    last_exc = None
    while time.time() < deadline:
        try:
            r = requests.get(f"{base_url}/", timeout=5)
            if r.status_code == 200:
                return
        except requests.RequestException as exc:
            last_exc = exc
            time.sleep(1)
    pytest.exit(f"Magento storefront not reachable at {base_url}: {last_exc}", returncode=1)


@pytest.fixture(scope="session")
def admin_token(base_url):
    r = requests.post(
        f"{base_url}/rest/V1/integration/admin/token",
        json={"username": ADMIN_USER, "password": ADMIN_PASSWORD},
        timeout=REQ_TIMEOUT,
    )
    assert r.status_code == 200, f"Admin token request failed: {r.status_code} {r.text}"
    token = r.text.strip('"')
    assert token, "Empty admin token"
    return token


@pytest.fixture(scope="session")
def admin_headers(admin_token):
    return {
        "Authorization": f"Bearer {admin_token}",
        "Content-Type": "application/json",
    }


@pytest.fixture
def tag_prefix():
    """A per-test prefix so parallel/retry runs stay isolated."""
    return f"ftrun-{uuid.uuid4().hex[:8]}"


@pytest.fixture
def created_tag_ids(base_url, admin_headers):
    """Track created tag ids and delete them at teardown (idempotent)."""
    ids: list[int] = []
    yield ids
    for tag_id in ids:
        try:
            requests.delete(
                f"{base_url}/rest/V1/tags/{tag_id}",
                headers=admin_headers,
                timeout=REQ_TIMEOUT,
            )
        except requests.RequestException:
            pass
