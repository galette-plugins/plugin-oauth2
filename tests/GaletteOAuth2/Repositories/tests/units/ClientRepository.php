<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
