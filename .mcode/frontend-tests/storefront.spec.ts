import { test, expect } from "@playwright/test";

/**
 * Storefront regression guard tests for Magento_Tag milestone 1.
 *
 * Milestone 1 is backend-only (DB schema, service contracts, REST routes).
 * No new frontend templates were added. These tests verify the existing
 * storefront surfaces are unbroken after the module scaffold.
 *
 * All selectors verified against the live app via agent-browser DOM inspection.
 */

test.describe("Storefront Home Page", () => {
  test("renders with Luma branding and page header", async ({ page }) => {
    const response = await page.goto("/");
    // Verify HTTP 200
    expect(response?.status()).toBe(200);
    // Main heading — qa_agent observed h1 containing "Home Page"
    await expect(page.locator("h1")).toContainText("Home Page");
    // Page header is visible (Luma theme header)
    await expect(page.locator(".page-header")).toBeVisible();
    // Luma logo: <a class="logo" aria-label="store logo"> (title="" so aria-label is the stable selector)
    await expect(page.locator('a[aria-label="store logo"]')).toBeVisible();
    // Search bar present
    await expect(page.locator("input#search")).toBeVisible();
  });

  test("has working search bar with correct placeholder", async ({ page }) => {
    await page.goto("/");
    const searchInput = page.locator("input#search");
    await expect(searchInput).toBeVisible();
    await expect(searchInput).toHaveAttribute(
      "placeholder",
      /Search entire store here/i
    );
  });
});

test.describe("Customer Login Page", () => {
  test("renders the login form with required fields", async ({ page }) => {
    const response = await page.goto("/customer/account/login/");
    expect(response?.status()).toBe(200);
    // Observed heading: "Customer Login"
    await expect(page.locator("h1")).toContainText("Customer Login");
    // Visible login form on /customer/account/login/ uses these IDs:
    //   input#email (name="login[username]") — NOT input#customer-email
    //   (input#customer-email is the hidden modal authentication block)
    // DOM-verified with agent-browser: these elements are 32x278 visible.
    await expect(page.locator('input[name="login[username]"]')).toBeVisible();
    await expect(page.locator('input[name="login[password]"]')).toBeVisible();
    // Sign-in submit button (id="send2", type="submit") — present in both forms
    await expect(page.locator("button#send2")).toBeVisible();
  });

  test("has Create Account link to registration page", async ({ page }) => {
    await page.goto("/customer/account/login/");
    // qa_agent observed a "Create an Account" link (it is an <a>, not a <button>)
    // Verified class: "action create primary"
    const createLink = page.locator("a.action.create");
    await expect(createLink).toBeVisible();
    await expect(createLink).toContainText("Create an Account");
  });
});

test.describe("Customer Registration Page", () => {
  test("renders the registration form with required fields", async ({
    page,
  }) => {
    const response = await page.goto("/customer/account/create/");
    expect(response?.status()).toBe(200);
    // Observed heading: "Create New Customer Account"
    await expect(page.locator("h1")).toContainText(
      "Create New Customer Account"
    );
    // Firstname and lastname — verified name attributes from live DOM
    await expect(page.locator('input[name="firstname"]')).toBeVisible();
    await expect(page.locator('input[name="lastname"]')).toBeVisible();
    // Email field: id="email_address" (name="email") — verified from live DOM
    await expect(page.locator("input#email_address")).toBeVisible();
    // Password fields
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(
      page.locator('input[name="password_confirmation"]')
    ).toBeVisible();
  });
});

test.describe("Search Results Page", () => {
  test("shows no-results notice for an unknown query", async ({ page }) => {
    const response = await page.goto(
      "/catalogsearch/result/?q=magento_tag_nonexistent_xyz"
    );
    expect(response?.status()).toBe(200);
    // Heading observed by qa_agent
    await expect(page.locator("h1")).toContainText("Search results for:");
    // Warning message for no results
    await expect(page.locator(".message.notice")).toContainText("no results");
  });
});
