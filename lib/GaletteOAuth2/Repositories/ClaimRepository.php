<?php

/**
 * Copyright © 2021-2026 The Galette Team
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
 * OpenID Connect Claim Repository
 *
 * Provides mapping between OIDC scopes and claims.
 *
 * @author Florian Hatat <github@hatat.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

namespace GaletteOAuth2\Repositories;

use Idaas\OpenID\Repositories\ClaimRepositoryInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use GaletteOAuth2\Entities\ClaimEntity;

class ClaimRepository implements ClaimRepositoryInterface
{
    public static $scopeClaims = [
        'profile' => [
            ['name', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['family_name', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['given_name', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['middle_name', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['nickname', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['preferred_username', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['profile', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['picture', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['website', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['gender', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['birthdate', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['zoneinfo', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['locale', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['updated_at', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
        ],
        'email' => [
            ['email', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['email_verified', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
        ],
        'galette' => [
            ['galette_uptodate', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['galette_status', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['galette_status_priority', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['galette_staff', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['galette_groups', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
            ['galette_managed_groups', \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO, false],
        ],
    ];

    public static function getScopeClaims()
    {
        return self::$scopeClaims;
    }

    public static function getAllClaims()
    {
        $res = [];
        foreach (self::getScopeClaims() as $claims) {
            foreach ($claims as $claim) {
                $res[] = $claim[0];
            }
        }
        return $res;
    }

    /**
     * Return information about a claim.
     *
     * @param string $identifier The claim identifier
     */
    public function getClaimEntityByIdentifier(string $identifier, mixed $type, mixed $essential): ?ClaimEntityInterface
    {
        return new ClaimEntity($identifier, $type, $essential);
    }

    /**
     * @return ClaimEntityInterface[]
     */
    public function getClaimsByScope(ScopeEntityInterface $scope): iterable
    {
        $res = [];
        foreach ($this->getScopeClaims()[$scope->getIdentifier()] ?? [] as $claim) {
            $res[] = $this->getClaimEntityByIdentifier($claim[0], $claim[1], $claim[2]);
        }
        return $res;
    }

    public function claimsRequestToEntities(array $json = null)
    {
        $res = [];
        foreach ([\Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_ID_TOKEN, \Idaas\OpenID\Entities\ClaimEntityInterface::TYPE_USERINFO] as $type) {
            if ($json != null && isset($json[$type])) {
                foreach ($json[$type] as $claim => $properties) {
                    $res[] = $this->getClaimEntityByIdentifier(
                        $claim,
                        $type,
                        isset($properties) && isset($properties['essential']) ? $properties['essential'] : false
                    );
                }
            }
        }
        return $res;
    }
}
