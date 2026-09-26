<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * API controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ApiController extends GaletteRoutingTestCase
{
    protected int $seed = 20260926203000;
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
     * Test user data require a valid access token
     *
     * @return void
     */
    public function testUserWithoutValidToken(): void
    {
        $request = $this->createRequest(route_name: OAUTH2_PREFIX . '_user');
        $test_response = $this->app->handle($request);

        $this->assertSame(401, $test_response->getStatusCode());
        $this->assertSame('application/json', $test_response->getHeaderLine('Content-Type'));
        $body = json_decode((string)$test_response->getBody(), true);
        $this->assertSame('access_denied', $body['error']);

        $request = $request->withHeader('Authorization', 'Bearer not-a-valid-token');
        $test_response = $this->app->handle($request);

        $this->assertSame(401, $test_response->getStatusCode());
        $this->assertSame('application/json', $test_response->getHeaderLine('Content-Type'));
        $body = json_decode((string)$test_response->getBody(), true);
        $this->assertSame('access_denied', $body['error']);
    }
}
