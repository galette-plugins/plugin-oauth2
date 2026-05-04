<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Router file to be launched by php -S
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

$db = 'mysql';
$dbenv = getenv('DB');
if (
    $dbenv === 'pgsql'
    || substr($dbenv, 0, strlen('postgres')) === 'postgres'
) {
    $db = 'pgsql';
}

$basepath = '../../tests/';
define('GALETTE_CONFIG_PATH', $basepath . 'config/' . $db . '/');
define('OAUTH2_CONFIGPATH', __DIR__ . '/config');
return false;
