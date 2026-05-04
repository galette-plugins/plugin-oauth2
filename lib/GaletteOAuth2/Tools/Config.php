<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Tools;

/**
 * Config class
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Config extends \Noodlehaus\Config
{
    /** @var string[]|string */
    private array|string $path;

    public function __construct(array|string $values)
    {
        $this->path = $values;

        try {
            parent::__construct($values, new \Noodlehaus\Parser\Yaml());
        } catch (\Exception $e) {
            Debug::log("Error load file {$this->path}");
        }
    }

    public function writeFile(): void
    {
        try {
            $this->toFile($this->path, new \Noodlehaus\Writer\Yaml());
        } catch (\Exception $e) {
            Debug::log("Error Write file {$this->path} " . $e->getMessage());
        }
    }

    public function get($name, $default = null)
    {
        return parent::get($name, $default) ?? '';
    }
}
