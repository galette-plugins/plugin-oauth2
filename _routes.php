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

require_once '_dependencies.php';

//login is always called by a http_redirect
$app->map(
    ['GET', 'POST'],
    '/login',
    [LoginController::class, 'login']
)->setName(OAUTH2_PREFIX . '_login');

$app->map(
    ['GET', 'POST'],
    '/logout',
    [LoginController::class, 'logout']
)->setName(OAUTH2_PREFIX . '_logout');

$app->map(
    ['GET', 'POST'],
    '/authorize',
    [AuthorizationController::class, 'authorize']
)
->setName(OAUTH2_PREFIX . '_authorize')
->add(Authentication::class);

$app->post(
    '/access_token',
    [AuthorizationController::class, 'token']
)->setName(OAUTH2_PREFIX . '_token');

$app->get(
    '/user',
    [ApiController::class, 'user']
)->setName(OAUTH2_PREFIX . '_user');
