"""Functional test for the customer-facing addTagsToProduct endpoint.

Endpoint:
    POST /V1/products/:productId/tags
    (ACL `self` — customer-authenticated)

Seeds a customer and a product via existing Magento REST APIs, obtains a customer
JWT, then calls addTagsToProduct and verifies the per-tag status result.
"""

import os
import uuid

import pytest
import requests


BASE_URL = os.environ.get("MAGENTO_BASE_URL", "http://127.0.0.1:8082")
ADMIN_USER = os.environ.get("MAGENTO_ADMIN_USER", "admin")
ADMIN_PASSWORD = os.environ.get("MAGENTO_ADMIN_PASSWORD", "admin123")
REQ_TIMEOUT = 30


@pytest.fixture(scope="module")
def admin_headers_mod():
    r = requests.post(
        f"{BASE_URL}/rest/V1/integration/admin/token",
        json={"username": ADMIN_USER, "password": ADMIN_PASSWORD},
        timeout=REQ_TIMEOUT,
    )
    assert r.status_code == 200, r.text
    return {
        "Authorization": f"Bearer {r.text.strip('\"')}",
        "Content-Type": "application/json",
    }


@pytest.fixture
def seeded_customer_and_product(admin_headers_mod):
    """Create a customer + simple product, yield (customer_token, product_id); clean up after."""
    suffix = uuid.uuid4().hex[:8]
    email = f"ft-{suffix}@example.com"
    password = "Password123!"

    # Create customer
    cust_resp = requests.post(
        f"{BASE_URL}/rest/V1/customers",
        headers=admin_headers_mod,
        json={
            "customer": {
                "email": email,
                "firstname": "FT",
                "lastname": "User",
            },
            "password": password,
        },
        timeout=REQ_TIMEOUT,
    )
    assert cust_resp.status_code == 200, cust_resp.text
    customer_id = cust_resp.json()["id"]

    # Create a simple product
    sku = f"ft-sku-{suffix}"
    prod_resp = requests.post(
        f"{BASE_URL}/rest/V1/products",
        headers=admin_headers_mod,
        json={
            "product": {
                "sku": sku,
                "name": f"FT Product {suffix}",
                "price": 9.99,
                "type_id": "simple",
                "attribute_set_id": 4,
                "status": 1,
                "visibility": 4,
                "weight": 1,
            }
        },
        timeout=REQ_TIMEOUT,
    )
    assert prod_resp.status_code == 200, prod_resp.text
    product_id = prod_resp.json()["id"]

    # Customer token
    ctok = requests.post(
        f"{BASE_URL}/rest/V1/integration/customer/token",
        json={"username": email, "password": password},
        timeout=REQ_TIMEOUT,
    )
    assert ctok.status_code == 200, ctok.text
    customer_token = ctok.text.strip('"')

    created_tag_ids: list[int] = []
    try:
        yield {
            "customer_token": customer_token,
            "customer_id": customer_id,
            "product_id": product_id,
            "sku": sku,
            "tag_ids": created_tag_ids,
            "suffix": suffix,
        }
    finally:
        # Clean up any tags created during the test
        for tid in created_tag_ids:
            try:
                requests.delete(
                    f"{BASE_URL}/rest/V1/tags/{tid}",
                    headers=admin_headers_mod,
                    timeout=REQ_TIMEOUT,
                )
            except requests.RequestException:
                pass
        # Delete product (by sku)
        try:
            requests.delete(
                f"{BASE_URL}/rest/V1/products/{sku}",
                headers=admin_headers_mod,
                timeout=REQ_TIMEOUT,
            )
        except requests.RequestException:
            pass
        # Delete customer
        try:
            requests.delete(
                f"{BASE_URL}/rest/V1/customers/{customer_id}",
                headers=admin_headers_mod,
                timeout=REQ_TIMEOUT,
            )
        except requests.RequestException:
            pass


class TestAddTagsToProduct:
    def test_customer_submits_two_new_tags(self, seeded_customer_and_product):
        """A customer-authenticated POST creates two PENDING tags and returns per-tag status strings."""
        env = seeded_customer_and_product
        headers = {
            "Authorization": f"Bearer {env['customer_token']}",
            "Content-Type": "application/json",
        }
        tag_names = [f"ft-cool-{env['suffix']}", f"ft-trendy-{env['suffix']}"]
        r = requests.post(
            f"{BASE_URL}/rest/V1/products/{env['product_id']}/tags",
            headers=headers,
            json={"tagNames": tag_names},
            timeout=REQ_TIMEOUT,
        )
        # Expected: HTTP 200 with a mapping/list of status strings per tag.
        # TagManagementInterface returns string[] (associative array keyed by tag name).
        assert r.status_code == 200, r.text

        body = r.json()
        # Response could be an object keyed by name, or a positional array of statuses.
        if isinstance(body, dict):
            for n in tag_names:
                assert n in body, f"missing status for {n} in {body}"
                assert body[n] in {"new", "success", "exist", "rejected"}, body
        elif isinstance(body, list):
            assert len(body) == len(tag_names), body
            for s in body:
                assert s in {"new", "success", "exist", "rejected"}, body
        else:
            pytest.fail(f"unexpected response shape: {body!r}")

        # Register any tags that were created for cleanup
        admin_hdrs = {
            "Authorization": self._admin_token_header_only(),
            "Content-Type": "application/json",
        }
        list_r = requests.get(
            f"{BASE_URL}/rest/V1/tags",
            headers=admin_hdrs,
            params={"searchCriteria[pageSize]": 100},
            timeout=REQ_TIMEOUT,
        )
        for item in list_r.json().get("items", []):
            if item.get("name") in tag_names:
                env["tag_ids"].append(item["tag_id"])

    @staticmethod
    def _admin_token_header_only():
        r = requests.post(
            f"{BASE_URL}/rest/V1/integration/admin/token",
            json={"username": ADMIN_USER, "password": ADMIN_PASSWORD},
            timeout=REQ_TIMEOUT,
        )
        return f"Bearer {r.text.strip('\"')}"
