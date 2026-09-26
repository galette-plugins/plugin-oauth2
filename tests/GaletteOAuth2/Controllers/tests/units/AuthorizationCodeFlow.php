<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Authorization code flow tests: code, tokens and user data
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AuthorizationCodeFlow extends GaletteRoutingTestCase
{
    protected int $seed = 20260926233000;
    protected bool $load_plugins = true;

    private const string CLIENT_ID = 'galette_cli';
    private const string CLIENT_SECRET = 'cli-secret-for-tests';
    private const string REDIRECT_URI = 'http://localhost:8888';

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
            $this->session->client_id
        );
        parent::tearDown();
    }

    /**
     * Get an authorization code, as if member had logged in and approved
     *
     * @param int      $member_id Member ID
     * @param string[] $scopes    Scopes checked on the consent screen
     *
     * @return string
     */
    private function getAuthorizationCode(int $member_id, array $scopes): string
    {
        $this->session->isLoggedIn = 'yes';
        $this->session->user_id = $member_id;
        $this->session->client_id = self::CLIENT_ID;

        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_doAuthorize',
            method: 'POST',
            query_params: [
                'response_type' => 'code',
                'client_id' => self::CLIENT_ID,
                'redirect_uri' => self::REDIRECT_URI,
                'scope' => implode(' ', $scopes),
                'state' => 'flow-state',
            ]
        );
        $request = $request->withParsedBody(['approve' => '', 'scopes' => $scopes]);
        $test_response = $this->app->handle($request);

        $this->assertSame(302, $test_response->getStatusCode());
        $location = $test_response->getHeaderLine('Location');
        $this->assertStringStartsWith(self::REDIRECT_URI . '?', $location);
        parse_str((string)parse_url($location, PHP_URL_QUERY), $args);
        $this->assertSame('flow-state', $args['state']);
        $this->assertIsString($args['code']);

        return $args['code'];
    }

    /**
     * Send a token request
     *
     * @param array<string, string> $params Request parameters
     *
     * @return ResponseInterface
     */
    private function requestToken(array $params): ResponseInterface
    {
        $request = $this->createRequest(
            route_name: OAUTH2_PREFIX . '_token',
            method: 'POST',
            content_type: 'application/x-www-form-urlencoded'
        );
        $request = $request->withParsedBody(
            $params + [
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::CLIENT_SECRET,
            ]
        );
        return $this->app->handle($request);
    }

    /**
     * Exchange an authorization code for tokens
     *
     * @param string $code Authorization code
     *
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code): array
    {
        $test_response = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT_URI,
        ]);
        $this->assertSame(200, $test_response->getStatusCode(), (string)$test_response->getBody());

        $tokens = json_decode((string)$test_response->getBody(), true);
        $this->assertIsArray($tokens);
        return $tokens;
    }

    /**
     * Get user data with an access token
     *
     * @param string $access_token Access token
     *
     * @return ResponseInterface
     */
    private function requestUser(string $access_token): ResponseInterface
    {
        $request = $this->createRequest(route_name: OAUTH2_PREFIX . '_user');
        $request = $request->withHeader('Authorization', 'Bearer ' . $access_token);
        return $this->app->handle($request);
    }

    /**
     * Test exchanging a code for tokens
     *
     * @return void
     */
    public function testTokenFromAuthorizationCode(): void
    {
        $member = $this->getAdminMember($this->getMemberOne());
        $tokens = $this->exchangeCode($this->getAuthorizationCode($member->id, ['member']));

        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertSame(3600, $tokens['expires_in']);
        $this->assertNotEmpty($tokens['access_token']);
        $this->assertNotEmpty($tokens['refresh_token']);
    }

    /**
     * Test code exchange requires client secret and same redirect URI
     *
     * @return void
     */
    public function testTokenRequiresClientSecretAndRedirectUri(): void
    {
        $member = $this->getAdminMember($this->getMemberOne());
        $code = $this->getAuthorizationCode($member->id, ['member']);

        $test_response = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::REDIRECT_URI,
            'client_secret' => 'wrong-secret',
        ]);
        $this->assertSame(401, $test_response->getStatusCode());
        $body = json_decode((string)$test_response->getBody(), true);
        $this->assertSame('invalid_client', $body['error']);

        $test_response = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => 'http://127.0.0.1/callback',
        ]);
        $this->assertSame(400, $test_response->getStatusCode());
        $body = json_decode((string)$test_response->getBody(), true);
        $this->assertSame('invalid_request', $body['error']);
    }

    /**
     * Test refreshing tokens
     *
     * @return void
     */
    public function testRefreshToken(): void
    {
        $member = $this->getAdminMember($this->getMemberOne());
        $tokens = $this->exchangeCode($this->getAuthorizationCode($member->id, ['member', 'member:due_date']));

        $test_response = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
        ]);
        $this->assertSame(200, $test_response->getStatusCode(), (string)$test_response->getBody());
        $refreshed = json_decode((string)$test_response->getBody(), true);
        $this->assertNotSame($tokens['access_token'], $refreshed['access_token']);
        $this->assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);

        //refreshed token keeps scopes
        $test_response = $this->requestUser($refreshed['access_token']);
        $this->assertSame(200, $test_response->getStatusCode());
        $data = json_decode((string)$test_response->getBody(), true);
        $this->assertArrayHasKey('due_date', $data);
    }

    /**
     * Test user data follow granted scopes
     *
     * @return void
     */
    public function testUserData(): void
    {
        $member = $this->getAdminMember($this->getMemberOne());
        $data = $this->dataAdherentOne();

        $tokens = $this->exchangeCode($this->getAuthorizationCode($member->id, ['member']));
        $test_response = $this->requestUser($tokens['access_token']);
        $this->assertSame(200, $test_response->getStatusCode());
        $this->assertSame('application/json', $test_response->getHeaderLine('Content-Type'));
        $user = json_decode((string)$test_response->getBody(), true);

        $this->assertSame($member->id, $user['id']);
        $this->assertSame($member->id, $user['sub']);
        $this->assertSame($data['login_adh'], $user['username']);
        $this->assertSame($data['email_adh'], $user['email']);
        foreach (['birthdate', 'address', 'phone', 'socials', 'groups', 'due_date'] as $key) {
            $this->assertArrayNotHasKey($key, $user);
        }

        $tokens = $this->exchangeCode(
            $this->getAuthorizationCode(
                $member->id,
                ['member', 'member:personal', 'member:localization', 'member:groups', 'member:due_date']
            )
        );
        $test_response = $this->requestUser($tokens['access_token']);
        $this->assertSame(200, $test_response->getStatusCode());
        $user = json_decode((string)$test_response->getBody(), true);

        $this->assertArrayHasKey('birthdate', $user);
        $this->assertArrayHasKey('address', $user);
        $this->assertArrayNotHasKey('street_address', $user['address']);
        $this->assertContains('admin', $user['groups']);
        $this->assertArrayHasKey('due_date', $user);
    }

    /**
     * Test user data are refused when member is no longer authorized
     *
     * @return void
     */
    public function testUserDataRequiresAuthorization(): void
    {
        //galette_cli requires a team member, member one is not admin
        $member = $this->getMemberOne();

        $tokens = $this->exchangeCode($this->getAuthorizationCode($member->id, ['member']));
        $test_response = $this->requestUser($tokens['access_token']);

        $this->assertSame(401, $test_response->getStatusCode());
        $this->assertSame('application/json', $test_response->getHeaderLine('Content-Type'));
        $body = json_decode((string)$test_response->getBody(), true);
        $this->assertSame(
            "Sorry, you can't login because your are not a team member.",
            $body['message']
        );
        $this->expectLogEntry(
            \Analog\Analog::ERROR,
            "api/user() error : Sorry, you can't login because your are not a team member."
        );
    }
}
