<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Tools;

use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Key;
use RuntimeException;

/**
 * Encryption key loader
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class EncryptionKey
{
    /**
     * Example values shipped with the plugin
     */
    private const array PLACEHOLDERS = ['KEY', 'your-encryption-key-here'];

    /**
     * Load encryption key from configuration file, or from encryption-key.php
     *
     * @param Config $config      Configuration
     * @param string $config_path Configuration directory
     *
     * @throws RuntimeException
     */
    public static function load(Config $config, string $config_path): Key
    {
        $encryptionKey = $config->get('global.encryption_key', '');
        if (!self::isSet($encryptionKey)) {
            $encryptionKey = '';
            if (file_exists($config_path . '/encryption-key.php')) {
                include $config_path . '/encryption-key.php';
            }
        }

        if (!self::isSet($encryptionKey)) {
            throw new RuntimeException(
                'OAuth2 encryption key is not set: generate one with vendor/bin/generate-defuse-key'
                . ' and copy it in encryption-key.php in the configuration directory.'
            );
        }

        try {
            return Key::loadFromAsciiSafeString($encryptionKey);
        } catch (BadFormatException $e) {
            throw new RuntimeException(
                'OAuth2 encryption key is invalid: generate a new one with vendor/bin/generate-defuse-key.',
                previous: $e
            );
        }
    }

    /**
     * Is key set to a real value?
     *
     * @phpstan-assert-if-true non-empty-string $key
     */
    private static function isSet(mixed $key): bool
    {
        return is_string($key) && $key !== '' && !in_array($key, self::PLACEHOLDERS, true);
    }
}
