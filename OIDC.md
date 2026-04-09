# OpenID Connect (OIDC) Implementation for Galette OAuth2 Plugin

## Overview

This document describes the OpenID Connect support added to the Galette OAuth2 plugin, enabling secure authentication with OIDC-compatible applications using standard discovery endpoints and id_token generation.

## Architecture

### Core Components

#### 1. **IdTokenBuilder** (`lib/GaletteOAuth2/OIDC/IdTokenBuilder.php`)
- Generates signed JWT id_tokens (RS256)
- Uses `lcobucci/jwt` library (already present in league/oauth2-server)
- Includes standard OIDC claims: `sub`, `iss`, `aud`, `exp`, `iat`, `auth_time`, `nonce`
- Extracts user claims via `ClaimExtractor` based on granted scopes

#### 2. **ClaimExtractor** (`lib/GaletteOAuth2/OIDC/ClaimExtractor.php`)
- Maps Galette member data to OIDC standard claims
- Supports OIDC scopes: `openid`, `profile`, `email`, `address`, `phone`
- Also supports Galette-specific scopes for backward compatibility
- Shared by both `IdTokenBuilder` and the `/userinfo` endpoint

#### 3. **OidcBearerTokenResponse** (`lib/GaletteOAuth2/ResponseTypes/OidcBearerTokenResponse.php`)
- Extends `BearerTokenResponse` from league/oauth2-server
- Injects `id_token` into token response when `openid` scope is present
- Sets the nonce parameter for id_token generation

#### 4. **OidcAuthCodeGrant** (`lib/GaletteOAuth2/Grant/OidcAuthCodeGrant.php`)
- Extends standard `AuthCodeGrant`
- Passes nonce from token request to response for id_token inclusion
- Maintains PKCE support

#### 5. **DiscoveryController** (`lib/GaletteOAuth2/Controllers/DiscoveryController.php`)
- Implements three discovery endpoints:
  - `/.well-known/openid-configuration` (OpenID Connect Discovery 1.0)
  - `/.well-known/oauth-authorization-server` (RFC 8414)
  - `/.well-known/jwks.json` (JSON Web Key Set)
- Exposes public RSA key for signature verification

#### 6. **Extended ApiController** (`lib/GaletteOAuth2/Controllers/ApiController.php`)
- New `/userinfo` endpoint (GET and POST)
- Returns OIDC standard claims based on granted scopes
- Implements RFC 6750 Bearer token authentication

## Configuration

### 1. Update `config/config.yml`

```yaml
global:
    password: your_secure_password
    # OIDC issuer URL - REQUIRED for OpenID Connect
    issuer: 'https://galette.example.org/plugins/oauth2'
    title: 'Galette OAuth2 Server'

# Example OIDC-compatible client
example_oidc_client:
    title: 'Example OIDC App'
    redirect_logout: 'https://app.example.org'
    authorize: active  # or 'uptodate' or 'teamonly'
    scopes:
        - openid         # Required for OIDC
        - profile        # User profile claims
        - email          # Email claim
        - member:groups  # Additional Galette-specific scope
```

### 2. Ensure RSA Keys Exist

```bash
cd plugin-oauth2/config
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key
chmod 660 *.key
```

### 3. Encryption Key

Ensure `encryption-key.php` exists in the config directory with a valid encryption key.

## OIDC Flows Supported

### Authorization Code with PKCE (Recommended for Public Clients)

```
1. Client initiates authorization request with:
   - response_type=code
   - client_id
   - redirect_uri
   - scope (including 'openid')
   - code_challenge (base64url-encoded SHA256 hash)
   - code_challenge_method=S256
   - nonce (for replay attack protection)

2. User authenticates at Galette

3. User grants consent to requested scopes

4. Authorization code returned

5. Client exchanges code for tokens:
   - POST /access_token
   - code
   - code_verifier (proves possession of code_challenge)
   - client_id

6. Response includes:
   - access_token
   - token_type: Bearer
   - expires_in
   - id_token (JWT with user claims)
   - refresh_token (optional)
```

### Authorization Code (For Confidential Clients)

Similar to above, but without PKCE parameters.

## OIDC Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/.well-known/openid-configuration` | GET | OIDC Provider configuration discovery |
| `/.well-known/oauth-authorization-server` | GET | OAuth 2.0 server metadata (RFC 8414) |
| `/.well-known/jwks.json` | GET | Public keys for signature verification |
| `/authorize` | GET, POST | Authorization endpoint |
| `/access_token` | POST | Token endpoint |
| `/userinfo` | GET, POST | User information endpoint (RFC 6750) |

## Standard OIDC Scopes

### `openid`
- **Required** for OIDC authentication
- Enables `id_token` generation
- Claims: `sub`, `iss`, `aud`, `exp`, `iat`, `auth_time`

### `profile`
- User profile information
- Claims: `name`, `family_name`, `given_name`, `nickname`, `preferred_username`, `locale`, `gender`, `birthdate`, `updated_at`

### `email`
- Email address and verification
- Claims: `email`, `email_verified`

### `address`
- Postal address
- Claims: `address` (object with `formatted`, `street_address`, `locality`, `region`, `postal_code`, `country`)

### `phone`
- Phone number
- Claims: `phone_number`, `phone_number_verified`

## Client Examples

### Generic OIDC Client

```bash
Discovery URL: https://galette.example.org/plugins/oauth2/.well-known/openid-configuration
Client ID: example_oidc_client
Client Secret: (from configuration)
Scopes: openid profile email
Redirect URIs: https://app.example.org/callback
```

### Nextcloud with OIDC

Use "Social Login" or "OpenID Connect" app:

```
Discovery URL: https://galette.example.org/plugins/oauth2/.well-known/openid-configuration
Client ID: galette_nc
Client Secret: (from configuration)
Scopes: openid profile email member:groups
```

### Keycloak Federation

Add a generic OIDC provider with discovery URL pointing to Galette's endpoint.

## id_token Structure

The id_token is a signed JWT (RS256) containing:

```json
{
  "iss": "https://galette.example.org/plugins/oauth2",
  "sub": "123",
  "aud": "example_oidc_client",
  "exp": 1234567890,
  "iat": 1234567200,
  "auth_time": 1234567200,
  "nonce": "n-0S6_WzA2Mj",
  "jti": "a1b2c3d4e5f6g7h8",
  "name": "John Doe",
  "family_name": "Doe",
  "given_name": "John",
  "email": "john@example.org",
  "email_verified": true,
  "locale": "en_US",
  "groups": ["member", "uptodate", "admin"]
}
```

**Claims included vary by scope:**
- All id_tokens include: `iss`, `sub`, `aud`, `exp`, `iat`, `auth_time`, `jti`
- `nonce` included if provided in authorization request
- Profile claims with `profile` scope
- Email claims with `email` scope
- Address claims with `address` scope
- Phone claims with `phone` scope

## Security Considerations

### 1. PKCE (Proof Key for Public Clients Exchange)
- Mandatory for public clients (web apps without a confidential backend)
- Use `code_challenge_method=S256` (SHA256)
- Prevents authorization code interception attacks

### 2. Nonce Parameter
- Recommended to prevent replay attacks
- Should be cryptographically random
- Validated and included in id_token

### 3. Signature Verification
- Clients must verify id_token signature using public key from `/.well-known/jwks.json`
- Use `alg=RS256` (RSA with SHA-256)
- Never trust unverified tokens

### 4. Token Expiration
- Access tokens expire after 1 hour
- Refresh tokens expire after 1 month
- id_tokens expire at same time as access_token

### 5. HTTPS Requirement
- Always use HTTPS in production
- Set proper `issuer` URL in configuration

## Scope Mappings

### OIDC to Galette Member Data

| OIDC Scope | Galette Data | Claims |
|-----------|--------------|---------|
| `profile` | Member basic info | name, family_name, given_name, nickname, preferred_username, locale, gender, birthdate, updated_at |
| `email` | Email address | email, email_verified |
| `address` | Postal address | address (formatted, street_address, locality, region, postal_code, country) |
| `phone` | Phone numbers | phone_number, phone_number_verified |

### Galette-Specific Scopes (Backward Compatibility)

| Scope | Claims |
|-------|--------|
| `member` | Basic member info (login, email, language, etc.) |
| `member:personal` | Birthdate, job, gender, birthplace, GnuPG ID |
| `member:localization` | Country, region, town, zipcode |
| `member:localization:precise` | Full address, coordinates |
| `member:phones` | All phone numbers |
| `member:socials` | Social network URLs |
| `member:groups` | Groups membership, roles |
| `member:due_date` | Membership due date |

## Error Handling

### Invalid Scope

```json
{
  "error": "invalid_scope",
  "error_description": "The requested scope is invalid"
}
```

### Access Denied

```json
{
  "error": "access_denied",
  "error_description": "The user denied the request"
}
```

### Missing Required Parameter

```json
{
  "error": "invalid_request",
  "error_description": "Missing required parameter: nonce"
}
```

## Debugging

Enable debug logging in configuration:

```php
// _config.inc.php
define('OAUTH2_LOG', true);
define('OAUTH2_DEBUGSESSION', false); // or true for verbose session debugging
```

Logs are written to `GALETTE_CACHE_DIR` with prefix `OAUTH2_`.

## RFC Compliance

- **RFC 6234**: US Secure Hash Algorithms (SHA-256)
- **RFC 6749**: The OAuth 2.0 Authorization Framework
- **RFC 6750**: OAuth 2.0 Bearer Token Usage
- **RFC 7231**: HTTP Semantics and Content
- **RFC 7515**: JSON Web Signature (JWS)
- **RFC 7517**: JSON Web Key (JWK)
- **RFC 7518**: JSON Web Algorithms (JWA)
- **RFC 7636**: Proof Key for Public Clients Exchange (PKCE)
- **OpenID Connect Core 1.0**: OpenID Connect specification
- **OpenID Connect Discovery 1.0**: Discovery specification
- **RFC 8414**: OAuth 2.0 Authorization Server Metadata

## Future Enhancements

1. **Nonce Storage in Auth Code**: Store nonce in encrypted auth code payload for better security
2. **Implicit Flow**: Could be added if clients require it (not recommended)
3. **Hybrid Flow**: Authorization Code + ID Token
4. **Client Registration Endpoint**: Dynamic client registration
5. **Introspection Endpoint**: Token introspection
6. **Revocation Endpoint**: Token revocation
7. **Device Flow**: For devices without web browsers
8. **Mutual TLS Authentication**: For high-security deployments

## Support and Issues

For issues, questions, or feature requests:
- GitHub: https://github.com/galette/plugin-oauth2
- Documentation: https://doc.galette.eu/
- Bug Tracker: https://bugs.galette.eu/

## License

GPLv3 - See LICENSE file for details

