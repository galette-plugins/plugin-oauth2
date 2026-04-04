<?php

declare(strict_types=1);

/**
 *  OpenID Connect plugin for Galette
 *
 *  PHP version 7
 *
 *  This file is part of 'OpenID Connect plugin for Galette'.
 *
 *  OpenID Connect Plugin for Galette is free software: you can redistribute it
 *  and/or modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation, either version 3 of the License,
 *  or (at your option) any later version.
 *
 *  OpenID Connect Plugin for Galette is distributed in the hope that it will
 *  be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 *  Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with OpenID Connect Plugin for Galette. If not, see
 *  <http://www.gnu.org/licenses/>.
 *
 *  @category Plugins
 *
 *  @author Manuel Hervouet <manuelh78dev@ik.me>
 *  @author Florian Hatat <github@hatat.me>
 *  @copyright Manuel Hervouet (c) 2021
 *  @copyright Florian Hatat (c) 2022
 *  @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0
 */

namespace GaletteOAuth2\Entities;

use Idaas\OpenID\Entities\ClaimEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClaimEntity implements ClaimEntityInterface
{
    use EntityTrait;

    private $type;
    private $essential;
    public const IDENTIFIER = 'id';
    public const ESSENTIAL = 'essential';
    public const TYPE = 'type';

    public function __construct($identifier, $type, $essential)
    {
        $this->setIdentifier($identifier);
        $this->type = $type;
        $this->essential = $essential;
    }

    /**
     * Get type of the claim
     *
     * @return string userinfo|id_token
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Whether this is an essential claim
     *
     */
    public function getEssential(): bool
    {
        return $this->essential;
    }

    public function jsonSerialize(): string
    {
        return json_encode([
            self::IDENTIFIER    => $this->getIdentifier(),
            self::ESSENTIAL  => $this->getEssential(),
            self::TYPE        => $this->getType()
        ]);
    }
}
