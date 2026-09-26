<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Repositories;

use Analog\Analog;
use DI\Container;
use GaletteOAuth2\Entities\ClientEntity;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

/**
 * Client Repository
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class ClientRepository implements ClientRepositoryInterface
{
    private const string EXAMPLE_PASSWORD = 'abc123';

    private Container $container;
    private Config $config;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $this->container->get(Config::class);
    }

    /**
     * Check if a client exists in the configuration
     */
    public function clientExists(?string $client_id): bool
    {
        if (empty($client_id) || $client_id === 'global') {
            return false;
        }
        if ($this->config->get($client_id) === '') {
            return false;
        }
        if (count($this->getRedirectUris($client_id)) === 0) {
            Analog::log(
                sprintf(
                    'OAuth2: no redirect_uri configured for client "%1$s", add "redirect_uri" to its entry in config.yml',
                    $client_id
                ),
                Analog::ERROR
            );
            return false;
        }
        return true;
    }

    /**
     * Get redirect URIs allowed for a client
     *
     * @return string[]
     */
    private function getRedirectUris(string $client_id): array
    {
        $uris = $this->config->get("{$client_id}.redirect_uri");
        if (is_string($uris)) {
            $uris = [$uris];
        }
        if (!is_array($uris)) {
            return [];
        }

        return array_values(
            array_filter(
                $uris,
                fn($uri) => is_string($uri) && $uri !== ''
            )
        );
    }

    public function getClientEntity(string $client_id): ?ClientEntityInterface
    {
        if (!$this->clientExists($client_id)) {
            return null;
        }

        $client = new ClientEntity();
        $client->setIdentifier($this->config->get("{$client_id}.id", $client_id));
        $client->setName($client_id);
        $client->setRedirectUri($this->getRedirectUris($client_id));
        $client->setConfidential();

        return $client;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if (!preg_match('/galette_/', $clientIdentifier) || !$this->clientExists($clientIdentifier)) {
            Debug::log("validateClient({$clientIdentifier}) denied");

            return false;
        }

        $password = $this->config->get($clientIdentifier . '.password');
        if (!is_string($password) || $password === '') {
            Analog::log(
                sprintf(
                    'OAuth2: no password configured for client "%1$s", add "password" to its entry in config.yml',
                    $clientIdentifier
                ),
                Analog::ERROR
            );
            return false;
        }

        if ($password === self::EXAMPLE_PASSWORD) {
            Analog::log(
                sprintf(
                    'OAuth2: client "%1$s" still uses the example password, set a strong one in config.yml',
                    $clientIdentifier
                ),
                Analog::ERROR
            );
            return false;
        }

        return hash_equals($password, (string)$clientSecret);
    }
}
