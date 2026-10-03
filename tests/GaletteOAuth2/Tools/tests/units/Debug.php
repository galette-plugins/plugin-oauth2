<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Tools\tests\units;

use PHPUnit\Framework\TestCase;

/**
 * Debug tools tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Debug extends TestCase
{
    /**
     * Test secrets are hidden from logged parameters
     *
     * @return void
     */
    public function testHideSecrets(): void
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => 'galette_cli',
            'client_secret' => 'cli-secret-for-tests',
            'code' => 'def50200abcdef',
            'refresh_token' => 'def50200123456',
            'access_token' => 'eyJ0eXAiOiJKV1Qi',
            'code_verifier' => 'verifier',
            'password' => 'mypassword',
            'redirect_uri' => 'http://localhost:8888',
        ];

        $this->assertSame(
            [
                'grant_type' => 'authorization_code',
                'client_id' => 'galette_cli',
                'client_secret' => 'HIDDEN',
                'code' => 'HIDDEN',
                'refresh_token' => 'HIDDEN',
                'access_token' => 'HIDDEN',
                'code_verifier' => 'HIDDEN',
                'password' => 'HIDDEN',
                'redirect_uri' => 'http://localhost:8888',
            ],
            \GaletteOAuth2\Tools\Debug::hideSecrets($params)
        );
    }
}
