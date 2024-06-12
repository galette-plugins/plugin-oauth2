<?php

/**
 * Copyright © 2021-2024 The Galette Team
 *
 * This file is part of Galette OAuth2 plugin (https://galette-community.github.io/plugin-oauth2/).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette OAuth2 plugin. If not, see <http://www.gnu.org/licenses/>.
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
