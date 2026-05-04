<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Authorization;

use Exception;

/**
 * Exception thrown when user is not authorized
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 */
final class UserAuthorizationException extends Exception
{
}
