<?php

/**
 * Copyright © 2021-2024 The Galette Team
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

use DI\Container;
use Galette\Core\Db;
use Galette\Core\Login;
use Galette\Entity\Adherent;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;

/**
 * Helpers for user authorization
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class UserHelper
{
    public static function login(Container $container, $nick, $password): int|false
    {
        $preferences = $container->get('preferences');
        /** @var Login $login */
        $login = $container->get('login');
        $history = $container->get('history');
        $session = $container->get('session');
        $flash = $container->get('flash');

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
        $login = $container->get('login');
        $history = $container->get('history');
        $session = $container->get('session');

        $login->logout();
        $session->login = $login;
        $history->add(_T('Logout'));
    }

    public static function getUserData(Container $container, int $id, array $options)
    {
        /** @var Db $zdb */
        $zdb = $container->get('zdb');

        $member = new Adherent($zdb);
        $member->load($id);

        $nameExplode = preg_split('/[\\s,-]+/', $member->name);
        //$surnameExplode = preg_split('/[\\s,-]+/', $member->surname);

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
        $norm_login =
        mb_substr(self::stripAccents($member->surname), 0, 1) .
        '.' .
        self::stripAccents($nameFPart);

        //FIXME: is that really useful? From a Galette PoV; this does not means much.
        $etat_adhesion = ($member->isActive() && $member->isUp2Date()) || $member->isAdmin();

        if (!$member->isActive()) {
            throw new UserAuthorizationException(_T('You are not an active member.', 'oauth2'));
        }

        //for options=
        //teamonly
        if (in_array('teamonly', $options, true)) {
            if (!$member->isAdmin() && !$member->isStaff() && !$member->isGroupManager(null)) {
                throw new UserAuthorizationException(
                    _T("Sorry, you can't login because your are not a team member.", 'oauth2')
                );
            }
        }
        //uptodate
        if (in_array('uptodate', $options, true)) {
            if (!$member->isUp2Date()) {
                throw new UserAuthorizationException(
                    _T("Sorry, you can't login because your are not an up-to-date member.", 'oauth2')
                );
            }
        }

        //Groups list for Nextcloud
        $groups = [$member->sstatus]; //first group is the member status

        if ($member->isAdmin()) {
            $groups[] = 'admin';
        }

        if ($member->isStaff()) {
            $groups[] = 'staff';
        }

        if ($member->isGroupManager(null)) {
            $groups[] = 'groupmanager';
        }

        if ($member->isUp2Date()) {
            $groups[] = 'uptodate';
        }

        //FIXME: add groups from groups table? Or another way? info_adh does not seems a good way for everyone
        //FIXME: For example, data is replaced on duplication, thus oauth groups configuration would be lost
        //Add externals groups (free text in info_adh)
        //Example #GROUPS:compta;accueil#
        if (preg_match('/#GROUPS:([^#]*([^#]*))#/mui', $member->others_infos_admin, $matches, PREG_OFFSET_CAPTURE)) {
            $g = $matches[1][0];
            Debug::log("Groups added {$g}");
            $groups = array_merge($groups, explode(';', $g));
        }

        //Reformat group with strtolower, remove whites & slashs
        foreach ($groups as &$group) {
            $group = trim($group);
            $group = str_replace([' ', '/', '(', ')'], ['_', '', '', ''], $group);
            $group = str_replace('__', '_', $group);
            $group = self::stripAccents($group);
        }
        $groups = implode(',', $groups);

        $phone = '';
        if ($member->phone) {
            $phone = $member->phone;
        }
        if ($member->gsm) {
            if ($phone) {
                $phone .= '/';
            }
            $phone .= $member->gsm;
        }

        return [
            'id' => $member->id,
            'identifier' => $member->id, //nextcloud
            'displayName' => $member->sname,
            'username' => $norm_login, //FIXME: $member->login,
            'userName' => $norm_login, //FIXME: $member->login,
            'name' => $norm_login, //FIXME: $member->sname,
            'email' => $member->email,
            'mail' => $member->email,
            'language' => $member->language,

            'country' => $member->country,
            'zip' => $member->zipcode,
            'city' => $member->town,
            'phone' => $phone,

            'status' => $member->status,
            'state' => $etat_adhesion ? 'true' : 'false',
            'groups' => $groups, //nextcloud : set fields Groups claim (optional) = groups
        ];
    }

    //merge oauth_scopes with user config.yml client_id.options
    public static function mergeOptions(Config $config, $client_id, array $oauth_scopes)
    {
        $options = $oauth_scopes;
        $o = $config->get("{$client_id}.options");

        if ($o) {
            $o = str_replace(';', ' ', $o);
            $o = explode(' ', $o);
            $options = array_merge($o, $options);
        }
        $options = array_unique($options);
        Debug::log('Options: ' . implode(';', $options));

        return $options;
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
}
