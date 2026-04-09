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

namespace GaletteOAuth2\OIDC;

use DateTimeImmutable;
use DI\Container;
use Galette\Core\Db;
use Galette\Entity\Adherent;
use Galette\Entity\Social;

/**
 * OIDC Claim Extractor
 *
 * Extracts user claims from Galette member data according to OIDC standard scopes.
 * Used by both IdTokenBuilder and the /userinfo endpoint.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims
 */
final class ClaimExtractor
{
    /**
     * OIDC standard scopes and their associated claims
     */
    public const SCOPE_CLAIMS = [
        'profile' => [
            'name',
            'family_name',
            'given_name',
            'middle_name',
            'nickname',
            'preferred_username',
            'profile',
            'picture',
            'website',
            'gender',
            'birthdate',
            'zoneinfo',
            'locale',
            'updated_at',
        ],
        'email' => [
            'email',
            'email_verified',
        ],
        'address' => [
            'address',
        ],
        'phone' => [
            'phone_number',
            'phone_number_verified',
        ],
    ];

    private Container $container;

    /**
     * Constructor
     *
     * @param Container $container DI Container instance
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Extract claims for a user based on granted scopes
     *
     * @param int|string $userIdentifier The user's identifier (member ID)
     * @param string[]   $scopes         Array of granted scope identifiers
     * @param bool       $includeSubject Whether to include the 'sub' claim
     *
     * @return array<string, mixed> Array of claim name => value pairs
     */
    public function extract(int|string $userIdentifier, array $scopes, bool $includeSubject = true): array
    {
        $claims = [];

        // Always include subject if requested
        if ($includeSubject) {
            $claims['sub'] = (string)$userIdentifier;
        }

        // Load member data from database
        /** @var Db $zdb */
        $zdb = $this->container->get(Db::class);
        $member = new Adherent($zdb);

        if (!$member->load((int)$userIdentifier)) {
            return $claims;
        }

        // Extract claims for each scope
        if ($this->hasScope($scopes, ['profile', 'openid'])) {
            $claims = array_merge($claims, $this->extractProfileClaims($member));
        }

        if ($this->hasScope($scopes, ['email'])) {
            $claims = array_merge($claims, $this->extractEmailClaims($member));
        }

        if ($this->hasScope($scopes, ['address', 'member:localization', 'member:localization:precise'])) {
            $claims = array_merge(
                $claims,
                $this->extractAddressClaims($member, in_array('member:localization:precise', $scopes, true))
            );
        }

        if ($this->hasScope($scopes, ['phone', 'member:phones'])) {
            $claims = array_merge($claims, $this->extractPhoneClaims($member));
        }

        // Galette-specific scopes (for backward compatibility)
        if (in_array('member:groups', $scopes, true)) {
            $claims = array_merge($claims, $this->extractGroupsClaims($member));
        }

        if (in_array('member:due_date', $scopes, true)) {
            $claims['due_date'] = $member->due_date;
        }

        if (in_array('member:socials', $scopes, true)) {
            $claims = array_merge($claims, $this->extractSocialsClaims($member));
        }

        if (in_array('member:personal', $scopes, true)) {
            $claims = array_merge($claims, $this->extractPersonalClaims($member));
        }

        return $claims;
    }

    /**
     * Extract profile claims
     *
     * @param Adherent $member The member entity
     *
     * @return array<string, mixed>
     */
    private function extractProfileClaims(Adherent $member): array
    {
        $claims = [
            'name' => $member->sfullname,
            'family_name' => $member->name,
            'given_name' => $member->surname,
            'preferred_username' => $member->login,
            'locale' => $member->language,
        ];

        // Nickname (use login if not set)
        $claims['nickname'] = $member->nickname ?? $member->login;

        // Updated at timestamp
        if ($member->modification_date) {
            try {
                $claims['updated_at'] = (new DateTimeImmutable($member->modification_date))->getTimestamp();
            } catch (\Exception $e) {
                // Ignore invalid date
            }
        }

        // Gender mapping to OIDC values
        $gender = $member->gender;
        if ($gender !== null && $gender > 0) {
            $genderMap = [
                1 => 'female',
                2 => 'male',
            ];
            if (isset($genderMap[$gender])) {
                $claims['gender'] = $genderMap[$gender];
            }
        }

        // Birthdate in OIDC format (YYYY-MM-DD or YYYY)
        if ($member->birthdate) {
            $claims['birthdate'] = $member->birthdate;
        }

        return $claims;
    }

    /**
     * Extract email claims
     *
     * @param Adherent $member The member entity
     *
     * @return array<string, mixed>
     */
    private function extractEmailClaims(Adherent $member): array
    {
        $claims = [];

        if ($member->email) {
            $claims['email'] = $member->email;
            // Galette doesn't track email verification status
            // Consider active members as having verified emails
            $claims['email_verified'] = $member->isActive();
        }

        return $claims;
    }

    /**
     * Extract address claims
     *
     * @param Adherent $member  The member entity
     * @param bool     $precise Whether to include precise address (street)
     *
     * @return array<string, mixed>
     */
    private function extractAddressClaims(Adherent $member, bool $precise = false): array
    {
        $address = [];

        // Build formatted address
        $formatted = '';
        if ($precise && $member->getAddress()) {
            $formatted .= $member->getAddress();
            $address['street_address'] = $member->getAddress();
        }
        if ($member->getZipcode() || $member->getTown()) {
            if ($formatted) {
                $formatted .= "\n";
            }
            $formatted .= trim($member->getZipcode() . ' ' . $member->getTown());
        }
        if ($member->getRegion()) {
            if ($formatted) {
                $formatted .= "\n";
            }
            $formatted .= $member->getRegion();
        }
        if ($member->getCountry()) {
            if ($formatted) {
                $formatted .= "\n";
            }
            $formatted .= $member->getCountry();
        }

        if ($formatted) {
            $address['formatted'] = $formatted;
        }
        if ($member->getTown()) {
            $address['locality'] = $member->getTown();
        }
        if ($member->getRegion()) {
            $address['region'] = $member->getRegion();
        }
        if ($member->getZipcode()) {
            $address['postal_code'] = $member->getZipcode();
        }
        if ($member->getCountry()) {
            $address['country'] = $member->getCountry();
        }

        if (count($address) > 0) {
            return ['address' => (object)$address];
        }

        return [];
    }

    /**
     * Extract phone claims
     *
     * @param Adherent $member The member entity
     *
     * @return array<string, mixed>
     */
    private function extractPhoneClaims(Adherent $member): array
    {
        $claims = [];

        // Prefer mobile phone, then landline
        $phone = $member->gsm ?: $member->phone;
        if ($phone) {
            $claims['phone_number'] = $phone;
            // Galette doesn't track phone verification
            $claims['phone_number_verified'] = false;
        }

        return $claims;
    }

    /**
     * Extract groups claims (Galette-specific)
     *
     * @param Adherent $member The member entity
     *
     * @return array<string, mixed>
     */
    private function extractGroupsClaims(Adherent $member): array
    {
        $groups = array_map(
            fn($group) => $group->getName(),
            array_values($member->getGroups())
        );

        // Add status as a group
        $groups[] = $member->sstatus;

        // Add role-based groups
        if ($member->isAdmin()) {
            $groups[] = 'admin';
        }
        if ($member->isStaff()) {
            $groups[] = 'staff';
        }
        if (count($member->getManagedGroups()) > 0) {
            $groups[] = 'groupmanager';
        }
        if ($member->isUp2Date()) {
            $groups[] = 'uptodate';
        }

        return ['groups' => $groups];
    }

    /**
     * Extract socials claims (Galette-specific)
     *
     * @param Adherent $member The member entity
     *
     * @return array<string, mixed>
     */
    private function extractSocialsClaims(Adherent $member): array
    {
        $socials = [];
        foreach (Social::getListForMember($member->id) as $social) {
            $socials[$social->type] = $social->url;
        }

        if (count($socials) > 0) {
            return ['socials' => $socials];
        }

        return [];
    }

    /**
     * Extract personal claims (Galette-specific member:personal scope)
     *
     * @param Adherent $member The member entity
     *
     * @return array<string, mixed>
     */
    private function extractPersonalClaims(Adherent $member): array
    {
        $claims = [];

        if ($member->birthdate) {
            $claims['birthdate'] = $member->birthdate;
        }
        if ($member->birth_place) {
            $claims['birthplace'] = $member->birth_place;
        }
        if ($member->job) {
            $claims['job'] = $member->job;
        }
        if ($member->gender !== null) {
            $claims['gender'] = $member->sgender;
        }
        if ($member->gnupgid) {
            $claims['gpgid'] = $member->gnupgid;
        }

        return $claims;
    }

    /**
     * Check if any of the target scopes is present in the granted scopes
     *
     * @param string[] $grantedScopes Array of granted scope identifiers
     * @param string[] $targetScopes  Array of target scope identifiers to check
     *
     * @return bool True if at least one target scope is granted
     */
    private function hasScope(array $grantedScopes, array $targetScopes): bool
    {
        return count(array_intersect($grantedScopes, $targetScopes)) > 0;
    }
}
