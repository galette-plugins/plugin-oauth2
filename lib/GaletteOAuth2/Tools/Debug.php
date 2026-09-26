<?php

/**
 * This file is part of Galette OAuth2 plugin (https://galette-plugins.github.io/plugin-oauth2/).
 * SPDX-FileCopyrightText: Copyright © 2021-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteOAuth2\Tools;

use Analog\Analog;
use Slim\Psr7\Request;

/**
 * Debug tools
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class Debug
{
    private const array HIDDEN_PARAMS = [
        'password',
        'client_secret',
        'code',
        'refresh_token',
        'access_token',
        'code_verifier',
    ];

    public static function printVar($expression, bool $return = true)
    {
        $export = print_r($expression, true);
        $patterns = [
            '/array \\(/' => '[',
            '/^([ ]*)\\)(,?)$/m' => '$1]$2',
            "/=>[ ]?\n[ ]+\\[/" => '=> [',
            "/([ ]*)(\\'[^\\']+\\') => ([\\[\\'])/" => '$1$2 => $3',
        ];
        $export = preg_replace(array_keys($patterns), array_values($patterns), $export);

        if ($return) {
            return $export;
        }
        echo $export;
    }

    public static function log(string $txt): void
    {
        Analog::log(
            $txt,
            Analog::DEBUG
        );
    }

    /**
     * Hide secrets from parameters before they are logged
     *
     * @param array<string, mixed> $params Request parameters
     *
     * @return array<string, mixed>
     */
    public static function hideSecrets(array $params): array
    {
        foreach (self::HIDDEN_PARAMS as $name) {
            if (isset($params[$name])) {
                $params[$name] = 'HIDDEN';
            }
        }
        return $params;
    }

    public static function logRequest(string $fct, Request $request): void
    {
        $msg = sprintf(
            "%s - URI: %s",
            $fct,
            $request->getUri()->getPath()
        );
        if (count($qp = $request->getQueryParams()) > 0) {
            $msg .= "\nGET dump: " . self::printVar(self::hideSecrets($qp));
        }
        if (count($post = (array)$request->getParsedBody()) > 0) {
            $msg .= "\nPOST dump: " . self::printVar(self::hideSecrets($post));
        }
        $msg .= "\n";
        Analog::log(
            $msg,
            Analog::DEBUG
        );
    }
}
