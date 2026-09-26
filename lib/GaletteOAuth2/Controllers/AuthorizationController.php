<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Controllers;

use Analog\Analog;
use DI\Attribute\Inject;
use DI\Container;
use Exception;
use Galette\Controllers\AbstractPluginController;
use GaletteOAuth2\Authorization\UserHelper;
use GaletteOAuth2\Entities\UserEntity;
use GaletteOAuth2\Repositories\ScopeRepository;
use GaletteOAuth2\Tools\Config as Config;
use GaletteOAuth2\Tools\Debug as Debug;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use RKA\Session;
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
    #[Inject("oauth_session")]
    protected Session $session;

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

            // Validate the HTTP request and return an AuthorizationRequest object.
            // The auth request object can be serialized into a user's session
            $authRequest = $server->validateAuthorizationRequest($request);

            $user = new UserEntity();
            //FIXME: for both isLoggedIn and user_id, we can rely on login object stored in session
            $user->setIdentifier((string)$this->session->user_id);
            $authRequest->setUser($user);

            $server_title = $this->config->get('global.title', 'Galette');
            $sign_in_with = sprintf(
                _T('Sign in with %s', 'oauth2'),
                $server_title
            );
            $application = $this->config->get("{$client_id}.title", 'noname');
            $page_title = sprintf(
                _T('%s is requesting access to the following details', 'oauth2'),
                $application
            );
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
                    'sign_in_with' => $sign_in_with,
                    'page_title' => $page_title,
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
            return $this->errorResponse($response, $exception);
        }
    }

    /**
     * Proceed authorization
     *
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
            $user->setIdentifier((string)$this->session->user_id);
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
                    $scope_entity = $srepo->getScopeEntityByIdentifier($scope);
                    if ($scope_entity !== null) {
                        $req_scopes[] = $scope_entity;
                    }
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
            return $this->errorResponse($response, $exception);
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
            // Catch unexpected exceptions
            return $this->errorResponse($response, $exception);
        }
    }

    /**
     * Log an unexpected error, without disclosing its details
     */
    private function errorResponse(Response $response, Exception $exception): ResponseInterface
    {
        Analog::log(
            'OAuth2 error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            Analog::ERROR
        );
        $response->getBody()->write(_T('An error occurred', 'oauth2'));

        return $response->withStatus(500);
    }
}
