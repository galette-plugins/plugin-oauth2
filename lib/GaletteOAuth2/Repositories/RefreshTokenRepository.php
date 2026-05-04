<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Repositories;

use GaletteOAuth2\Entities\RefreshTokenEntity;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * Refresh token repository
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        // Some logic to persist the refresh token in a database
    }

    public function revokeRefreshToken($tokenId): void
    {
        // Some logic to revoke the refresh token in a database
    }

    public function isRefreshTokenRevoked($tokenId)
    {
        return false; // The refresh token has not been revoked
    }

    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }
}
