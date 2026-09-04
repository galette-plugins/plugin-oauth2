<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Repositories;

use DI\Container;
use GaletteOAuth2\Entities\ClientEntity;
use GaletteOAuth2\Tools\Config;
use GaletteOAuth2\Tools\Debug;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use RKA\Session;

/**
 * Client Repository
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class ClientRepository implements ClientRepositoryInterface
{
    private Container $container;
    private Config $config;
    private Session $session;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $this->container->get(Config::class);
        $this->session = $this->container->get('oauth_session');
    }

    /**
     * Check if a client exists in the configuration
     */
    public function clientExists(?string $client_id): bool
    {
        if (empty($client_id)) {
            return false;
        }
        return $this->config->get($client_id) !== '';
    }

    public function getClientEntity(string $client_id): ClientEntityInterface
    {
        $client = new ClientEntity();
        $client->setIdentifier($this->config->get("{$client_id}.id", $client_id));
        $client->setName($client_id);
        if (isset($this->session->$client_id)) {
            $redirect_uri = $this->session->$client_id->redirect_uri;
        } else {
            $filename = OAUTH2_PREFIX . '_' . $client_id . '.redirect_uri.txt';
            $redirect_uri = file_get_contents(GALETTE_CACHE_DIR . '/' . $filename);
        }
        $cid = $this->config->get("{$client_id}.redirect_uri");
        /*$redirect_uri = $this->config->get("{$clientIdentifier}.redirect_uri");
        if (empty($redirect_uri)) {
            $filename = OAUTH2_PREFIX . '_' . $clientIdentifier . '.redirect_uri.txt';
            $redirect_uri = file_get_contents(GALETTE_CACHE_DIR . '/' . $filename);
        }*/
        $client->setRedirectUri($redirect_uri);
        $client->setConfidential();

        Debug::log('getClientEntity() ' . Debug::printVar($client));

        return $client;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if (!preg_match('/galette_/', $clientIdentifier)) {
            Debug::log("validateClient({$clientIdentifier}) denied");

            return false;
        }

        $password = $this->config->get($clientIdentifier . '.password');
        if (!$password) {
            $password = $this->config->get('global.password');
        }
        $pwd = password_hash($password, PASSWORD_BCRYPT);

        if (password_verify($clientSecret, $pwd) === false) {
            return false;
        }

        return true;
    }
}
