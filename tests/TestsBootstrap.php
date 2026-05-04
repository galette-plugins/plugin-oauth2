<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
