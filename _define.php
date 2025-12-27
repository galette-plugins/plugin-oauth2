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

declare(strict_types=1);

/**
 * Definitions
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

$this->register(
    'Galette OAuth2',                      //Name
    'OAuth 2.0 integration',               //Short description
    'Manuel Hervouet, , Johan Cwiklinski', //Author
    '3.0.2',                               //Version
    '1.2.0',                               //Galette compatible version
    'oauth2',                              //routing name and translation domain
    '2025-12-27',                          //Release date
    [//Permissions needed
        'oauth2_authorize' => 'member'
    ],
);

$this->setCsrfExclusions([
    '/oauth2_*/',
]);
