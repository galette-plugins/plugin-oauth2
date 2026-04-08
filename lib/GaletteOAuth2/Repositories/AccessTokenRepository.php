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

namespace GaletteOAuth2\Repositories;

use GaletteOAuth2\Entities\AccessTokenEntity;
use GaletteOAuth2\Entities\ClaimEntity;
use Idaas\OpenID\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface as LeagueAccessTokenEntityInterface;
use Psr\Container\ContainerInterface;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     *
     */
    public function persistNewAccessToken(LeagueAccessTokenEntityInterface $accessTokenEntity): void
    {
        // Save access token to database
        $zdb = $this->container->get('zdb');
        $scopes = [];
        foreach ($accessTokenEntity->getScopes() as $scope) {
            $scopes[] = $scope->getIdentifier();
        }
        $query = $zdb->insert(AccessTokenEntity::TABLE)->values(
            [
                'token_id' => $accessTokenEntity->getIdentifier(),
                'id_adh' => $accessTokenEntity->getUserIdentifier(),
                'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
                'scopes' => json_encode($scopes),
                'expiry' => $accessTokenEntity->getExpiryDateTime()->format("Y-m-d H:i:s"),
                'revoked' => 0,
            ]
        );
        $zdb->execute($query);

        // Regularly cleanup old expired tokens
        $query = $zdb->delete(AccessTokenEntity::TABLE)->where(function ($w): void {
            $w->lessThan('expiry', (new \DateTimeImmutable("1 day ago"))->format("Y-m-d H:i:s"));
        });
        $zdb->execute($query);
    }

    /**
     */
    public function revokeAccessToken(mixed $tokenId): void
    {
        $zdb = $this->container->get('zdb');
        // Revoke access token in database
        $query = $zdb->update(AccessTokenEntity::TABLE)->set(['revoked' => true])->where([AccessTokenEntity::PK => $tokenId]);
        $zdb->execute($query);
    }

    /**
     */
    public function isAccessTokenRevoked(mixed $tokenId)
    {
        $zdb = $this->container->get('zdb');
        $query = $zdb->select(AccessTokenEntity::TABLE)->where([AccessTokenEntity::PK => $tokenId]);
        $results = $zdb->execute($query);

        // Access token does not exist, assume it has been revoked
        if ($results->count() !== 1) {
            return true;
        }

        return boolval($results->current()->revoked);
    }

    /**
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, mixed $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);

        return $accessToken;
    }

    /**
     */
    public function getAccessToken(mixed $tokenId)
    {
        $zdb = $this->container->get('zdb');
        $query = $zdb->select(AccessTokenEntity::TABLE)->where([AccessTokenEntity::PK => $tokenId]);
        $results = $zdb->execute($query);

        if ($results->count() !== 1) {
            return null;
        }

        $rs = $results->current();
        $clientRepository = $this->container->get(ClientRepository::class);
        $scopeRepository = $this->container->get(ScopeRepository::class);
        $claimRepository = $this->container->get(ClaimRepository::class);

        $accessToken = new AccessTokenEntity();
        $accessToken->setIdentifier($rs->token_id);
        $accessToken->setUserIdentifier($rs->id_adh);
        $accessToken->setClient($clientRepository->getClientEntity($rs->client_id));
        $accessToken->setExpiryDateTime(new \DateTimeImmutable($rs->expiry));
        foreach (json_decode($rs->scopes) as $scope_id) {
            $scope = $scopeRepository->getScopeEntityByIdentifier($scope_id);
            $accessToken->addScope($scope);

            foreach ($claimRepository->getClaimsByScope($scope) as $claim) {
                $accessToken->addClaim($claim);
            }
        }

        return $accessToken;
    }

    /**
     *
     */
    public function storeClaims(LeagueAccessTokenEntityInterface $token, array $claims): void
    {
        foreach ($claims as $serclaim) {
            $claim = new ClaimEntity(
                $serclaim[ClaimEntity::IDENTIFIER],
                $serclaim[ClaimEntity::TYPE],
                $serclaim[ClaimEntity::ESSENTIAL]
            );
            $token->addClaim($claim);
        }
    }
}
