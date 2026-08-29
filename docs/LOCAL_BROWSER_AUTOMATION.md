# Local isolated browser automation

GridPBX uses a separate headless Chromium session for repeatable live UI checks.
It does not attach to the user's Chrome profile, move the desktop pointer, or
change the focused desktop window. Interactive computer control remains a
fallback for browser- or desktop-specific behavior only.

## Run the GridPBX Device parity check

Start the Compose stack, then run:

```bash
cd grid-ui
npm run test:e2e:isolated
```

The command always uses headless Chromium with one worker. It does not open a
headed browser window even outside CI.

The local GridPBX development credentials are used by default. Override them
with `GRID_E2E_EMAIL` and `GRID_E2E_PASSWORD` when needed.

## Include the legacy Switch UI reference

Provide a dedicated local test account through environment variables:

```bash
export SWITCH_E2E_USERNAME='test-user'
export SWITCH_E2E_PASSWORD='test-password'
export SWITCH_E2E_ACCOUNT_NAME='test-account'
npm run test:e2e:isolated:all
```

Do not use a personal or production account. The credentials are read from the
process environment and are never written to source control. Playwright stores
temporary authenticated browser state under `grid-ui/.playwright/`, which is
ignored by Git.

## Artifacts

Failures retain a screenshot, trace, and video under `grid-ui/test-results/live`.
The HTML report is written to `grid-ui/playwright-report-live`. Both locations
are ignored by Git.

Type-check only the isolated test harness with:

```bash
npm run test:e2e:isolated:type-check
```
