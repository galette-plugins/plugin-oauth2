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

namespace GaletteOauth2\Authorization\tests\units;

use Galette\GaletteTestCase;

/**
 * UserHelper tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UserHelper extends GaletteTestCase
{
    protected int $seed = 20230324120838;

    /**
     * Test stripAccents
     *
     * @return void
     */
    public function testStripAccents(): void
    {
        /** @var \Galette\Core\Plugins */
        global $plugins;

        $str = "çéè-ßØ";
        $this->assertSame('cee-sso', \GaletteOAuth2\Authorization\UserHelper::stripAccents($str));
    }

    /**
     * Test getUserData
     *
     * @return void
     */
    public function testGetUserData(): void
    {
        global $container;

        $this->initStatus();
        $adh1  = $this->getMemberOne();
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $adh1->id,
            []
        );

        $this->assertSame(
            [
                'id' => $adh1->id,
                'identifier' => $adh1->id,
                'displayName' => $adh1->sname,
                'username' => 'r.durand',
                'userName' => 'r.durand',
                'name' => 'r.durand',
                'email' => $adh1->email,
                'mail' => $adh1->email,
                'language' => $adh1->language,
                'country' => $adh1->country,
                'zip' => $adh1->zipcode,
                'city' => $adh1->town,
                'phone' => $adh1->phone,
                'status' => $adh1->status,
                'groups' => 'non-member'
            ],
            $user_data
        );
    }
}
