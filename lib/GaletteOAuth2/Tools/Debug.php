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

    public static function logRequest(string $fct, Request $request): void
    {
        $msg = sprintf(
            "%s - URI: %s",
            $fct,
            $request->getUri()
        );
        if (count($qp = $request->getQueryParams()) > 0) {
            $msg .= "\nGET dump: " . self::printVar($qp);
        }
        if (count($post = (array)$request->getParsedBody()) > 0) {
            if (isset($post['password'])) {
                $post['password'] = 'HIDDEN';
            }
            $msg .= "\nPOST dump: " . self::printVar($post);
        }
        $msg .= "\n";
        Analog::log(
            $msg,
            Analog::DEBUG
        );
    }
}
