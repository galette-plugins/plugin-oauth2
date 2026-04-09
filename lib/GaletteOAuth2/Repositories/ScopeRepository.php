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

namespace GaletteOAuth2\Repositories;

use Analog\Analog;
use GaletteOAuth2\Entities\ScopeEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

use function array_key_exists;

/**
 * Scope repository
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * Get all known scopes including OIDC standard scopes
     *
     * OIDC scopes (openid, profile, email, address, phone) are mapped to
     * Galette's internal scopes when claims are extracted.
     *
     * @return array<string, array<string, string>> Array of scope definitions
     */
    public static function knownScopes(): array
    {
        return [
            // OpenID Connect standard scopes
            'openid' => [
                'description' => _T('OpenID Connect authentication (required for OIDC)', 'oauth2'),
            ],
            'profile' => [
                'description' => _T('Access to your profile information: name, nickname, locale, gender, birthdate', 'oauth2'),
            ],
            'email' => [
                'description' => _T('Access to your email address', 'oauth2'),
            ],
            'address' => [
                'description' => _T('Access to your postal address', 'oauth2'),
            ],
            'phone' => [
                'description' => _T('Access to your phone number', 'oauth2'),
            ],
            // Galette-specific scopes (backward compatible)
            'member' => [
                'description' => _T('Access to your member basic information: name, login, email, language, company name)', 'oauth2'),
            ],
            'member:personal' => [
                'description' => _T('Access to more precise personal data: birth date, job, gender, birth place, GnuPG ID', 'oauth2'),
            ],
            'member:localization' => [
                'description' => _T('Access to your localization data: zipcode, town, region, country', 'oauth2'),
            ],
            'member:localization:precise' => [
                'description' => _T('Access to your precise localisation data: full address, coordinates (from maps plugin)', 'oauth2'),
            ],
            'member:phones' => [
                'description' => _T('Access to your phone numbers', 'oauth2'),
            ],
            'member:socials' => [
                'description' => _T('Access to your social networks data', 'oauth2'),
            ],
            'member:groups' => [
                'description' => _T('Access to the groups you belong to', 'oauth2'),
            ],
            'member:due_date' => [
                'description' => _T('Access to your due date', 'oauth2'),
            ]
        ];
    }

    /**
     * Get OIDC-only scopes
     *
     * @return string[] Array of OIDC scope identifiers
     */
    public static function oidcScopes(): array
    {
        return ['openid', 'profile', 'email', 'address', 'phone'];
    }

    /**
     * Check if a scope is an OIDC scope
     *
     * @param string $scope The scope identifier
     *
     * @return bool True if the scope is an OIDC standard scope
     */
    public static function isOidcScope(string $scope): bool
    {
        return in_array($scope, self::oidcScopes(), true);
    }

    public function getScopeEntityByIdentifier($scopeIdentifier)
    {
        $scopes = static::knownScopes();
        if (array_key_exists($scopeIdentifier, $scopes) === false) {
            Analog::log(
                'Unknown scope identifier: ' . $scopeIdentifier,
                Analog::ERROR
            );
            return null;
        }

        $scope = new ScopeEntity();
        $scope->setIdentifier($scopeIdentifier);

        return $scope;
    }

    /**
     * {@inheritDoc}
     */
    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) {
        /*TODO : ?
         [JC] 2024-06-12: does not seems required; or maybe I misunderstood something. Anyway; that works without it.
                // Example of programmatically modifying the final scope of the access token
                if ((int) $userIdentifier === 1) {
                    $scope = new ScopeEntity();
                    $scope->setIdentifier('email');
                    $scopes[] = $scope;
                }
         */
        return $scopes;
    }
}
