import { test, expect } from "@playwright/test";

/**
 * REST API smoke test for Magento_Tag milestone 1.
 *
 * This test proves the backend REST wiring introduced in milestone 1 is live:
 *   webapi.xml → DI preferences → TagRepository → Resource models → DB tables
 *
 * Steps:
 *   1. Obtain an admin bearer token via POST /rest/V1/integration/admin/token
 *   2. Use the token to GET /rest/V1/tags and verify the SearchResults envelope
 */

const BASE_URL = "http://127.0.0.1:8082";
const ADMIN_USER = "admin";
const ADMIN_PASS = "admin123";

test.describe("Magento_Tag REST API — /V1/tags", () => {
  test("GET /rest/V1/tags returns HTTP 200 with SearchResults envelope", async ({
    request,
  }) => {
    // Step 1: obtain admin bearer token
    const tokenResponse = await request.post(
      `${BASE_URL}/rest/V1/integration/admin/token`,
      {
        headers: { "Content-Type": "application/json" },
        data: { username: ADMIN_USER, password: ADMIN_PASS },
      }
    );
    expect(tokenResponse.status()).toBe(200);
    const rawToken = await tokenResponse.text();
    // The endpoint returns a quoted JSON string, e.g. "abc123"
    const token = rawToken.replace(/"/g, "").trim();
    expect(token.length).toBeGreaterThan(10);

    // Step 2: call the /V1/tags endpoint with the admin token
    const tagsResponse = await request.get(
      `${BASE_URL}/rest/V1/tags?searchCriteria[pageSize]=1`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      }
    );

    // Must be HTTP 200 (not 404 = route missing, not 401 = auth broken)
    expect(tagsResponse.status()).toBe(200);

    // Response must be valid JSON with the Magento SearchResults shape
    const body = await tagsResponse.json();

    // Required top-level keys per Magento SearchResults contract
    expect(body).toHaveProperty("items");
    expect(body).toHaveProperty("total_count");
    expect(body).toHaveProperty("search_criteria");

    // items must be an array (empty on a fresh install)
    expect(Array.isArray(body.items)).toBe(true);

    // total_count must be a non-negative integer
    expect(typeof body.total_count).toBe("number");
    expect(body.total_count).toBeGreaterThanOrEqual(0);

    // search_criteria must be an object
    expect(typeof body.search_criteria).toBe("object");
    expect(body.search_criteria).not.toBeNull();

    // On a fresh install there are no tags
    expect(body.total_count).toBe(0);
    expect(body.items).toHaveLength(0);
  });

  test("GET /rest/V1/tags without auth token returns 401", async ({
    request,
  }) => {
    // Regression guard: endpoint must reject unauthenticated requests
    const tagsResponse = await request.get(
      `${BASE_URL}/rest/V1/tags?searchCriteria[pageSize]=1`,
      {
        headers: { Accept: "application/json" },
      }
    );
    // Magento returns 401 for missing/invalid bearer on protected endpoints
    expect(tagsResponse.status()).toBe(401);
  });
});
