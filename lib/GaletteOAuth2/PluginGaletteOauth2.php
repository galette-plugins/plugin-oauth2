<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2;

use Galette\Core\GalettePlugin;

/**
 * Galette OAuth2 plugin main class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */

class PluginGaletteOauth2 extends GalettePlugin
{
    /**
     * Is the plugin fully installed (including database, extra configuration, etc.)?
     */
    public function isInstalled(): bool
    {
        //FIXME: check for plugin install requirements (if files are generated and OK, etc.)
        return true;
    }
}
