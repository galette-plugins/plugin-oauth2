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

declare(strict_types=1);

namespace GaletteOAuth2\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use DI\Container;
use Exception;
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Authorization\UserAuthorizationException;
use GaletteOAuth2\Authorization\UserHelper;
use GaletteOAuth2\Entities\UserEntity;
use GaletteOAuth2\Repositories\ScopeRepository;
use GaletteOAuth2\Tools\Config as Config;
use GaletteOAuth2\Tools\Debug as Debug;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Controller for authorization
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class AuthorizationController extends AbstractPluginController
{
    /**
     * @var array<string, mixed>
     */
    #[Inject("Plugin Galette OAuth2")]
    protected array $module_info;
    protected Container $container;
    protected Config $config;

    /**
     * Default constructor
     *
     * @param Container $container Container instance
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $this->container->get(Config::class);
        parent::__construct($container);
    }

    /**
     * Display authorization form
     *
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function authorize(Request $request, Response $response): Response|ResponseInterface
    {
        Debug::logRequest('authorization/authorize()', $request);

        $server = $this->container->get(AuthorizationServer::class);

        try {
            $queryParams = $request->getQueryParams();
            $client_id = $queryParams['client_id'];

            //Save redirect_uri (it's not possible with Sessions)
            //FIXME [JC]: I really do not like the idea of using a file on disk;
            // this may also cause severe issues in case of concurrent logins
            if (isset($queryParams['redirect_uri'])) {
                $key = $client_id . '.redirect_uri';
                if (!isset($this->session->$client_id)) {
                    $this->session->$client_id = new \stdClass();
                }
                $this->session->$client_id->redirect_uri = $queryParams['redirect_uri'];
                $v = $queryParams['redirect_uri'];

                if ($this->config->get($key, '') === '') {
                    $filename = OAUTH2_PREFIX . '_' . $key . '.txt';
                    Debug::log("Auto add redirect_uri to cache $filename: $v");

                    $this->config->set($key, $v);
                    $stream = fopen(GALETTE_CACHE_DIR . '/' . $filename, 'w+');
                    fwrite(
                        $stream,
                        $v
                    );
                    fclose($stream);

                    Analog::log(
                        'Auto add redirect_uri ok.',
                        Analog::DEBUG
                    );
                }
            }

            // Validate the HTTP request and return an AuthorizationRequest object.
            // The auth request object can be serialized into a user's session
            $authRequest = $server->validateAuthorizationRequest($request);

            $user = new UserEntity();
            //FIXME: for both isLoggedIn and user_id, we can rely on login object stored in session
            $user->setIdentifier($this->session->user_id);
            $authRequest->setUser($user);

            $scopes = UserHelper::mergeScopes(
                $this->config,
                $client_id,
                $queryParams['scope'] ?? [],
                true
            );

            $this->view->render(
                $response,
                $this->getTemplate(OAUTH2_PREFIX . '_authorize'),
                [
                    'page_title' => 'Authorize',
                    'clientName' => $authRequest->getClient()->getName(),
                    'requested_scopes' => $scopes,
                    'known_scopes' => ScopeRepository::knownScopes(),
                    'queryParams' => $queryParams,
                    'querystring' => $request->getUri()->getQuery()
                ]
            );
            return $response;
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        } catch (Exception $exception) {
            $body = $response->getBody();
            $body->write($exception->getMessage());

            return $response->withStatus(500)->withBody($body);
        }
    }

    /**
     * Proceed authorization
     *
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function doAuthorize(Request $request, Response $response): Response|ResponseInterface
    {
        Debug::logRequest('authorization/doAuthorize()', $request);

        $server = $this->container->get(AuthorizationServer::class);

        try {
            $params = (array)$request->getParsedBody();
            $queryParams = $request->getQueryParams();

            // Validate the HTTP request and return an AuthorizationRequest object.
            // The auth request object can be serialized into a user's session
            $authRequest = $server->validateAuthorizationRequest($request);
            $user = new UserEntity();
            //FIXME: for both isLoggedIn and user_id, we can rely on login object stored in session
            $user->setIdentifier($this->session->user_id);
            $authRequest->setUser($user);

            // Once the user has approved or denied the client update the status
            // (true = approved, false = denied)
            if (isset($params['approve'])) {
                $authRequest->setAuthorizationApproved(true);
                $scopes = UserHelper::mergeScopes(
                    null,
                    $queryParams['client_id'],
                    $params['scopes'] ?? [],
                    true
                );
                $req_scopes = [];
                $srepo = new ScopeRepository();
                foreach ($scopes as $scope) {
                    $req_scopes[] = $srepo->getScopeEntityByIdentifier($scope);
                }
                $authRequest->setScopes($req_scopes);
            } else {
                $authRequest->setAuthorizationApproved(true);
                $authRequest->setScopes([]);

                throw OAuthServerException::accessDenied(
                    sprintf(
                        _T('Default scope (%s) has not been authorized.', 'oauth2'),
                        'member'
                    )
                );
            }

            // Return the HTTP redirect response
            $r = $server->completeAuthorizationRequest($authRequest, $response);
            Analog::log(
                'authorization/doAuthorize() exit ok',
                Analog::DEBUG
            );

            return $r;
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        } catch (Exception $exception) {
            $body = $response->getBody();
            $body->write($exception->getMessage());

            return $response->withStatus(500)->withBody($body);
        } finally {
            $this->login->logout();
        }
    }

    public function token(Request $request, Response $response): Response|ResponseInterface
    {
        Debug::logRequest('authorization/token()', $request);
        $server = $this->container->get(AuthorizationServer::class);
        $params = (array)$request->getParsedBody(); //POST

        try {
            // Try to respond to the access token request
            $r = $server->respondToAccessTokenRequest($request, $response);
            Debug::log('authorization/token() exit ok');

            return $r;
        } catch (OAuthServerException $exception) {
            Debug::log('authorization/OAuthServerException: ' . $exception->getMessage());
            // All instances of OAuthServerException can be converted to a PSR-7 response
            return $exception->generateHttpResponse($response);
        } catch (Exception $exception) {
            Debug::log(
                'authorization/Exception: ' .
                $exception->getMessage() . '<br>' . $exception->getTraceAsString()
            );
            // Catch unexpected exceptions
            $body = $response->getBody();
            $body->write($exception->getMessage());

            return $response->withStatus(500)->withBody($body);
        }
    }
}
