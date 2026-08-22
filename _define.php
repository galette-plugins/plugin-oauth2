<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Definitions
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

/** @var \Galette\Core\Plugins $this */
$this->register(
    name: 'Galette OAuth2',                        //Name
    desc: 'OAuth 2.0 integration',                 //Short description
    author: 'Manuel Hervouet, , Johan Cwiklinski', //Author
    version: '3.0.2',                              //Version
    compver: '1.3.0',                              //Galette compatible version
    route: 'oauth2',                               //routing name and translation domain
    date: '2025-12-27',                            //Release date
    acls: [                                        //Permissions needed
        'oauth2_authorize' => 'member'
    ],
);

$this->setCsrfExclusions([
    '/oauth2_(token|user)/',
]);
