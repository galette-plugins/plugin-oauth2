# OAuth2 Plugin E2E Tests

## Prerequisites

- Galette installed and configured
- Test database initialized (`bin/console galette:install`), fixture data added
- Playwright installed (`npm install` from Galette root)

You can find useful information on Playwright setup in the `tests/e2e/README.md` file from Galette.

## Running tests

### From Galette root directory:

```bash
# Run OAuth2 E2E tests only
npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/

# Run with visible browser (headed mode)
npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/ --headed

# Debug mode (step-by-step)
npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/ --debug

# UI mode
npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/ --ui

# Run specific test
npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/oauth2-flow.spec.ts
```

### Start test server manually:

```bash
# Terminal 1: Start PHP server
cd /path/to/galette
DB=pgsql GALETTE_TESTS=1 php -S 0.0.0.0:8090 -t galette/webroot tests/router_e2e.php

# Terminal 2: Run tests
E2E_BASE_URL=http://127.0.0.1:8090 npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/
```

## Using Galette shared fixtures

Plugin specs can import Galette E2E fixtures via the `@e2e` alias defined in
`tsconfig.json`:

```typescript
import { test as base, expect } from '@playwright/test';
import { test } from '@e2e/fixtures/auth.fixture';

// Use `base(...)` for tests with a plain page (no auth)
base('my test', async ({ page }) => { ... });

// Use `test(...)` for tests with a pre-authenticated page
test('my test', async ({ loggedInPage }) => { ... });
```

Available fixtures:
- **`@e2e/fixtures/auth.fixture`** — provides `loggedInPage` (logged in as admin)
- **`@e2e/fixtures/a11y.fixture`** — provides `axeBuilder()` and `formatViolations()` for accessibility audits

## Test Coverage

The E2E tests cover:

1. **Complete OAuth2 Authorization Code Flow**
   - Client redirects user to Galette OAuth2
   - User logs in on OAuth2 login page
   - User approves authorization
   - Client receives authorization code
   - Client exchanges code for access token
   - Client retrieves user information with access token

2. **Error Handling**
   - Invalid client_id shows error

3. **UI Validation**
   - OAuth2 login page displays correctly
   - All form elements are visible

## Notes

- Tests require data to be committed to database
- Screenshots and traces are captured on failure
