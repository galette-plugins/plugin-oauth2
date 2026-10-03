<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Bootstrap tests file for OAuth2 plugin
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

define('GALETTE_PLUGINS_PATH', __DIR__ . '/../../');
$basepath = __DIR__ . '/../../../'; // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable -- used from Core testBootstrap

define('OAUTH2_CONFIGPATH', __DIR__ . '/config');

include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/../../../../tests/TestsBootstrap.php';
require_once __DIR__ . '/../_config.inc.php';
