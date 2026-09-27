<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace GaletteOAuth2\Tools\tests\units;

use Analog\Analog;
use Galette\Tests\GaletteTestCase;

/**
 * Configuration tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Config extends GaletteTestCase
{
    protected int $seed = 20260927090000;

    /**
     * Test reading values
     *
     * @return void
     */
    public function testGet(): void
    {
        $config = new \GaletteOAuth2\Tools\Config([
            'global' => ['title' => 'Galette'],
            'galette_app' => [
                'title' => 'My app',
                'legacy_data' => false,
                'redirect_logout' => null,
                'scopes' => ['member', 'member:phones'],
            ],
            'galette_empty' => null,
        ]);

        $this->assertSame('Galette', $config->get('global.title'));
        $this->assertSame('My app', $config->get('galette_app.title', 'default'));
        $this->assertSame(['member', 'member:phones'], $config->get('galette_app.scopes'));
        $this->assertSame(false, $config->get('galette_app.legacy_data', true));
        $this->assertIsArray($config->get('galette_app'));

        //missing or empty values give the default
        $this->assertNull($config->get('galette_app.password'));
        $this->assertSame('default', $config->get('galette_app.password', 'default'));
        $this->assertSame('default', $config->get('galette_app.redirect_logout', 'default'));
        $this->assertSame('default', $config->get('galette_unknown.title', 'default'));
        $this->assertSame('default', $config->get('galette_empty.title', 'default'));
        $this->assertSame('default', $config->get('global.title.sub', 'default'));

        $this->assertTrue($config->has('galette_app.title'));
        $this->assertFalse($config->has('galette_app.password'));
        $this->assertFalse($config->has('galette_empty'));
    }

    /**
     * Test deprecated "options" entry is read as "authorize"
     *
     * @return void
     */
    public function testOptionsMigration(): void
    {
        $config = new \GaletteOAuth2\Tools\Config([
            'galette_old' => ['options' => 'uptodate'],
            'galette_both' => ['options' => 'uptodate', 'authorize' => 'active'],
        ]);

        $this->assertSame('uptodate', $config->get('galette_old.authorize'));
        $this->assertFalse($config->has('galette_old.options'));
        $this->assertSame('active', $config->get('galette_both.authorize'));
        $this->assertFalse($config->has('galette_both.options'));

        $this->expectLogEntry(
            Analog::WARNING,
            '"options" is deprecated, please use "authorize" instead for galette_old'
        );
        $this->expectLogEntry(
            Analog::WARNING,
            '"options" is deprecated, please use "authorize" instead for galette_both'
        );
    }

    /**
     * Test loading configuration file
     *
     * @return void
     */
    public function testFromFile(): void
    {
        $config = \GaletteOAuth2\Tools\Config::fromFile(OAUTH2_CONFIGPATH . '/config.yml');
        $this->assertSame('Forum Flarum', $config->get('galette_flarum.title'));

        $config = \GaletteOAuth2\Tools\Config::fromFile(OAUTH2_CONFIGPATH . '/missing.yml');
        $this->assertNull($config->get('galette_flarum.title'));
        $this->expectLogEntry(
            Analog::ERROR,
            'OAuth2: unable to read configuration file'
        );
    }
}
