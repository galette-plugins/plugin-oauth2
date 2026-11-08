# OAuth2 Plugin E2E Tests

Plugin E2E tests run with Galette core tooling: see the "Plugins" section of
`tests/e2e/README.md` in Galette for prerequisites, shared fixtures and helpers,
and how to run them.

## OAuth2 specific setup

The authorization server needs its keys and configuration. `tests/e2e/setup.sh`
creates them, from the plugin directory:

```bash
cd galette/plugins/plugin-oauth2
bash tests/e2e/setup.sh
```

On CI, the `plugin-e2e` workflow of [galette/.github](https://github.com/galette/.github)
runs it before seeding the fixtures.

## Running tests

From Galette root directory:

```bash
npx playwright test galette/plugins/plugin-oauth2/tests/e2e/specs/
```

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
