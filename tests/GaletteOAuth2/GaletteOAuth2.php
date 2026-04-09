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

namespace GaletteOAuth2;

use Galette\Tests\GaletteTestCase;

/**
 * UserHelper tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteOAuth2 extends GaletteTestCase
{
    protected int $seed = 20240613200350;
    protected bool $load_plugins = true;
    protected bool $db_transactions = false;

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
     * Test stripAccents
     *
     * @return void
     */
    public function testFlow(): void
    {
        $member_one = $this->getMemberOne();
        $data = $this->dataAdherentOne();
        $this->getAdminMember($member_one);

        $provider = new \Galette\OAuth2\Client\Provider\Galette([
            //information related to the app where you will use galette-oauth2
            'clientId'      => 'galette_cli',          // The client ID assigned to you
            'clientSecret'  => '4567zyx',      // The client password assigned to you
            'redirectUri'   => 'http://localhost:8888', // The return URL you specified for your app
            //information related to the galette instance you want to connect to
            'instance'      => 'http://localhost:8888',    // The instance of Galette you want to connect to
            'pluginDir'     => 'oauth2',   // The directory where the plugin is installed - defaults to 'plugin-oauth2'
        ]);

        $options = [
            'scope' => 'member member:localization'
        ];

        // Get authorization URL
        $authorizationUrl = $provider->getAuthorizationUrl($options);
        //echo $authorizationUrl;

        // Get state and store it to the session
        $state = $provider->getState();

        // Use a cookie jar - disable auto redirects to manually control cookie handling
        $jar = new \GuzzleHttp\Cookie\CookieJar();

        $guzzle = new \GuzzleHttp\Client([
            'cookies' => $jar,
            'allow_redirects' => false, // Disable automatic redirects
            'timeout' => 5,
            'http_errors' => false, // Don't throw exceptions on 4xx/5xx responses
        ]);

        //do login
        $login_url = str_replace('/authorize', '/login', $authorizationUrl);
        $login_url .= "&redirect_url=" . urlencode($authorizationUrl);

        // GET login page
        $response = $guzzle->request('GET', $login_url);
        $this->assertSame(200, $response->getStatusCode());

        // Debug: log cookies after GET login
        echo "\nCookies after GET login:\n";
        foreach ($jar as $cookie) {
            echo "  - {$cookie->getName()} = {$cookie->getValue()} (domain: {$cookie->getDomain()})\n";
        }

        // POST login credentials
        $response = $guzzle->request('POST', $login_url, [
            'form_params' => [
                'login' => $data['login_adh'],
                'password' => $data['mdp_adh']
            ]
        ]);

        // Debug: log response after POST login
        echo "POST login response status: " . $response->getStatusCode() . "\n";
        echo "POST login Location header: " . ($response->getHeader('Location')[0] ?? 'none') . "\n";

        // Debug: log cookies after POST login
        echo "Cookies after POST login:\n";
        foreach ($jar as $cookie) {
            echo "  - {$cookie->getName()} = {$cookie->getValue()} (domain: {$cookie->getDomain()})\n";
        }

        // After successful login, we should get a 302 redirect to authorize
        $this->assertContains($response->getStatusCode(), [301, 302], 'Login should redirect');
        $location = $response->getHeader('Location')[0] ?? '';
        $this->assertNotEmpty($location, 'Login should provide a Location header');

        // If redirected to login again, login failed
        $this->assertStringNotContainsString('/login', $location, 'Login should not redirect back to login page');

        // Follow redirect to authorize page (if redirected)
        if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) {
            $response = $guzzle->request('GET', $location);
            $this->assertSame(200, $response->getStatusCode(), 'Authorize page should return 200');
        }

        // POST authorization approval
        $response = $guzzle->request('POST', $authorizationUrl, [
            'form_params' => [
                'approve' => true
            ]
        ]);

        // Debug: log cookies after POST authorize
        echo "POST authorize response status: " . $response->getStatusCode() . "\n";
        echo "Cookies after POST authorize:\n";
        foreach ($jar as $cookie) {
            echo "  - {$cookie->getName()} = {$cookie->getValue()} (domain: {$cookie->getDomain()})\n";
        }

        // After approval, we should get a 302 redirect with the authorization code
        $this->assertSame(302, $response->getStatusCode(), 'Authorization should redirect with code');
        $redirected_uri = $response->getHeader('Location')[0] ?? '';
        $this->assertNotEmpty($redirected_uri, 'Authorization should provide a redirect URI');

        echo "Final redirect URI: " . $redirected_uri . "\n";

        parse_str(parse_url($redirected_uri, PHP_URL_QUERY), $url_arguments);

        $this->assertIsArray($url_arguments);
        $this->assertArrayHasKey('code', $url_arguments);
        $this->assertArrayHasKey('state', $url_arguments);

        $get_code = $url_arguments['code'];
        $get_state = $url_arguments['state'];

        $this->assertSame($state, $get_state);

        // Get access token
        $accessToken = $provider->getAccessToken(
            'authorization_code',
            [
                'code' => $get_code
            ]
        );
        $this->assertInstanceOf(\League\OAuth2\Client\Token\AccessToken::class, $accessToken);

        // Get resource owner
        $resourceOwner = $provider->getResourceOwner($accessToken);
        $resourceOwner_array = $resourceOwner->toArray();
        $this->assertInstanceOf(\Galette\OAuth2\Client\Provider\GaletteResourceOwner::class, $resourceOwner);

        //check values
        $this->assertSame($member_one->id, $resourceOwner->getId());
        $this->assertSame($data['login_adh'], $resourceOwner->getUsername());
        $this->assertSame($data['email_adh'], $resourceOwner->getEmail());
        //due date scope is requested from configuration file
        $this->assertArrayHasKey('due_date', $resourceOwner_array);
    }
}
