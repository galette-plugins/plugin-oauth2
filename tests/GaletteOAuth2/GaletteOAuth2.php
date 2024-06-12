<?php

/**
 * Copyright © 2021-2024 The Galette Team
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

use Galette\GaletteTestCase;

/**
 * UserHelper tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class GaletteOAuth2 extends GaletteTestCase
{
    protected int $seed = 20240613200350;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);
    }

    /**
     * Test stripAccents
     *
     * @return void
     */
    public function testFlow(): void
    {
        $this->initStatus();
        $adh1  = $this->getMemberOne();

        $data = $this->dataAdherentOne();
        $data['bool_admin_adh'] = true;
        $check = $this->adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $this->adh->store();
        $this->assertTrue($store);

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

        $jar = new \GuzzleHttp\Cookie\CookieJar(
            false,
             [
                 [
                    'Name' => 'session_id',
                    'Value' => 'galette-oauthtests-session',
                    'Domain' => 'localhost'
                ]
            ],
        );
        $guzzle = new \GuzzleHttp\Client([
            'cookies' => $jar,
            'allow_redirects' => ['track_redirects' => true],
            'timeout' => 5,
        ]);

        //do login
        $login_url = str_replace('/authorize', '/login', $authorizationUrl);
        $login_url .= "&redirect_url=" . urlencode($authorizationUrl);
        $response = $guzzle->request('GET', $login_url);
        $response = $guzzle->request('POST', $login_url, [
            'form_params' => [
                'login' => $data['login_adh'],
                'password' => $data['mdp_adh']
            ]
        ]);

        $response = $guzzle->request('POST', $authorizationUrl, [
            'form_params' => [
                'approve' => true
            ]
        ]);

        //get code and status from redirected URL
        $headersRedirect = $response->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
        $redirected_uri = $headersRedirect[0];
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
        $this->assertSame($adh1->id, $resourceOwner->getId());
        $this->assertSame('r.durand', $resourceOwner->getUsername()); //not a Galette data
        $this->assertSame($data['email_adh'], $resourceOwner->getEmail());
        //due date scoep is requested from configuration file
        $this->assertArrayHasKey('due_date', $resourceOwner_array);
    }
}
