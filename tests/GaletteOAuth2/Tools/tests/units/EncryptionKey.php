<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Tools\tests\units;

use Defuse\Crypto\Key;
use GaletteOAuth2\Tools\Config;
use PHPUnit\Framework\TestCase;

/**
 * Encryption key loading tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class EncryptionKey extends TestCase
{
    private string $config_path;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->config_path = sys_get_temp_dir() . '/oauth2-key-' . uniqid();
        mkdir($this->config_path);
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        array_map('unlink', glob($this->config_path . '/*'));
        rmdir($this->config_path);
    }

    /**
     * Write configuration files
     *
     * @param ?string $config_key Key in config.yml, if any
     * @param ?string $file_key   Key in encryption-key.php, if any
     *
     * @return Config
     */
    private function writeConfig(?string $config_key, ?string $file_key): Config
    {
        $yaml = "global:\n    title: 'Galette'\n";
        if ($config_key !== null) {
            $yaml .= "    encryption_key: '{$config_key}'\n";
        }
        file_put_contents($this->config_path . '/config.yml', $yaml);

        if ($file_key !== null) {
            file_put_contents(
                $this->config_path . '/encryption-key.php',
                "<?php\n\n\$encryptionKey = '{$file_key}';\n"
            );
        }

        return new Config($this->config_path . '/config.yml');
    }

    /**
     * Test key from configuration file
     *
     * @return void
     */
    public function testLoadFromConfig(): void
    {
        $key = Key::createNewRandomKey()->saveToAsciiSafeString();
        $config = $this->writeConfig($key, 'KEY');

        $this->assertSame(
            $key,
            \GaletteOAuth2\Tools\EncryptionKey::load($config, $this->config_path)->saveToAsciiSafeString()
        );
    }

    /**
     * Test key from PHP file, configuration still has the example value
     *
     * @return void
     */
    public function testLoadFromFile(): void
    {
        $key = Key::createNewRandomKey()->saveToAsciiSafeString();

        $config = $this->writeConfig('your-encryption-key-here', $key);
        $this->assertSame(
            $key,
            \GaletteOAuth2\Tools\EncryptionKey::load($config, $this->config_path)->saveToAsciiSafeString()
        );

        $config = $this->writeConfig(null, $key);
        $this->assertSame(
            $key,
            \GaletteOAuth2\Tools\EncryptionKey::load($config, $this->config_path)->saveToAsciiSafeString()
        );
    }

    /**
     * Test missing key
     *
     * @return void
     */
    public function testMissingKey(): void
    {
        $config = $this->writeConfig('your-encryption-key-here', 'KEY');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth2 encryption key is not set');
        \GaletteOAuth2\Tools\EncryptionKey::load($config, $this->config_path);
    }

    /**
     * Test invalid key
     *
     * @return void
     */
    public function testInvalidKey(): void
    {
        $config = $this->writeConfig('not-a-defuse-key', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth2 encryption key is invalid');
        \GaletteOAuth2\Tools\EncryptionKey::load($config, $this->config_path);
    }
}
