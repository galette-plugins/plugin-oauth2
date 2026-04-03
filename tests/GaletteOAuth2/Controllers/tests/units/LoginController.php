<?php

/**
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
 */

namespace GaletteOAuth2\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;

/**
 * Login controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class LoginController extends GaletteRoutingTestCase
{
    protected int $seed = 20250921223808;
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
     * Test display login page
     *
     * @return void
     */
    public function testLogin(): void
    {
        $this->assertArrayNotHasKey('plugin-oauth2', $this->plugins->getDisabledModules());
        $this->assertArrayHasKey('plugin-oauth2', $this->plugins->getModules());

        $route_name = OAUTH2_PREFIX . '_login';
        $query_params = [
            'redirect_url' => $this->routeparser->urlFor(
                OAUTH2_PREFIX . '_authorize',
                [],
                [
                    'scope' => 'member',
                    'state' => '7d627422092a7a5ac413ac597312b9b4',
                    'response_type' => 'code',
                    'approval_prompt' => 'auto',
                    'redirect_uri' => 'http://flarum.localhost/auth/passport',
                    'client_id' => 'galette_flarum'
                ]
            )
        ];
        $request = $this->createRequest(
            route_name: $route_name,
            query_params: $query_params
        );

        $test_response = $this->app->handle($request);
        $this->expectOK($test_response);
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('Sign in Forum Flarum', $body);
    }

    /**
     * Test login
     *
     * @return void
     */
    public function testDoLogin(): void
    {
        $this->assertArrayNotHasKey('plugin-oauth2', $this->plugins->getDisabledModules());
        $this->assertArrayHasKey('plugin-oauth2', $this->plugins->getModules());

        $route_name = OAUTH2_PREFIX . '_login';
        $query_params = [
            'redirect_url' => $this->routeparser->urlFor(
                OAUTH2_PREFIX . '_authorize',
                [],
                [
                    'scope' => 'member',
                    'state' => '7d627422092a7a5ac413ac597312b9b4',
                    'response_type' => 'code',
                    'approval_prompt' => 'auto',
                    'redirect_uri' => 'http://flarum.localhost/auth/passport',
                    'client_id' => 'galette_flarum'
                ]
            )
        ];
        $request = $this->createRequest(
            route_name: $route_name,
            query_params: $query_params,
            method: 'POST'
        );

        $request = $request->withParsedBody([
            'login' => 'jdoe',
            'password' => 'password'
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor(OAUTH2_PREFIX . '_login')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(
            \Analog::WARNING,
            'No entry found for login `jdoe`'
        );
        //flash data are stored in plugin specific session... No way from tests to check them, including on redirected page

        $request = $request->withParsedBody([
            'login' => 'admin',
            'password' => 'admin'
        ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(['Location' => [$this->routeparser->urlFor(OAUTH2_PREFIX . '_login')]], $test_response->getHeaders());
        $this->assertSame(301, $test_response->getStatusCode());
        $this->expectLogEntry(
            \Analog::WARNING,
            'OAuth login attempt from superadmin account'
        );
        //flash data are stored in plugin specific session... No way from tests to check them, including on redirected page
    }
}
