<?php

/**
 *  This file is part of 'Galette OAuth2 plugin'.
 *  Galette OAuth2 Plugin is free software: you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *
 *  Galette OAuth2 Plugin is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *
 * Galette is free software: you can redistribute it and/or modify
 *  with Galette OAuth2 Plugin. If not, see <http://www.gnu.org/licenses/>.
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

namespace GaletteOAuth2\Entities;

use Idaas\OpenID\Entities\AccessTokenEntityInterface;
use Idaas\OpenID\Entities\Traits\AccessTokenTrait;
use Idaas\OpenID\Entities\ClaimEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/**
 * Class AccessTokenEntity
 *
 * @author Manuel Hervouet <manuelh78dev@ik.me>
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
final class AccessTokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    use TokenEntityTrait;
    use EntityTrait;

    public const TABLE = 'galette_plugin_oauth2_access_tokens';
    public const PK = 'token_id';

    /**
     * @var ClaimEntityInterface[]
     */
    protected array $claims = [];

    public function getClaims()
    {
        return $this->claims;
    }

    public function addClaim(ClaimEntityInterface $claim): void
    {
        $this->claims[$claim->getIdentifier()] = $claim;
    }
}
