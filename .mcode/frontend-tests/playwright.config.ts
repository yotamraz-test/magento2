import { defineConfig, devices } from "@playwright/test";
import * as os from "os";
import * as path from "path";

// agent-browser uses chromium-1208 from the Playwright cache — use the same binary.
// On this ARM64 sandbox, system glib/nss/nspr are absent, but the PIXI conda
// environments (306-app-v1 and 306-app-v6) supply the required shared libraries.
const HOME = os.homedir();
const CHROMIUM_BIN = path.join(
  HOME,
  ".cache/ms-playwright/chromium-1208/chrome-linux/chrome"
);
const PIXI_ENVS = "/app/.local/share/modelcode/workspace/.pixi/envs";
const LD_LIB =
  `${PIXI_ENVS}/306-app-v1/lib:${PIXI_ENVS}/306-app-v6/lib`;

export default defineConfig({
  testDir: ".",
  timeout: 60000,
  retries: 1,
  use: {
    baseURL: "http://127.0.0.1:8082",
    headless: true,
    screenshot: "on",
    video: "retain-on-failure",
    trace: "retain-on-failure",
    launchOptions: {
      executablePath: CHROMIUM_BIN,
      args: [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
      ],
      env: {
        ...process.env,
        LD_LIBRARY_PATH: LD_LIB,
      } as Record<string, string>,
    },
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
  reporter: [
    ["list"],
    [
      "json",
      {
        outputFile: `${process.env.MCODE_DIR}/fe_testing/playwright-results.json`,
      },
    ],
  ],
});
