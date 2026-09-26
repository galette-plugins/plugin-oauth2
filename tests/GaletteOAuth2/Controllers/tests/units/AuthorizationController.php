<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Authorization controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AuthorizationController extends GaletteRoutingTestCase
{
    protected int $seed = 20260926180000;
    protected bool $load_plugins = true;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        global $session;
        parent::setUp();

        $this->session = $this->container->get('oauth_session');
        $session = $this->session;
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset(
            $this->session->isLoggedIn,
            $this->session->user_id,
            $this->session->client_id,
            $this->session->request_args
        );
        parent::tearDown();
    }

    /**
     * Mark user as logged in the OAuth session
     *
     * @param string $client_id Client the login has been checked for
     *
     * @return void
     */
    private function logUserIn(string $client_id = 'galette_flarum'): void
    {
        $this->session->isLoggedIn = 'yes';
        $this->session->user_id = 1;
        $this->session->client_id = $client_id;
    }

    /**
     * Build authorization request parameters
     *
     * @param string $redirect_uri Redirect URI
     *
     * @return array<string, string>
     */
    private function getAuthorizeParams(string $redirect_uri): array
    {
        return [
            'response_type' => 'code',
            'client_id' => 'galette_flarum',
            'redirect_uri' => $redirect_uri,
            'scope' => 'member',
            'state' => '7d627422092a7a5ac413ac597312b9b4',
        ];
    }

    /**
     * Test authorization form with the configured redirect URI
     *
     * @return void
     */
    public function testAuthorize(): void
    {
        $this->logUserIn();

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_authorize',
            query_params: $this->getAuthorizeParams('http://flarum.localhost/auth/passport')
        );
        $test_response = $this->app->handle($request);

        $this->expectOK($test_response);
        $this->assertStringContainsString(
            'Forum Flarum is requesting access to the following details',
            (string)$test_response->getBody()
        );
    }

    /**
     * Test a login checked for a client cannot be used for another one
     *
     * @return void
     */
    public function testAuthorizeRequiresLoginForSameClient(): void
    {
        $this->logUserIn('galette_nc');

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_authorize',
            query_params: $this->getAuthorizeParams('http://flarum.localhost/auth/passport')
        );
        $test_response = $this->app->handle($request);

        $this->assertSame(302, $test_response->getStatusCode());
        $this->assertStringContainsString(
            OAUTH2_PREFIX . '/login',
            $test_response->getHeaderLine('Location')
        );

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_doAuthorize',
            method: 'POST',
            query_params: $this->getAuthorizeParams('http://flarum.localhost/auth/passport')
        );
        $request = $request->withParsedBody(['approve' => '', 'scopes' => ['member']]);
        $test_response = $this->app->handle($request);

        $this->assertSame(302, $test_response->getStatusCode());
        $this->assertStringContainsString(
            OAUTH2_PREFIX . '/login',
            $test_response->getHeaderLine('Location')
        );
    }

    /**
     * Test authorization form refuses a redirect URI that is not configured
     *
     * @return void
     */
    public function testAuthorizeRefusesUnknownRedirectUri(): void
    {
        $this->logUserIn();

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_authorize',
            query_params: $this->getAuthorizeParams('https://attacker.example/cb')
        );
        $test_response = $this->app->handle($request);

        $this->assertSame(401, $test_response->getStatusCode());
        $this->assertFalse($test_response->hasHeader('Location'));
        $this->assertFileDoesNotExist(
            GALETTE_CACHE_DIR . '/' . OAUTH2_PREFIX . '_galette_flarum.redirect_uri.txt'
        );
    }

    /**
     * Test approval redirects to the configured redirect URI
     *
     * @return void
     */
    public function testDoAuthorize(): void
    {
        $this->logUserIn();

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_doAuthorize',
            method: 'POST',
            query_params: $this->getAuthorizeParams('http://flarum.localhost/auth/passport')
        );
        $request = $request->withParsedBody(['approve' => '', 'scopes' => ['member']]);
        $test_response = $this->app->handle($request);

        $this->assertSame(302, $test_response->getStatusCode());
        $this->assertStringStartsWith(
            'http://flarum.localhost/auth/passport?code=',
            $test_response->getHeaderLine('Location')
        );
    }

    /**
     * Test approval refuses a redirect URI that is not configured
     *
     * @return void
     */
    public function testDoAuthorizeRefusesUnknownRedirectUri(): void
    {
        $this->logUserIn();

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_doAuthorize',
            method: 'POST',
            query_params: $this->getAuthorizeParams('https://attacker.example/cb')
        );
        $request = $request->withParsedBody(['approve' => '', 'scopes' => ['member']]);
        $test_response = $this->app->handle($request);

        $this->assertSame(401, $test_response->getStatusCode());
        $this->assertFalse($test_response->hasHeader('Location'));
    }

    /**
     * Grant types that must not be accepted
     *
     * @return array<string, array<array<string, string>>>
     */
    public static function disabledGrantsProvider(): array
    {
        return [
            'password' => [
                [
                    'grant_type' => 'password',
                    'username' => 'admin',
                    'password' => 'admin',
                ]
            ],
            'client credentials' => [
                ['grant_type' => 'client_credentials']
            ],
        ];
    }

    /**
     * Test only authorization code and refresh token grants are available
     *
     * @param array<string, string> $params Token request parameters
     *
     * @return void
     */
    #[DataProvider('disabledGrantsProvider')]
    public function testTokenRefusesDisabledGrants(array $params): void
    {
        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_token',
            method: 'POST',
            content_type: 'application/x-www-form-urlencoded'
        );
        $request = $request->withParsedBody(
            $params + [
                'client_id' => 'galette_cli',
                'client_secret' => 'cli-secret-for-tests',
                'scope' => 'member',
            ]
        );
        $test_response = $this->app->handle($request);

        $this->assertSame(400, $test_response->getStatusCode());
        $body = json_decode((string)$test_response->getBody(), true);
        $this->assertSame('unsupported_grant_type', $body['error']);
    }
}
