"""CRUD and list functional tests for the Magento_Tag REST surface.

Endpoints covered:
    POST    /V1/tags
    GET     /V1/tags/:tagId
    PUT     /V1/tags/:tagId
    DELETE  /V1/tags/:tagId
    GET     /V1/tags  (searchCriteria)

Admin JWT auth; see conftest.py.
"""

import json

import pytest
import requests


BASE = "/rest/V1/tags"
REQ_TIMEOUT = 30


class TestTagCreate:
    def test_create_tag_returns_200_with_tag_id(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        name = f"{tag_prefix}-create"
        payload = {"tag": {"name": name, "status": 1, "first_customer_id": None, "first_store_id": None}}

        r = requests.post(f"{base_url}{BASE}", headers=admin_headers, json=payload, timeout=REQ_TIMEOUT)
        assert r.status_code == 200, r.text

        body = r.json()
        assert isinstance(body.get("tag_id"), int) and body["tag_id"] > 0, body
        assert body.get("name") == name, body
        assert body.get("status") == 1, body
        created_tag_ids.append(body["tag_id"])

    def test_create_rejects_unauthenticated(self, base_url, tag_prefix):
        payload = {"tag": {"name": f"{tag_prefix}-noauth", "status": 1}}
        r = requests.post(f"{base_url}{BASE}", json=payload, timeout=REQ_TIMEOUT)
        # Magento returns 401 when ACL fails for the resource
        assert r.status_code == 401, r.text


class TestTagGetById:
    def test_get_existing_tag(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        name = f"{tag_prefix}-get"
        create = requests.post(
            f"{base_url}{BASE}", headers=admin_headers,
            json={"tag": {"name": name, "status": 1}}, timeout=REQ_TIMEOUT,
        )
        assert create.status_code == 200, create.text
        tag_id = create.json()["tag_id"]
        created_tag_ids.append(tag_id)

        r = requests.get(f"{base_url}{BASE}/{tag_id}", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert r.status_code == 200, r.text
        body = r.json()
        assert body["tag_id"] == tag_id
        assert body["name"] == name
        assert body["status"] == 1

    def test_get_missing_tag_returns_404(self, base_url, admin_headers):
        r = requests.get(f"{base_url}{BASE}/999999", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert r.status_code == 404, r.text
        body = r.json()
        # Standard Magento NoSuchEntityException shape
        assert "message" in body
        assert "doesn" in body["message"] or "exist" in body["message"].lower()


class TestTagUpdate:
    def test_put_updates_name_and_status(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        name = f"{tag_prefix}-update"
        create = requests.post(
            f"{base_url}{BASE}", headers=admin_headers,
            json={"tag": {"name": name, "status": 1}}, timeout=REQ_TIMEOUT,
        )
        assert create.status_code == 200, create.text
        tag_id = create.json()["tag_id"]
        created_tag_ids.append(tag_id)

        new_name = f"{name}-renamed"
        r = requests.put(
            f"{base_url}{BASE}/{tag_id}", headers=admin_headers,
            json={"tag": {"name": new_name, "status": 0}}, timeout=REQ_TIMEOUT,
        )
        assert r.status_code == 200, r.text
        body = r.json()
        assert body["tag_id"] == tag_id
        assert body["name"] == new_name
        assert body["status"] == 0

        # Subsequent GET reflects the change
        g = requests.get(f"{base_url}{BASE}/{tag_id}", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert g.status_code == 200
        g_body = g.json()
        assert g_body["name"] == new_name
        assert g_body["status"] == 0


class TestTagDelete:
    def test_delete_then_get_returns_404(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        name = f"{tag_prefix}-del"
        create = requests.post(
            f"{base_url}{BASE}", headers=admin_headers,
            json={"tag": {"name": name, "status": 1}}, timeout=REQ_TIMEOUT,
        )
        assert create.status_code == 200, create.text
        tag_id = create.json()["tag_id"]
        # Don't track for cleanup — we're deleting here

        r = requests.delete(f"{base_url}{BASE}/{tag_id}", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert r.status_code == 200, r.text
        assert r.json() is True, r.text

        g = requests.get(f"{base_url}{BASE}/{tag_id}", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert g.status_code == 404, g.text

    def test_delete_missing_returns_404(self, base_url, admin_headers):
        r = requests.delete(f"{base_url}{BASE}/999998", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert r.status_code == 404, r.text


class TestTagList:
    def test_search_returns_search_results_shape(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        # Seed three tags with the same prefix
        names = [f"{tag_prefix}-list-{i}" for i in range(3)]
        for n in names:
            resp = requests.post(
                f"{base_url}{BASE}", headers=admin_headers,
                json={"tag": {"name": n, "status": 1}}, timeout=REQ_TIMEOUT,
            )
            assert resp.status_code == 200, resp.text
            created_tag_ids.append(resp.json()["tag_id"])

        r = requests.get(
            f"{base_url}{BASE}",
            headers=admin_headers,
            params={"searchCriteria[pageSize]": 50},
            timeout=REQ_TIMEOUT,
        )
        assert r.status_code == 200, r.text
        body = r.json()
        # SearchResults shape
        assert "items" in body and isinstance(body["items"], list), body
        assert "total_count" in body, body
        assert "search_criteria" in body, body
        returned_names = [item["name"] for item in body["items"]]
        for n in names:
            assert n in returned_names, f"missing {n} in {returned_names}"

    def test_status_filter_returns_only_matching(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        approved_name = f"{tag_prefix}-filter-approved"
        pending_name = f"{tag_prefix}-filter-pending"
        for name, status in ((approved_name, 1), (pending_name, 0)):
            resp = requests.post(
                f"{base_url}{BASE}", headers=admin_headers,
                json={"tag": {"name": name, "status": status}}, timeout=REQ_TIMEOUT,
            )
            assert resp.status_code == 200, resp.text
            created_tag_ids.append(resp.json()["tag_id"])

        r = requests.get(
            f"{base_url}{BASE}",
            headers=admin_headers,
            params={
                "searchCriteria[filter_groups][0][filters][0][field]": "status",
                "searchCriteria[filter_groups][0][filters][0][value]": 1,
                "searchCriteria[filter_groups][0][filters][0][condition_type]": "eq",
                "searchCriteria[pageSize]": 100,
            },
            timeout=REQ_TIMEOUT,
        )
        assert r.status_code == 200, r.text
        body = r.json()
        names_returned = {item["name"] for item in body["items"]}
        assert approved_name in names_returned, body
        assert pending_name not in names_returned, body
        # Every returned item must be status==1
        statuses = {item["status"] for item in body["items"]}
        assert statuses == {1}, f"expected only status=1, got {statuses}"

    def test_pagination_page_size_respected(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        for i in range(5):
            resp = requests.post(
                f"{base_url}{BASE}", headers=admin_headers,
                json={"tag": {"name": f"{tag_prefix}-page-{i}", "status": 1}}, timeout=REQ_TIMEOUT,
            )
            assert resp.status_code == 200, resp.text
            created_tag_ids.append(resp.json()["tag_id"])

        r = requests.get(
            f"{base_url}{BASE}",
            headers=admin_headers,
            params={"searchCriteria[pageSize]": 2, "searchCriteria[currentPage]": 1},
            timeout=REQ_TIMEOUT,
        )
        assert r.status_code == 200, r.text
        body = r.json()
        assert len(body["items"]) <= 2, body
        assert body["total_count"] >= 5, body


class TestTagEdgeCases:
    def test_name_longer_than_255_is_truncated(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        prefix = f"{tag_prefix}-trunc-"
        payload_name = prefix + "x" * 400  # total > 255
        r = requests.post(
            f"{base_url}{BASE}", headers=admin_headers,
            json={"tag": {"name": payload_name, "status": 1}}, timeout=REQ_TIMEOUT,
        )
        assert r.status_code == 200, r.text
        body = r.json()
        tag_id = body["tag_id"]
        created_tag_ids.append(tag_id)
        # Response name should be truncated to 255 by Tag::beforeSave
        assert len(body["name"]) == 255, f"expected name length 255, got {len(body['name'])}"
        assert body["name"] == payload_name[:255]

        # Subsequent GET should reflect the stored (truncated) value
        g = requests.get(f"{base_url}{BASE}/{tag_id}", headers=admin_headers, timeout=REQ_TIMEOUT)
        assert g.status_code == 200
        assert len(g.json()["name"]) == 255

    def test_duplicate_names_allowed(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        """Current repository has no uniqueness constraint on `name`; two inserts both succeed."""
        name = f"{tag_prefix}-dup"
        ids = []
        for _ in range(2):
            r = requests.post(
                f"{base_url}{BASE}", headers=admin_headers,
                json={"tag": {"name": name, "status": 0}}, timeout=REQ_TIMEOUT,
            )
            assert r.status_code == 200, r.text
            tid = r.json()["tag_id"]
            ids.append(tid)
            created_tag_ids.append(tid)
        assert ids[0] != ids[1], "Expected two distinct tag_ids for the same name"

    def test_status_outside_enum_is_accepted(self, base_url, admin_headers, tag_prefix, created_tag_ids):
        """No validator in this milestone — a status outside {-1, 0, 1} is accepted and persisted."""
        name = f"{tag_prefix}-enum"
        r = requests.post(
            f"{base_url}{BASE}", headers=admin_headers,
            json={"tag": {"name": name, "status": 99}}, timeout=REQ_TIMEOUT,
        )
        assert r.status_code == 200, r.text
        body = r.json()
        assert body["status"] == 99
        created_tag_ids.append(body["tag_id"])
