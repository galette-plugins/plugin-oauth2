<?php

/**
 * Copyright © 2021-2026 The Galette Team
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

use Symfony\Component\Yaml\Yaml;

/**
 * Config class
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 *
 * @implements \Iterator<string, mixed>
 */
final class Config implements \Iterator
{
    private string $path;
    /** @var array<string, mixed> */
    private array $data = [];
    /** @var string[] */
    private array $keys = [];
    private int $position = 0;

    public function __construct(string $path)
    {
        $this->path = $path;

        try {
            if (file_exists($path)) {
                $parsed = Yaml::parseFile($path);
                $this->data = is_array($parsed) ? $parsed : [];
            }
        } catch (\Exception $e) {
            Debug::log("Error load file {$this->path}");
        }

        $this->keys = array_keys($this->data);
    }

    /**
     * Get a config value using dot notation
     *
     * @param string $name    Key path (dot-separated)
     * @param mixed  $default Default value when key is not found
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $parts = explode('.', $name);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Set a config value using dot notation
     *
     * @param string $name  Key path (dot-separated)
     * @param mixed  $value Value to set
     */
    public function set(string $name, mixed $value): void
    {
        $parts = explode('.', $name);
        $current = &$this->data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }

        $this->keys = array_keys($this->data);
    }

    /**
     * Remove a config key using dot notation
     *
     * @param string $name Key path (dot-separated)
     */
    public function remove(string $name): void
    {
        $parts = explode('.', $name);
        $current = &$this->data;
        $lastIndex = count($parts) - 1;

        for ($i = 0; $i < $lastIndex; $i++) {
            if (!isset($current[$parts[$i]]) || !is_array($current[$parts[$i]])) {
                return;
            }
            $current = &$current[$parts[$i]];
        }

        unset($current[$parts[$lastIndex]]);
        $this->keys = array_keys($this->data);
    }

    /**
     * Write the current configuration back to the YAML file
     */
    public function writeFile(): void
    {
        try {
            file_put_contents($this->path, Yaml::dump($this->data, 4, 4));
        } catch (\Exception $e) {
            Debug::log("Error Write file {$this->path} " . $e->getMessage());
        }
    }

    // Iterator interface methods

    public function current(): mixed
    {
        return $this->data[$this->keys[$this->position]] ?? null;
    }

    public function key(): string|null
    {
        return $this->keys[$this->position] ?? null;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->keys[$this->position]);
    }
}
