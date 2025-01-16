<?php

/**
 * Copyright © 2021-2025 The Galette Team
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

/**
 * Bootstrap tests file for OAuth2 plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

define('GALETTE_PLUGINS_PATH', __DIR__ . '/../../');
$basepath = '../../../galette/';

define('OAUTH2_CONFIGPATH', __DIR__ . '/config');

include_once __DIR__ . '/../vendor/autoload.php';
include_once '../../../tests/TestsBootstrap.php';
include_once __DIR__ . '/../_dependencies.php';
$module = [
    'root' => __DIR__ . '/..'
];
include_once __DIR__ . '/../_routes.php';
