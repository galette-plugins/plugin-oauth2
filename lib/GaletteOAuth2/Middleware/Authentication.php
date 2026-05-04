<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Middleware;

use Analog\Analog;
use GaletteOAuth2\Repositories\ClientRepository;
use GaletteOAuth2\Tools\Debug;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use DI\Container;
use RKA\Session;
use Slim\Routing\RouteParser;

/**
 * Authentication middleware
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Authentication
{
    private Container $container;
    private RouteParser $routeparser;
    private Session $session;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->routeparser = $container->get(RouteParser::class);
        $this->session = $container->get('oauth_session');
    }

    /**
     * Middleware invokable class
     *
     * @param Request        $request PSR7 request
     * @param RequestHandler $handler PSR7 request handler
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Validate client_id before proceeding
        $queryParams = $request->getQueryParams();
        $client_id = $queryParams['client_id'] ?? null;

        $clientRepository = new ClientRepository($this->container);
        if (!$clientRepository->clientExists($client_id)) {
            Analog::log(
                sprintf(
                    'OAuth2: Invalid or missing client_id "%s" in authorization request from IP %s',
                    $client_id ?? 'null',
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ),
                Analog::WARNING
            );

            $response = new \Slim\Psr7\Response();
            $url = $this->routeparser->urlFor(
                OAUTH2_PREFIX . '_error',
                [],
                ['message' => _T('Unknown client application', 'oauth2')]
            );
            return $response->withHeader('Location', $url)->withStatus(302);
        }

        $loggedIn = $this->session->isLoggedIn ?? '';

        if ('yes' !== $loggedIn) {
            $url = $this->routeparser->urlFor(
                OAUTH2_PREFIX . '_login',
                [],
                ['redirect_url' => $_SERVER['REQUEST_URI']],
            );
            Debug::log("Redirect to {$url}");

            $response = new \Slim\Psr7\Response();
            // If the user is not logged in, redirect them to login
            return $response->withHeader('Location', $url)
                ->withStatus(302);
        }

        // The user must be logged in, so pass this request
        // down the middleware chain
        $response = $handler->handle($request);

        // And pass the request back up the middleware chain.
        return $response;
    }
}
