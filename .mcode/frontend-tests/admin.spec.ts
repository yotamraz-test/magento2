import { test, expect, Page } from "@playwright/test";

/**
 * Admin regression guard tests for Magento_Tag milestone 1.
 *
 * Tests cover: admin login, dashboard, and the Catalog menu (ensuring no
 * Tags menu item is injected prematurely — that is a milestone 2 concern).
 */

const ADMIN_PATH = "/admin_xptuha1";
const ADMIN_USER = "admin";
const ADMIN_PASS = "admin123";

/**
 * Helper: log into the Magento admin and wait for the dashboard.
 * Returns after the dashboard h1 is visible.
 */
async function adminLogin(page: Page): Promise<void> {
  await page.goto(ADMIN_PATH);
  // Wait for the username field to be available
  await page.waitForSelector('input[name="login[username]"]', {
    timeout: 30000,
  });
  await page.fill('input[name="login[username]"]', ADMIN_USER);
  await page.fill('input[name="login[password]"]', ADMIN_PASS);
  await page.click('button:has-text("Sign in")');

  // Dismiss the analytics dialog if it appears
  try {
    await page.waitForSelector('button:has-text("Don\'t Allow")', {
      timeout: 5000,
    });
    await page.click('button:has-text("Don\'t Allow")');
  } catch {
    // Dialog did not appear — continue
  }

  // Wait until the admin dashboard has fully loaded
  await page.waitForLoadState("networkidle", { timeout: 30000 });
}

test.describe("Admin Login", () => {
  test("admin login page renders with credentials form", async ({ page }) => {
    const response = await page.goto(ADMIN_PATH);
    expect(response?.status()).toBe(200);
    // Username and password fields
    await expect(
      page.locator('input[name="login[username]"]')
    ).toBeVisible();
    await expect(
      page.locator('input[name="login[password]"]')
    ).toBeVisible();
    // Sign in button
    await expect(page.locator('button:has-text("Sign in")')).toBeVisible();
  });

  test("admin can log in with valid credentials and reach dashboard", async ({
    page,
  }) => {
    await adminLogin(page);

    // Observed heading after login: "Dashboard"
    await expect(page.locator("h1.page-title")).toContainText("Dashboard");

    // Admin navigation menu is visible
    await expect(page.locator(".admin__menu")).toBeVisible();

    // URL should have redirected to the dashboard route
    expect(page.url()).toContain("dashboard");
  });
});

test.describe("Admin Dashboard", () => {
  test("dashboard page has Dashboard heading and no 500 error", async ({
    page,
  }) => {
    await adminLogin(page);

    // Primary heading must say Dashboard
    await expect(page.locator("h1.page-title")).toContainText("Dashboard");

    // Check for absence of a PHP/Magento 500 error indicator
    const bodyText = await page.locator("body").innerText();
    expect(bodyText).not.toMatch(/500 Internal Server Error/i);
    expect(bodyText).not.toMatch(/Fatal error/i);
  });
});

test.describe("Admin Catalog Menu", () => {
  test("Catalog menu shows Products and Categories but NOT Tags", async ({
    page,
  }) => {
    await adminLogin(page);

    // Click the top-level CATALOG menu item.
    // DOM-verified: the li has id="menu-magento-catalog-catalog"
    // Using this ID avoids ambiguity with "Catalog Price Rule" under Marketing.
    const catalogLink = page.locator("#menu-magento-catalog-catalog > a");
    await catalogLink.click();

    // Wait for the flyout submenu to open
    await page.waitForTimeout(1000);

    // DOM-verified submenu items use data-ui-id attributes:
    //   Products:    data-ui-id="menu-magento-catalog-catalog-products"
    //   Categories:  data-ui-id="menu-magento-catalog-catalog-categories"
    const productsItem = page.locator(
      '[data-ui-id="menu-magento-catalog-catalog-products"] a'
    );
    const categoriesItem = page.locator(
      '[data-ui-id="menu-magento-catalog-catalog-categories"] a'
    );
    await expect(productsItem).toBeVisible();
    await expect(categoriesItem).toBeVisible();

    // Regression guard for milestone 1: no Tags menu entry should exist.
    // Milestone 2 will add the admin UI. Check the full menu for any Tags link.
    const tagsItem = page.locator(
      '[data-ui-id*="catalog-tag"] a, [data-ui-id*="catalog-tags"] a'
    );
    await expect(tagsItem).not.toBeVisible();
  });
});
