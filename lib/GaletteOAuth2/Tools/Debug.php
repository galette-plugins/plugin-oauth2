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

use Analog\Analog;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
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
