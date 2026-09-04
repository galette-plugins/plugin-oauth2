<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Repositories;

use DI\Container;
use GaletteOAuth2\Authorization\UserHelper;
use GaletteOAuth2\Entities\UserEntity;
use GaletteOAuth2\Tools\Debug;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

/**
 * User repository
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class UserRepository implements UserRepositoryInterface
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        Debug::log("getUserEntityByUserCredentials({$username}, '***', {$grantType}) ");
        $user_id = UserHelper::login($this->container, $username, $password);
        if ($user_id !== false) {
            $user = new UserEntity();
            $user->setIdentifier((string)$user_id);
            return $user;
        }

        return null;
    }
}
