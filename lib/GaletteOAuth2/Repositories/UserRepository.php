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
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        Debug::log("getUserEntityByUserCredentials({$username}, '***', {$grantType}) ");
        if (UserHelper::login($this->container, $username, $password)) {
            //FIXME? Returned entity may be related to just logged-in user?
            return new UserEntity();
        }

        return null;
    }
}
