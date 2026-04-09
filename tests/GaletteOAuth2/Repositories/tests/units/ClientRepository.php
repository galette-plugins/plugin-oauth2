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

namespace GaletteOAuth2\Repositories\tests\units;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ClientRepository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ClientRepository extends GaletteTestCase
{
    protected int $seed = 20260413100000;
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
     * Data provider for valid client IDs
     *
     * @return array<string, array<string>>
     */
    public static function validClientIdsProvider(): array
    {
        return [
            'galette_flarum' => ['galette_flarum'],
            'galette_nc' => ['galette_nc'],
            'galette_cli' => ['galette_cli'],
        ];
    }

    /**
     * Data provider for invalid client IDs
     *
     * @return array<string, array<string|null>>
     */
    public static function invalidClientIdsProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'unknown client' => ['unknown_client'],
            'galette_unknown' => ['galette_unknown'],
            'random string' => ['some_random_string'],
        ];
    }

    /**
     * Test clientExists with valid client IDs
     *
     * @param string $client_id Client ID to test
     * @return void
     */
    #[DataProvider('validClientIdsProvider')]
    public function testClientExistsWithValidClients(string $client_id): void
    {
        $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);
        $this->assertTrue(
            $clientRepository->clientExists($client_id),
            "Client '$client_id' should exist in configuration"
        );
    }

    /**
     * Test clientExists with invalid client IDs
     *
     * @param string|null $client_id Client ID to test
     * @return void
     */
    #[DataProvider('invalidClientIdsProvider')]
    public function testClientExistsWithInvalidClients(?string $client_id): void
    {
        $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);
        $this->assertFalse(
            $clientRepository->clientExists($client_id),
            "Client '$client_id' should not exist in configuration"
        );
    }
}
