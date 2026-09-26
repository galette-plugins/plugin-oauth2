<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Repositories\tests\units;

use Analog\Analog;
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
            'reserved global entry' => ['global'],
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

    /**
     * Test the redirect URI always comes from the configuration
     *
     * @return void
     */
    public function testGetClientEntityUsesConfiguredRedirectUri(): void
    {
        //values that used to be trusted: session and cache file
        $this->session->galette_flarum = new \stdClass();
        $this->session->galette_flarum->redirect_uri = 'https://attacker.example/cb';
        $cache_file = GALETTE_CACHE_DIR . '/' . OAUTH2_PREFIX . '_galette_nc.redirect_uri.txt';
        file_put_contents($cache_file, 'https://attacker.example/cb');

        try {
            $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);

            $client = $clientRepository->getClientEntity('galette_flarum');
            $this->assertNotNull($client);
            $this->assertSame(['http://flarum.localhost/auth/passport'], $client->getRedirectUri());

            $client = $clientRepository->getClientEntity('galette_nc');
            $this->assertNotNull($client);
            $this->assertSame(
                ['http://localhost/nextcloud/apps/sociallogin/custom_oauth2/galette'],
                $client->getRedirectUri()
            );

            $client = $clientRepository->getClientEntity('galette_cli');
            $this->assertNotNull($client);
            $this->assertCount(4, $client->getRedirectUri());
        } finally {
            unset($this->session->galette_flarum);
            unlink($cache_file);
        }
    }

    /**
     * Test a client without configured redirect URI is refused
     *
     * @return void
     */
    public function testClientWithoutRedirectUriIsRefused(): void
    {
        $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);

        $this->assertFalse($clientRepository->clientExists('galette_noredirect'));
        $this->expectLogEntry(
            Analog::ERROR,
            'OAuth2: no redirect_uri configured for client "galette_noredirect"'
        );

        $this->assertNull($clientRepository->getClientEntity('galette_noredirect'));
        $this->expectLogEntry(
            Analog::ERROR,
            'OAuth2: no redirect_uri configured for client "galette_noredirect"'
        );
    }

    /**
     * Test client secret validation
     *
     * @return void
     */
    public function testValidateClient(): void
    {
        $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);

        $this->assertTrue($clientRepository->validateClient('galette_cli', 'cli-secret-for-tests', 'authorization_code'));
        $this->assertFalse($clientRepository->validateClient('galette_cli', 'wrong-secret', 'authorization_code'));
        $this->assertFalse($clientRepository->validateClient('galette_cli', '', 'authorization_code'));
        $this->assertFalse($clientRepository->validateClient('galette_cli', null, 'authorization_code'));
        //secret of another client
        $this->assertFalse($clientRepository->validateClient('galette_cli', 'flarum-secret-for-tests', 'authorization_code'));
        $this->assertFalse($clientRepository->validateClient('unknown_client', 'cli-secret-for-tests', 'authorization_code'));
    }

    /**
     * Test client without its own password is refused, even with the global one
     *
     * @return void
     */
    public function testValidateClientWithoutPassword(): void
    {
        $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);

        $this->assertFalse($clientRepository->validateClient('galette_nopassword', 'abc123', 'authorization_code'));
        $this->expectLogEntry(
            Analog::ERROR,
            'OAuth2: no password configured for client "galette_nopassword"'
        );
    }

    /**
     * Test client with the default example password is refused
     *
     * @return void
     */
    public function testValidateClientWithDefaultPassword(): void
    {
        $clientRepository = new \GaletteOAuth2\Repositories\ClientRepository($this->container);

        $this->assertFalse($clientRepository->validateClient('galette_defaultpassword', 'abc123', 'authorization_code'));
        $this->expectLogEntry(
            Analog::ERROR,
            'OAuth2: client "galette_defaultpassword" still uses the example password'
        );
    }
}
