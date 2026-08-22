<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Configuration
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

define('OAUTH2_LOG', true);
if (!defined('OAUTH2_DEBUGSESSION')) {
    define('OAUTH2_DEBUGSESSION', false);
}
if (!defined('OAUTH2_CONFIGPATH')) {
    define('OAUTH2_CONFIGPATH', __DIR__ . '/config'); //For more security, you can move this folder
}
define('OAUTH2_PREFIX', 'oauth2');
