/*!
 * Copyright © 2021-2026 The Galette Team
 *
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette OAuth2 plugin. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

import { test as base, expect } from '@playwright/test';
import { test } from '@e2e/fixtures/auth.fixture';

/**
 * OAuth2 Flow E2E Tests
 *
 * These tests verify the complete OAuth2 authorization code flow:
 * 1. Client initiates OAuth2 authorization
 * 2. User is redirected to Galette OAuth2 login page
 * 3. User authenticates
 * 4. User approves the authorization request
 * 5. User is redirected back to client with authorization code
 * 6. Client exchanges code for access token
 * 7. Client uses access token to get user information
 *
 * Galette shared fixtures are available via the @e2e alias:
 *   import { test } from '@e2e/fixtures/auth.fixture';
 *   test('my test', async ({ loggedInPage }) => { ... });
 */
test.describe('OAuth2 Plugin', () => {

  test.describe('Login Page UI', () => {

    base('OAuth2 login page displays correctly', async ({ page }) => {
      const baseUrl = process.env.E2E_BASE_URL || 'http://127.0.0.1:8090';
      const clientId = 'galette_cli';

      const authParams = new URLSearchParams({
        response_type: 'code',
        client_id: clientId,
        redirect_uri: `${baseUrl}/callback`,
        scope: 'member',
        state: 'test-ui',
      });
      const authorizationUrl = `${baseUrl}/plugins/oauth2/authorize?${authParams.toString()}`;

      await page.goto(authorizationUrl);

      // Should redirect to OAuth2 login page
      await expect(page).toHaveURL(/\/plugins\/oauth2\/login/);

      // Check page elements
      await expect(page.locator('input[name="login"]')).toBeVisible();
      await expect(page.locator('input[name="password"]')).toBeVisible();
      await expect(page.locator('button[type="submit"], input[type="submit"]')).toBeVisible();

      // Page should have a title
      await expect(page).toHaveTitle(/.+/);
    });

  });

  test.describe('Authorization Flow', () => {

    base('OAuth2 flow superadmin cannot login', async ({ page }) => {
      const baseUrl = process.env.E2E_BASE_URL || 'http://127.0.0.1:8090';
      const clientId = 'galette_cli';
      const redirectUri = `${baseUrl}/oauth2-test-callback`;
      const scope = 'member member:localization';

      // Step 1: Build authorization URL (simulating client application)
      const state = 'test-state-' + Date.now();
      const authParams = new URLSearchParams({
        response_type: 'code',
        client_id: clientId,
        redirect_uri: redirectUri,
        scope: scope,
        state: state,
      });
      const authorizationUrl = `${baseUrl}/plugins/oauth2/authorize?${authParams.toString()}`;

      // Step 2: Navigate to authorization URL
      await page.goto(authorizationUrl);

      // Should be redirected to login page with redirect_url
      await expect(page).toHaveURL(/\/plugins\/oauth2\/login.*redirect_url=/);
      await expect(page.locator('input[name="login"]')).toBeVisible();
      await expect(page.locator('input[name="password"]')).toBeVisible();

      // Step 3: Fill login form
      const login = 'admin';
      const password = 'admin';

      await page.locator('input[name="login"]').fill(login);
      await page.locator('input[name="password"]').fill(password);
      await page.locator('button[type="submit"], input[type="submit"]').click();

      // Step 4: Should be redirected to login page
      await expect(page).toHaveURL(/\/plugins\/oauth2\/login/);

      // Wait for the error toast to appear. It can take some time in CI.
      await expect(page.locator('.ui.toast.error')).toBeVisible({ timeout: 10000 });
    });

    base('Complete OAuth2 authorization code flow', async ({ page }) => {
      const baseUrl = process.env.E2E_BASE_URL || 'http://127.0.0.1:8090';
      const clientId = 'galette_cli';
      const redirectUri = `${baseUrl}/plugins/oauth2/test-callback`;
      const scope = 'member member:localization';

      // Step 1: Build authorization URL (simulating client application)
      const state = 'test-state-' + Date.now();
      const authParams = new URLSearchParams({
        response_type: 'code',
        client_id: clientId,
        redirect_uri: redirectUri,
        scope: scope,
        state: state,
      });
      const authorizationUrl = `${baseUrl}/plugins/oauth2/authorize?${authParams.toString()}`;

      // Step 2: Navigate to authorization URL
      await page.goto(authorizationUrl);

      // Should be redirected to login page with redirect_url
      await expect(page).toHaveURL(/\/plugins\/oauth2\/login.*redirect_url=/);
      await expect(page.locator('input[name="login"]')).toBeVisible();
      await expect(page.locator('input[name="password"]')).toBeVisible();

      // Step 3: Fill login form
      const login = 'leia.organa';
      const password = 'G@l3tte-E2E!';

      await page.locator('input[name="login"]').fill(login);
      await page.locator('input[name="password"]').fill(password);
      await page.locator('button[type="submit"], input[type="submit"]').click();

      // Step 4: Should be redirected to authorization page
      await expect(page).toHaveURL(/\/plugins\/oauth2\/authorize/);

      // Check that authorization page displays correctly
      await expect(page.locator('button[name="approve"], input[name="approve"]')).toBeVisible({ timeout: 10000 });

      // Step 5: Approve the authorization
      await page.locator('button[name="approve"], input[name="approve"]').click();

      // Step 6: Should be redirected to test callback with code
      await page.waitForURL(/\/plugins\/oauth2\/test-callback/, { timeout: 10000 });

      // Verify the URL contains the authorization code and state
      const finalUrl = page.url();
      const urlParams = new URLSearchParams(new URL(finalUrl).search);

      expect(urlParams.has('code')).toBeTruthy();
      expect(urlParams.has('state')).toBeTruthy();
      expect(urlParams.get('state')).toBe(state);

      const authorizationCode = urlParams.get('code');
      expect(authorizationCode).toBeTruthy();
      expect(authorizationCode!.length).toBeGreaterThan(10);

      // Step 7: Exchange code for access token (API call)
      const tokenResponse = await page.request.post(`${baseUrl}/plugins/oauth2/access_token`, {
        form: {
          grant_type: 'authorization_code',
          code: authorizationCode!,
          redirect_uri: redirectUri,
          client_id: clientId,
          client_secret: 'abc123',
        },
      });

      // Debug: log response if not OK
      if (!tokenResponse.ok()) {
        console.error('Token exchange failed:', tokenResponse.status(), await tokenResponse.text());
      }
      expect(tokenResponse.ok()).toBeTruthy();
      const tokenData = await tokenResponse.json();

      expect(tokenData).toHaveProperty('access_token');
      expect(tokenData).toHaveProperty('token_type');
      expect(tokenData.token_type).toBe('Bearer');
      expect(tokenData).toHaveProperty('expires_in');

      // Step 8: Use access token to get user info
      const userInfoResponse = await page.request.get(`${baseUrl}/plugins/oauth2/user`, {
        headers: {
          'Authorization': `Bearer ${tokenData.access_token}`,
        },
      });

      // Debug: log response if not OK
      if (!userInfoResponse.ok()) {
        console.error('User info failed:', userInfoResponse.status(), await userInfoResponse.text());
      }
      expect(userInfoResponse.ok()).toBeTruthy();
      const userInfo = await userInfoResponse.json();

      // Verify user information
      expect(userInfo).toHaveProperty('id');
      expect(userInfo).toHaveProperty('username');
      expect(userInfo.username).toBe(login);
    });

    base('Shows error for invalid client_id', async ({ page }) => {
      const baseUrl = process.env.E2E_BASE_URL || 'http://127.0.0.1:8090';

      const authParams = new URLSearchParams({
        response_type: 'code',
        client_id: 'invalid_client_does_not_exist',
        redirect_uri: `${baseUrl}/callback`,
        scope: 'member',
        state: 'test-invalid',
      });
      const authorizationUrl = `${baseUrl}/plugins/oauth2/authorize?${authParams.toString()}`;

      await page.goto(authorizationUrl);

      // Should show an error
      await page.waitForTimeout(2000); // Give time for error to appear

      // Wait for the error to appear. It can take some time in CI.
      await expect(page.locator('.ui.red.message')).toBeVisible({ timeout: 10000 });
      await page.getByText('OAuth2 error Unknown client');
      const hasErrorOnPage = await page.locator('.ui.red.message').isVisible().catch(() => false);

      expect(hasErrorOnPage).toBeTruthy();
    });

  });

});

