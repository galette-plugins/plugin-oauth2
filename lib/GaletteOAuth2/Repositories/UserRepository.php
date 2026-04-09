<?php

/**
 *  This file is part of 'Galette OAuth2 plugin'.
 *  Galette OAuth2 Plugin is free software: you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *
 *  Galette OAuth2 Plugin is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *
 * Galette is free software: you can redistribute it and/or modify
 *  with Galette OAuth2 Plugin. If not, see <http://www.gnu.org/licenses/>.
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

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Idaas\OpenID\Repositories\UserRepositoryInterface;
use Idaas\OpenID\Repositories\UserRepositoryTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use Psr\Container\ContainerInterface as ContainerInterface;
use Galette\Entity\Adherent;
use GaletteOAuth2\Entities\UserEntity;
use Galette\Entity\Status as GaletteStatus;

/**
 * User repository
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class UserRepository implements UserRepositoryInterface
{
    use UserRepositoryTrait;

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getAttributes(UserEntityInterface $userEntity, $claims, $scopes)
    {
        $attributes = [];
        $zdb = $this->container->get('zdb');
        $adherent = new Adherent($zdb);
        $adherent->load(intval($userEntity->getIdentifier()));

        $scope_names = array_map(function ($s) {
            return $s->getIdentifier();
        }, $scopes);

        if (in_array('profile', $scope_names)) {
            $attributes['family_name'] = \ucwords(\mb_strtolower($adherent->name));
            $attributes['given_name'] = \ucwords(\mb_strtolower($adherent->surname));
            $attributes['name'] = $adherent->sname;
            $attributes['nickname'] = \mb_strtolower($adherent->nickname);
            $attributes['locale'] = $adherent->language;
            $attributes['preferred_username'] = $adherent->login;
            if ($adherent->isMan()) {
                $attributes['gender'] = 'male';
            }
            if ($adherent->isWoman()) {
                $attributes['gender'] = 'female';
            }
            $updated_at = \DateTime::createFromFormat('!' . __("Y-m-d"), $adherent->modification_date);
            $attributes['updated_at'] = $updated_at->getTimestamp();
        }

        if (in_array('email', $scope_names)) {
            $attributes['email'] = $adherent->email;
        }

        if (in_array('galette', $scope_names)) {
            $attributes['galette_uptodate'] = ($adherent->isActive() && $adherent->isUp2Date()) || $adherent->isAdmin();
            $attributes['galette_status'] = $adherent->status;
            $attributes['galette_status_priority'] = (new GaletteStatus($zdb, $adherent->status))->priority;
            $attributes['galette_staff'] = $adherent->isStaff();

            $attributes['galette_groups'] = [];
            foreach ($adherent->getGroups() as $galette_group) {
                $attributes['galette_groups'][] = self::normalizeGaletteGroup($galette_group);
            }

            $attributes['galette_managed_groups'] = [];
            foreach ($adherent->getManagedGroups() as $galette_group) {
                $attributes['galette_managed_groups'][] = self::normalizeGaletteGroup($galette_group);
            }
        }

        if (in_array('phone', $scope_names)) {
            $attributes['phone'] = $adherent->phone;
        }

        if (in_array('nextcloud', $scope_names)) {
            $attributes['groups'] = [];
            $attributes['groups'][] = $adherent->getDynamicFields()->getValues(4)[0]['text_val'];
        }

        return $attributes;
    }

    private static function normalizeGaletteGroup($group)
    {
        $path = [];
        $current_group = $group;
        while ($current_group) {
            array_unshift($path, $current_group->getName());
            $current_group = $current_group->getParentGroup();
        }
        return $path;
    }

    public function getUserInfoAttributes(UserEntityInterface $userEntity, $claims, $scopes)
    {
        $attributes = $this->getAttributes($userEntity, $claims, $scopes);
        $attributes['sub'] = $userEntity->getIdentifier();
        return $attributes;
    }

    public function getUserByIdentifier($identifier): ?UserEntityInterface
    {
        $user = new UserEntity();
        $user->setIdentifier($identifier);
        return $user;
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $grantType
     * @param ClientEntityInterface $clientEntity
     * @return UserEntityInterface|null
     */
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        $login = $this->container->get(\Galette\Core\Login::class);
        $preferences = $this->container->get(\Galette\Entity\Config::class);
        
        // Try to login
        if ($login->login($username, $password, true)) {
            $user = new UserEntity();
            $user->setIdentifier((string)$login->id);
            return $user;
        }

        return null;
    }
}
