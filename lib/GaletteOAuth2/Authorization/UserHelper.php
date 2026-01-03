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

namespace GaletteOAuth2\Authorization;

use Analog\Analog;
use DI\Container;
use Galette\Core\Db;
use Galette\Core\History;
use Galette\Core\Login;
use Galette\Core\Preferences;
use Galette\Entity\Adherent;
use Galette\Entity\Social;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;
use Slim\Flash\Messages;

/**
 * Helpers for user authorization
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class UserHelper
{
    public const AUTH_TEAMONLY = 'teamonly';
    public const AUTH_UPTODATE = 'uptodate';
    public const AUTH_ACTIVE = 'active';

    public static function login(Container $container, $nick, $password): int|false
    {
        $preferences = $container->get(Preferences::class);
        /** @var Login $login */
        $login = $container->get(Login::class);
        $history = $container->get(History::class);
        $session = $container->get('oauth_session');
        $flash = $container->get(Messages::class);

        if (trim($nick) === '' || trim($password) === '') {
            return false;
        }

        if ($nick === $preferences->pref_admin_login) {
            $pw_superadmin = password_verify(
                $password,
                $preferences->pref_admin_pass,
            );

            if (!$pw_superadmin) {
                $pw_superadmin = (
                    md5($password) === $preferences->pref_admin_pass
                );
            }

            if ($pw_superadmin) {
                Analog::log(
                    'OAuth login attempt from superadmin account',
                    Analog::WARNING
                );
                $flash->addMessage(
                    'error_detected',
                    _T('Cannot OAuth login from superadmin account!', 'oauth2')
                );
                return false;
            }
        } else {
            $login->logIn($nick, $password);
        }

        if ($login->isLogged()) {
            $session->login = $login;
            $history->add(_T('Login'));

            return $login->id;
        }
        $history->add(_T('Authentication failed'), $nick);

        return false;
    }

    public static function logout(Container $container): void
    {
        /** @var Login $login */
        $login = $container->get(Login::class);
        $history = $container->get(History::class);
        $session = $container->get('oauth_session');

        $login->logout();
        $session->login = $login;
        $history->add(_T('Logout'));
    }

    /**
     * Get user data
     *
     * @param Container       $container Container instance
     * @param int             $id        User ID
     * @param string          $acl       Requested authorization
     * @param string[]|string $scopes    Scopes
     * @param bool            $legacy    Legacy mode for data
     *
     * @return array<string, mixed>
     * @throws UserAuthorizationException
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \Throwable
     */
    public static function getUserData(Container $container, int $id, string $acl, array|string $scopes, bool $legacy = false): array
    {
        /** @var Db $zdb */
        $zdb = $container->get(Db::class);

        $member = new Adherent($zdb);
        if (!$member->load($id)) {
            throw new UserAuthorizationException(_T('User not found.', 'oauth2'));
        }

        //check active member? Covers $acl === self::AUTH_ACTIVE
        if (!$member->isActive()) {
            throw new UserAuthorizationException(_T("Sorry, you can't login because you are not an active member.", "oauth2"));
        }

        //check email
        if (!filter_var($member->email, FILTER_VALIDATE_EMAIL)) {
            throw new UserAuthorizationException(_T("Sorry, you can't login. Please, add an email address to your account.", 'oauth2'));
        }

        if ($acl === self::AUTH_TEAMONLY) {
            if (!$member->isAdmin() && !$member->isStaff() && !$member->isGroupManager(null)) {
                throw new UserAuthorizationException(
                    _T("Sorry, you can't login because your are not a team member.", 'oauth2')
                );
            }
        }

        if ($acl === self::AUTH_UPTODATE) {
            if (!$member->isUp2Date()) {
                throw new UserAuthorizationException(
                    _T("Sorry, you can't login because your are not an up-to-date member.", 'oauth2')
                );
            }
        }

        $default_scope = array_search('member', $scopes, true);
        if ($default_scope !== false) {
            unset($scopes[$default_scope]);
        } else {
            throw new UserAuthorizationException(
                sprintf(
                    _T('Default scope (%s) has not been authorized.', 'oauth2'),
                    'member'
                )
            );
        }

        $login = $member->login;

        if ($legacy === true) {
            //FIXME: I really doubt reworking names is a good idea outside a specific usage
            $nameExplode = preg_split('/[\\s,-]+/', $member->name);
            if (count($nameExplode) > 0) {
                $nameFPart = $nameExplode[0];
                //too short?
                if (mb_strlen($nameFPart) < 4 && count($nameExplode) > 1) {
                    $nameFPart .= $nameExplode[1];
                }
            } else {
                $nameFPart = $member->name;
            }

            //Normalized format s.name (example mail usage : s.name@xxxx.xx )
            //FIXME: why don't use email directly?
            $login = sprintf(
                '%s.%s',
                mb_substr(self::stripAccents($member->surname), 0, 1),
                self::stripAccents($nameFPart)
            );
        }

        //FIXME: be compliant with OpenID-Connect (see https://openid.net/specs/openid-connect-core-1_0.html#StandardClaims)
        $oauth_data = [
            'id' => $member->id,
            'sub' => $member->id, //drupal / OpenID-Connect
            'identifier' => $member->id, //nextcloud
            'name' => $member->sfullname, //OpenID-Connect
            'displayName' => $member->sname,
            'username' => $login,
            'userName' => $login,
            'email' => $member->email,
            'mail' => $member->email,
            'locale' => $member->language, //OpenID-Connect
            'language' => $member->language,
            'status' => $member->status
        ];

        //member:personal
        if (in_array('member:personal', $scopes)) {
            $oauth_data['birthdate'] = $member->birthdate;
            $oauth_data['birthplace'] = $member->birth_place;
            $oauth_data['job'] = $member->job;
            $oauth_data['gender'] = $member->sgender;
            $oauth_data['gpgid'] = $member->gnupgid;
        }

        //member:localization
        if (in_array('member:localization', $scopes) || in_array('member:localization:precise', $scopes)) {
            $address = new \stdClass();

            if (in_array('member:localization:precise', $scopes)) {
                $formatted = $member->getAddress();
                if ($member->getZipcode() || $member->getTown()) {
                    $formatted .= "\r\n\r\n";
                    if ($member->getZipcode()) {
                        $formatted .= $member->getZipcode() . ' ';
                    }
                    if ($member->getTown()) {
                        $formatted .= $member->getTown();
                    }
                }
                if ($member->getRegion()) {
                    $formatted .= "\r\n" . $member->getRegion();
                }
                if ($member->getCountry()) {
                    $formatted .= "\r\n" . $member->getCountry();
                }

                $address->formatted = $formatted;
                $address->street_address = $member->getAddress();
            }
            $address->locality = $member->getTown();
            $address->region = $member->getRegion();
            $address->postal_code = $member->getZipcode();
            $address->country = $member->getCountry();
            $oauth_data['address'] = $address;
        }

        //member:phones
        if (in_array('member:phones', $scopes)) {
            if ($member->phone) {
                $oauth_data['phone'] = $member->phone;
            }
            if ($member->gsm) {
                if ($member->phone) {
                    $oauth_data['mobile_phone'] = $member->gsm;
                } else {
                    $oauth_data['phone'] = $member->gsm;
                }
            }
        }

        //member:socials
        if (in_array('member:socials', $scopes)) {
            $socials = Social::getListForMember($member->id);
            foreach ($socials as $social) {
                $oauth_data['socials'][$social->type] = $social->url;
            }
        }

        //member:groups
        if (in_array('member:groups', $scopes)) {
            $oauth_data['groups'] = self::getUserGroups($member, $legacy);
        }

        //member:due_date
        if (in_array('member:due_date', $scopes)) {
            $oauth_data['due_date'] = $member->due_date;
        }

        return $oauth_data;
    }

    /**
     * Comma separated groups names
     *
     * @param Adherent $member Member
     * @param bool     $legacy Legacy mode for data
     *
     * @return array
     */
    protected static function getUserGroups(Adherent $member, bool $legacy = false): array
    {
        $groups = array_map(
            function ($group) {
                return $group->getName();
            },
            array_values($member->getGroups())
        );

        $groups[] = $member->sstatus;

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

        if ($legacy === true) {
            //Add externals groups (free text in info_adh)
            //Example #GROUPS:compta;accueil#
            if (preg_match('/#GROUPS:([^#]*([^#]*))#/mui', $member->others_infos_admin, $matches, PREG_OFFSET_CAPTURE)) {
                $g = $matches[1][0];
                $groups = array_merge($groups, explode(';', $g));
            }

            //Reformat group with strtolower, remove whites & slashs
            foreach ($groups as &$group) {
                $group = trim($group);
                $group = str_replace([' ', '/', '(', ')'], ['_', '', '', ''], $group);
                $group = str_replace('__', '_', $group);
                $group = self::stripAccents($group);
            }
        }

        return $groups;
    }

    /**
     * Get required authorizations
     *
     * @param Config $config Config instance
     * @param string $client_id
     *
     * @return string
     */
    public static function getAuthorization(Config $config, string $client_id): string
    {
        $acl = self::AUTH_TEAMONLY;
        $conf_acls = $config->get($client_id . '.authorize');

        if (!in_array($conf_acls, self::getKnownAuthorizations())) {
            Analog::log(
                sprintf(
                    'Invalid authorization "%1$s" for client "%2$s"',
                    $conf_acls,
                    $client_id
                ),
                Analog::ERROR
            );
        } else {
            $acl = $conf_acls;
        }

        return $acl;
    }

    /**
     * Merge requested and configured scopes
     *
     * @param Config $config Config instance
     *
     * @param string       $client_id        Client app identifier
     * @param array|string $requested_scopes Requested scopes from query string
     *
     * @return array
     */
    public static function mergeScopes(
        ?Config $config,
        string $client_id,
        array|string $requested_scopes,
        bool $with_default = false
    ): array {
        $scopes = [];

        //add default scope if requested
        if ($with_default === true) {
            $scopes[] = 'member';
        }

        //handle requested scopes
        if (!is_array($requested_scopes)) {
            $requested_scopes = str_replace([';', ','], ' ', $requested_scopes);
            $requested_scopes = preg_split('/ /', $requested_scopes, -1, PREG_SPLIT_NO_EMPTY);
        }
        $scopes = array_merge($scopes, $requested_scopes);

        if ($config !== null) {
            //handle config scopes
            $conf_scopes = $config->get($client_id . '.scopes');
            if ($conf_scopes) {
                if (!is_array($conf_scopes)) {
                    $conf_scopes = str_replace([';', ','], ' ', $conf_scopes);
                    $conf_scopes = explode(' ', $conf_scopes);
                }
                $scopes = array_merge($scopes, $conf_scopes);
            }
        }

        $scopes = array_unique($scopes);
        $scopes = array_map('strtolower', $scopes);
        Debug::log('Scopes: ' . implode(' ', $scopes));

        return $scopes;
    }

    // Nextcloud data:
    // \DBG = Hybridauth\User\Profile::__set_state(array(
    // 'identifier' => 3992, 'webSiteURL' => NULL, 'profileURL' => NULL,
    // 'photoURL' => NULL,
    // 'displayName' => ' TEST', 'description' => NULL, 'firstName' => NULL, 'lastName' => NULL, 'gender' => NULL,
    // 'language' => NULL,
    // 'age' => NULL, 'birthDay' => NULL, 'birthMonth' => NULL, 'birthYear' => NULL,
    // 'email' => 'uuuu@ik.me', 'emailVerified' => NULL, 'phone' => NULL,
    // 'address' => NULL, 'country' => NULL, 'region' => NULL, 'city' => NULL, 'zip' => NULL

    /**
     * Strips accented characters, lower string
     *
     * @param string $str
     * @return string
     */
    public static function stripAccents(string $str): string
    {
        return mb_strtolower(
            transliterator_transliterate(
                "Any-Latin; Latin-ASCII; [^a-zA-Z0-9\.\ -_] Remove;",
                $str
            )
        );
    }

    /**
     * Know authorizations
     *
     * @return string[]
     */
    public static function getKnownAuthorizations(): array
    {
        return [
            static::AUTH_TEAMONLY,
            static::AUTH_UPTODATE,
            static::AUTH_ACTIVE
        ];
    }
}
