<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Tools;

use Analog\Analog;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Read only configuration, with dot notation access
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Config
{
    /** @var array<mixed> */
    private array $data;

    /**
     * @param array<mixed> $data Configuration values
     */
    public function __construct(array $data)
    {
        $this->data = $this->migrate($data);
    }

    /**
     * Load configuration from a YAML file
     *
     * An unreadable file gives an empty configuration: every client will be refused.
     */
    public static function fromFile(string $path): self
    {
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            Analog::log(
                sprintf(
                    'OAuth2: unable to read configuration file %1$s: %2$s',
                    $path,
                    $e->getMessage()
                ),
                Analog::ERROR
            );
            $data = [];
        }

        return new self(is_array($data) ? $data : []);
    }

    /**
     * Get a value, using dot notation (client.entry)
     *
     * @param string $key     Key
     * @param mixed  $default Value returned when key is missing or empty
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value ?? $default;
    }

    /**
     * Is a value set?
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Handle deprecated entries
     *
     * @param array<mixed> $data Configuration values
     *
     * @return array<mixed>
     */
    private function migrate(array $data): array
    {
        foreach ($data as $key => $entry) {
            if (!is_array($entry) || !array_key_exists('options', $entry)) {
                continue;
            }

            Analog::log(
                '"options" is deprecated, please use "authorize" instead for ' . $key,
                Analog::WARNING
            );
            if (!isset($entry['authorize'])) {
                $data[$key]['authorize'] = $entry['options'];
            }
            unset($data[$key]['options']);
        }

        return $data;
    }
}
