<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Routes
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

use GaletteOAuth2\Controllers\ApiController;
use GaletteOAuth2\Controllers\AuthorizationController;
use GaletteOAuth2\Controllers\LoginController;
use GaletteOAuth2\Middleware\Authentication;

//Include specific classes (league/oauth2_server and tools)
require_once 'vendor/autoload.php';

//Constants and classes from plugin
require_once $module['root'] . '/_config.inc.php';

require '_dependencies.php';

//login is always called by a http_redirect
$app->get(
    '/login',
    [LoginController::class, 'login']
)->setName(OAUTH2_PREFIX . '_login');

$app->post(
    '/login',
    [LoginController::class, 'doLogin']
)->setName(OAUTH2_PREFIX . '_doLogin');

$app->map(
    ['GET', 'POST'],
    '/logout',
    [LoginController::class, 'logout']
)->setName(OAUTH2_PREFIX . '_logout');

$app->get(
    '/error',
    [LoginController::class, 'error']
)->setName(OAUTH2_PREFIX . '_error');

$app->get(
    '/authorize',
    [AuthorizationController::class, 'authorize']
)->setName(OAUTH2_PREFIX . '_authorize')->add(Authentication::class);

$app->post(
    '/authorize',
    [AuthorizationController::class, 'doAuthorize']
)->setName(OAUTH2_PREFIX . '_doAuthorize')->add(Authentication::class);

$app->post(
    '/access_token',
    [AuthorizationController::class, 'token']
)->setName(OAUTH2_PREFIX . '_token');

$app->get(
    '/user',
    [ApiController::class, 'user']
)->setName(OAUTH2_PREFIX . '_user');

// Test callback route for E2E tests (only in test environment)
if (getenv('GALETTE_TESTS') !== false || defined('GALETTE_TESTS')) {
    $app->get(
        '/test-callback',
        function ($request, $response) {
            $params = $request->getQueryParams();
            $html = '<!DOCTYPE html><html lang="en"><body><h1>OAuth2 Test Callback</h1>';
            $html .= '<pre>' . htmlspecialchars(print_r($params, true)) . '</pre>';
            $html .= '</body></html>';
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html');
        }
    )->setName(OAUTH2_PREFIX . '_test_callback');
}
