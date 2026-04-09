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

declare(strict_types=1);

namespace GaletteOAuth2\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use DI\Container;
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Authorization\UserAuthorizationException;
use GaletteOAuth2\Authorization\UserHelper;
use GaletteOAuth2\Repositories\ClientRepository;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;
use RKA\Session;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Controller for Login
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class LoginController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette OAuth2")]
    protected array $module_info;
    #[Inject]
    protected Container $container;
    #[Inject]
    protected Config $config;
    #[Inject("oauth_session")]
    protected Session $session;

    /**
     * Display login form
     *
     * @param Request  $request  Received request
     * @param Response $response Response instance
     */
    public function login(Request $request, Response $response): Response
    {
        Debug::logRequest('login()', $request);

        $redirect_url = $request->getQueryParams()['redirect_url'] ?? false;

        //FIXME: redirect URL seems required: session request_args is considered as existing in self::prepareVarsForm()
        if ($redirect_url) {
            $url = urldecode($redirect_url);
            $url_query = parse_url($url, PHP_URL_QUERY);
            parse_str($url_query, $url_args);
            $this->session->request_args = $url_args;
        }

        /** @phpstan-ignore-next-line */
        if (OAUTH2_DEBUGSESSION) {
            Debug::log('GET _SESSION = ' . Debug::printVar($this->session));
        }

        // Validate client_id before displaying login form
        $vars = $this->prepareVarsForm();
        if ($vars === null) {
            return $response
                ->withStatus(302)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(
                        OAUTH2_PREFIX . '_error',
                        [],
                        ['message' => _T('Unknown client application', 'oauth2')]
                    )
                );
        }

        // display page
        $this->view->render(
            $response,
            $this->getTemplate(OAUTH2_PREFIX . '_login'),
            $vars
        );
        return $response;
    }

    /**
     * Do login
     *
     * @param Request  $request  Received request
     * @param Response $response Response instance
     */
    public function doLogin(Request $request, Response $response): Response
    {
        /** @phpstan-ignore-next-line */
        if (OAUTH2_DEBUGSESSION) {
            Debug::log('POST _SESSION = ' . Debug::printVar($this->session));
        }

        // Get all POST parameters
        $params = (array)$request->getParsedBody();

        //Try login
        //FIXME: for both isLoggedIn and user_id, we can rely on login object stored in session
        $this->session->isLoggedIn = 'no';
        $this->session->user_id = $uid = UserHelper::login($this->container, $params['login'], $params['password']);
        Debug::log("UserHelper::login({$params['login']}) return '{$uid}'");

        if (false === $uid) {
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(OAUTH2_PREFIX . '_login')
                );
        }

        try {
            $client_id = $this->session->request_args['client_id'];
            UserHelper::getUserData(
                $this->container,
                $uid,
                UserHelper::getAuthorization($this->config, $client_id),
                UserHelper::mergeScopes(
                    $this->config,
                    $client_id,
                    $this->session->request_args['scope'] ?? [],
                    true
                ),
                (bool)$this->config->get($client_id . '.legacy_data', false)
            );
        } catch (UserAuthorizationException $e) {
            UserHelper::logout($this->container);
            Debug::log('login() check rights error ' . $e->getMessage());

            $this->flash->addMessage(
                'error_detected',
                $e->getMessage()
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor(OAUTH2_PREFIX . '_login')
                );
        }

        //FIXME: for both isLoggedIn and user_id, we can rely on login object stored in session
        $this->session->isLoggedIn = 'yes';

        // User is logged in, redirect them to authorize
        $url_params = [
            'response_type' => $this->session->request_args['response_type'],
            'client_id' => $this->session->request_args['client_id'],
            'scope' => $this->session->request_args['scope'] ?? [],
            'redirect_uri' => $this->session->request_args['redirect_uri'],
        ];
        if (isset($this->session->request_args['state'])) {
            $url_params['state'] = $this->session->request_args['state'];
        }

        $url = $this->routeparser->urlFor(OAUTH2_PREFIX . '_authorize', [], $url_params);

        $response = new Response();

        return $response->withHeader('Location', $url)
            ->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        Debug::logRequest('logout()', $request);
        UserHelper::logout($this->container);

        unset(
            $this->session->user_id,
            $this->session->isLoggedIn,
            $this->session->request_args
        );
        session_destroy();

        $redirect_logout = $this->routeparser->urlFor('slash');
        if ($client_id = $this->session->request_args['client_id'] ?? null) {
            $redirect_logout = $this->config->get("{$client_id}.redirect_logout", $redirect_logout);
            Debug::log("logout():url_logout for client:'{$client_id}' = '{$redirect_logout}'");
        }

        //Add an url redirection in config.yml: $client_id:   redirect_logout:"https:\\xxx");
        return $response->withHeader('Location', $redirect_logout)->withStatus(302);
    }

    /**
     * Display error page
     *
     * @param Request  $request  Received request
     * @param Response $response Response instance
     */
    public function error(Request $request, Response $response): Response
    {
        Debug::logRequest('error()', $request);

        $error_message = $request->getQueryParams()['message'] ?? _T('An error occurred', 'oauth2');

        $this->view->render(
            $response,
            $this->getTemplate(OAUTH2_PREFIX . '_error'),
            [
                'page_title' => _T('OAuth2 error', 'oauth2'),
                'error_message' => $error_message
            ]
        );
        return $response;
    }

    private function prepareVarsForm(): ?array
    {
        $client_id = $this->session->request_args['client_id'] ?? null;

        // Validate client_id exists
        if ($client_id === null || $client_id === '') {
            Analog::log(
                sprintf(
                    'OAuth2: Missing client_id in request from IP %s',
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ),
                Analog::WARNING
            );
            return null;
        }

        // Check if client exists in configuration
        $clientRepository = new ClientRepository($this->container);
        if (!$clientRepository->clientExists($client_id)) {
            Analog::log(
                sprintf(
                    'OAuth2: Invalid client_id "%s" in request from IP %s',
                    $client_id,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ),
                Analog::WARNING
            );
            return null;
        }

        $server_title = $this->config->get('global.title', 'Galette');
        $sign_in_with = sprintf(
            _T('Sign in with %s', 'oauth2'),
            $server_title
        );
        $application = $this->config->get("{$client_id}.title", '');
        $page_title = sprintf(
            _T('Sign in %s', 'oauth2'),
            $application
        );

        return [
            'sign_in_with' => $sign_in_with,
            'page_title' => $page_title,
            'prefix' => OAUTH2_PREFIX
        ];
    }
}
