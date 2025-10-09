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

namespace GaletteOauth2\Authorization\tests\units;

use Galette\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * UserHelper tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class UserHelper extends GaletteTestCase
{
    protected int $seed = 20230324120838;
    protected bool $load_plugins = true;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        global $session;

        parent::setUp();
        $this->session = $this->container->get('oauth_session');
        $session = $this->session;
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        //delete social networks
        $delete = $this->zdb->delete(\Galette\Entity\Social::TABLE);
        $this->zdb->execute($delete);

        //drop dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $this->zdb->execute($delete);

        $this->cleanMembers();
    }

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
        $member_one  = $this->getMemberOne();
        $this->getAdminMember($member_one); //set admin

        //test for default scope - legacy data mode
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member'],
            true
        );

        $expected_base = [
            'id' => $member_one->id,
            'sub' => $member_one->id,
            'identifier' => $member_one->id,
            'name' => $member_one->sfullname,
            'displayName' => $member_one->sname,
            'username' => 'r.durand',
            'userName' => 'r.durand',
            'email' => $member_one->email,
            'mail' => $member_one->email,
            'locale' => $member_one->language,
            'language' => $member_one->language,
            'status' => $member_one->status,
        ];

        $this->assertSame(
            $expected_base,
            $user_data
        );

        //test for default scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member']
        );

        $expected_base = [
            'id' => $member_one->id,
            'sub' => $member_one->id,
            'identifier' => $member_one->id,
            'name' => $member_one->sfullname,
            'displayName' => $member_one->sname,
            'username' => $member_one->login,
            'userName' => $member_one->login,
            'email' => $member_one->email,
            'mail' => $member_one->email,
            'locale' => $member_one->language,
            'language' => $member_one->language,
            'status' => $member_one->status,
        ];

        $this->assertSame(
            $expected_base,
            $user_data
        );

        //test personal scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:personal']
        );

        $this->assertSame(
            $expected_base + [
                'birthdate' => $user_data['birthdate'],
                'birthplace' => 'Gonzalez-sur-Meunier',
                'job' => 'Chef de fabrication',
                'gender' => 'Unspecified',
                'gpgid' => ''
            ],
            $user_data
        );

        //test phones scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:phones']
        );

        $this->assertSame(
            $expected_base + ['phone' => '0439153432'],
            $user_data
        );

        //test groups scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:groups']
        );

        $this->assertSame(
            $expected_base + [
                'groups' => [
                    'Non-member',
                    'admin'
                ]
            ],
            $user_data
        );

        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            'teamonly',
            ['member', 'member:groups']
        );

        $this->assertSame(
            $expected_base + [
                'groups' => [
                    'Non-member',
                    'admin'
                ]
            ],
            $user_data
        );

        //test due date scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:due_date']
        );

        $this->assertSame(
            $expected_base + ['due_date' => null],
            $user_data
        );

        //test localization scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:localization']
        );

        $address = new \stdClass();
        $address->locality = 'Martel';
        $address->region = 'Caribbean';
        $address->postal_code = '39 069';
        $address->country = 'Antarctique';
        $this->assertEquals(
            $expected_base + [
                'address' => $address
            ],
            $user_data
        );

        //test precise localization scope
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:localization:precise']
        );

        $address->formatted = "66, boulevard De Oliveira\r\n\r\n39 069 Martel\r\nCaribbean\r\nAntarctique";
        $address->street_address = "66, boulevard De Oliveira";
        $this->assertEquals(
            $expected_base + [
                'address' => $address
            ],
            $user_data
        );

        //test socials scope - no socials
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:socials']
        );

        $this->assertSame(
            $expected_base,
            $user_data
        );

        //add socials
        $social = new \Galette\Entity\Social($this->zdb);
        $this->assertTrue(
            $social
                ->setType(\Galette\Entity\Social::MASTODON)
                ->setUrl('mastodon URL')
                ->setLinkedMember($member_one->id)
                ->store()
        );

        //get again, with socials
        $user_data = \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            ['member', 'member:socials']
        );

        $this->assertSame(
            $expected_base + [
                'socials' => [
                    \Galette\Entity\Social::MASTODON => 'mastodon URL'
                ]
            ],
            $user_data
        );


        //no scope => error
        $this->expectExceptionMessage('Default scope (member) has not been authorized.');
        \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $member_one->id,
            '',
            []
        );
    }

    /**
     * Test requireAdmin
     *
     * @return void
     */
    public function testRequireAdmin()
    {
        global $container;

        $this->initStatus();
        $adh1  = $this->getMemberOne();

        $this->expectExceptionMessage("Sorry, you can't login because your are not a team member.");
        \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $adh1->id,
            'teamonly',
            ['member']
        );
    }

    /**
     * Data provider for not found members
     *
     * @return array
     */
    public static function memberNotFoundProvider(): array
    {
        return [
            [0],
            [42]
        ];
    }

    /**
     * Test with a not found member
     *
     * @return void
     */
    #[DataProvider('memberNotFoundProvider')]
    public function testMemberNotFound($member_id)
    {
        global $container;

        $exception_thrown = false;
        try {
            \GaletteOAuth2\Authorization\UserHelper::getUserData(
                $container,
                $member_id,
                'teamonly',
                ['member']
            );
        } catch (\Exception $e) {
            $exception_thrown = true;
            $this->assertEquals("User not found.", $e->getMessage());
        }
        $this->assertTrue($exception_thrown);
        $this->expectLogEntry(\Analog::ERROR, 'No member #' . $member_id);
    }

    /**
     * Test with an inactive member
     *
     * @return void
     */
    public function testMemberInactive()
    {
        global $container;

        $this->logSuperAdmin();
        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne();
        $data['activite_adh'] = false;
        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);
        $this->login->logout();

        $this->expectExceptionMessage("Sorry, you can't login because you are not an active member.");
        \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $adh->id,
            'teamonly',
            ['member']
        );
    }

    /**
     * Test with a member without an email address
     *
     * @return void
     */
    public function testMemberNoMail()
    {
        global $container;

        $this->logSuperAdmin();
        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne();
        $data['email_adh'] = '';
        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);
        $this->login->logout();

        $this->expectExceptionMessage("Sorry, you can't login. Please, add an email address to your account.");
        \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $adh->id,
            'teamonly',
            ['member']
        );
    }

    /**
     * Test with a member that is not up-to-date
     *
     * @return void
     */
    public function testMemberNotUp2Date(): void
    {
        global $container;

        $this->logSuperAdmin();
        $adh = new \Galette\Entity\Adherent($this->zdb);
        $adh->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );

        $data = $this->dataAdherentOne();
        $check = $adh->check($data, [], []);
        if (is_array($check)) {
            var_dump($check);
        }
        $this->assertTrue($check);

        $store = $adh->store();
        $this->assertTrue($store);
        $this->login->logout();

        $this->expectExceptionMessage("Sorry, you can't login because your are not an up-to-date member.");
        \GaletteOAuth2\Authorization\UserHelper::getUserData(
            $container,
            $adh->id,
            'uptodate',
            ['member']
        );
    }

    /**
     * Test getAuthorization
     *
     * @return void
     */
    public function testGetAuthorizations(): void
    {
        $config = new \GaletteOAuth2\Tools\Config(OAUTH2_CONFIGPATH . '/config.yml');

        //always defaults to 'teamonly'
        $this->assertSame(
            'teamonly',
            \GaletteOAuth2\Authorization\UserHelper::getAuthorization($config, 'any')
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            'Invalid authorization configuration for client any'
        );

        $client_id = 'galette_test';
        $config->set($client_id . '.authorize', 'unknown');
        $this->assertSame(
            'teamonly',
            \GaletteOAuth2\Authorization\UserHelper::getAuthorization($config, 'galette_test')
        );
        $this->expectLogEntry(
            \Analog::ERROR,
            'Invalid authorization configuration for client galette_test'
        );

        //correct value will be retrieved
        $this->assertSame(
            'teamonly',
            \GaletteOAuth2\Authorization\UserHelper::getAuthorization($config, 'galette_cli')
        );
        $this->assertSame(
            'uptodate',
            \GaletteOAuth2\Authorization\UserHelper::getAuthorization($config, 'galette_flarum')
        );
    }

    /**
     * Test getAuthorization
     *
     * @return void
     */
    public function testMergeScopes(): void
    {
        $config = new \GaletteOAuth2\Tools\Config(OAUTH2_CONFIGPATH . '/config.yml');

        $this->assertSame(
            [],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes($config, 'any', [])
        );

        $this->assertSame(
            ['member'],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes($config, 'any', [], true)
        );

        $this->assertSame(
            [
                'member',
                'member:localization',
                'member:phones',
                'member:groups',
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes($config, 'galette_nc', [])
        );

        $this->assertSame(
            [
                'member:due_date',
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes($config, 'galette_cli', [])
        );

        $this->assertSame(
            [
                'member',
                'member:due_date',
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes($config, 'galette_cli', [], true)
        );

        $this->assertSame(
            [
                'member',
                'member:phones',
                'member:localization:precise',
                'member:due_date',
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes(
                $config,
                'galette_cli',
                [
                    'member:phones',
                    'member:localization:precise'
                ],
                true
            )
        );

        $this->assertSame(
            ['member'],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes($config, 'any', '', true)
        );


        $this->assertSame(
            [
                'member:phones',
                'member:localization:precise',
                'member:due_date',
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes(
                $config,
                'galette_cli',
                'member:phones member:localization:precise'
            )
        );

        $this->assertSame(
            [
                'member:phones',
                'member:localization:precise',
                'member:due_date',
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes(
                $config,
                'galette_cli',
                'member:phones;member:localization:precise'
            )
        );

        $client_id = 'galette_test';
        $config->set($client_id . '.scopes', 'member:phones;member:localization:precise');
        $this->assertSame(
            [
                'member:phones',
                'member:localization:precise'
            ],
            \GaletteOAuth2\Authorization\UserHelper::mergeScopes(
                $config,
                'galette_test',
                []
            )
        );
    }
}
