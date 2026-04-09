Makes Galette act as a oAuth2 server; so it is possible to use existing members to log-in on third party websites, like [Flarum](https://flarum.org/), [Nextcould](https://nextcloud.com/), and so on!

**OpenID Connect (OIDC) is now supported!** This allows integration with OIDC-compatible applications using standard discovery and the `openid` scope.

Most of the time, oAuth2 client capacities on third party websites are available from "plugins". Check their docs and forums ;)

# Setup

This project use `league/oauth2-server`, `symfony/yaml` and `hassankhan/config` packages.

To automatically download these packages:
```
cd plugin-oauth2
composer install
```

# Updating to version 3.0.0

Before updating to version 3.0.0, please take care of the following:
- the existing `options` entry in configuration file has been renamed to `authorize`. Please update your configuration file accordingly.
- the `scopes` entry in configuration file has been added; some data you were previously using may be missing.
- previous versions were using non Galette data (like `username`). If you were using this data and still want to rely on them; add a `legacy_data: true` in you applications entries.
- Real Galette groups have been added to `member:groups:` scope
- Member status is no longer the first groups entry
- Groups hack from `info_adh` field has been removed
- Groups names reformatting has been removed

# Configuration

## Prepare public/private keys

```
cd plugin-oauth2/config
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key
chmod 660 *.key

vendor/bin/generate-defuse-key
copy-paste the hexadecimal string result in plugin-oauth2/config/encryption-key.php
```

## Configure a ClientEntity

Rename `config/config.yml.dist` to `config/config.yml` and edit according to your third party application settings:

```yaml
global:
    password: abc123
    # Required for OpenID Connect: set your OAuth2 server base URL
    issuer: 'https://galette.example.org/plugins/oauth2'

galette_flarum:
    title: 'Forum Flarum'
    redirect_logout: 'http://192.168.1.99/flarum/public'
galette_nc:
    title: 'Nextcloud'
    redirect_logout: 'http://192.168.1.99/nextcloud'
    scopes:
        - member:groups
galette_xxxxx:
```

The corresponding Flarum configuration:

![Flarum configuration example](examples/flarum.png)

The corresponding NextCloud configuration:

![Nextcloud configuration example](examples/nextcloud.png)

The corresponding Wordpress configuration:

![Wordpress configuration example](examples/wordpress.png)

The corresponding Drupal CMS configuration:

![Drupal CMS configuration example](examples/drupal.png)

The corresponding DokuWiki configuration:

![DokuWiki configuration example](examples/dokuwiki.png)

### Available authorizations:

* `active`: only active members can log in
* `uptodate`: only active and up-to-date members can log in
* `teamonly`: only active team members (admins, staff and groups managers)

When there is no `authorize` entry set in configuration, it defaults to `teamonly`.

### Scopes

Default `member` scope will be added if it is not present in your configuration (even if you do not set any scope).
To declare multiple scopes, separate them with a space like `member member:phone member:localization`.

#### OpenID Connect Scopes

These standard OIDC scopes are now supported:

* `openid`: Required for OIDC authentication - enables id_token generation
* `profile`: Basic profile (name, nickname, preferred_username, locale, gender, birthdate)
* `email`: Email address and verification status
* `address`: Postal address (formatted, street, city, region, postal code, country)
* `phone`: Phone number

#### Galette-specific Scopes

* `member`: default, basic scope - always included:
  * user full name,
  * login,
  * email,
  * language
  * company name if relevant
* `member:personal` precise personal data:
  * birthdate,
  * job,
  * gender,
  * birthplace
  * GPG id
* `member:localization` localization data:
  * country,
  * region,
  * town,
  * zipcode
* `member:localization:precise` precise localization data:
  * address,
  * maps plugin coordinates
* `member:phones`:
  * mobile phone
  * phone
* `member:socials`:
  * all registered social networks
* `member:groups`:
  * groups member is part of
* `member:due_date`:
  * due date

# OpenID Connect (OIDC) Support

This plugin now supports OpenID Connect, enabling integration with OIDC-compatible applications.

## OIDC Endpoints

The following discovery and OIDC endpoints are available:

| Endpoint | URL | Description |
|----------|-----|-------------|
| OIDC Discovery | `/.well-known/openid-configuration` | OpenID Connect configuration |
| OAuth2 Metadata | `/.well-known/oauth-authorization-server` | RFC 8414 metadata |
| JWKS | `/.well-known/jwks.json` | Public keys for signature verification |
| UserInfo | `/userinfo` | User claims endpoint |
| Authorize | `/authorize` | Authorization endpoint |
| Token | `/access_token` | Token endpoint |

## OIDC Configuration

1. **Set the issuer URL** in `config/config.yml`:
   ```yaml
   global:
       issuer: 'https://galette.example.org/plugins/oauth2'
   ```

2. **Configure your client application** to use OIDC discovery at:
   ```
   https://galette.example.org/plugins/oauth2/.well-known/openid-configuration
   ```

3. **Request the `openid` scope** to receive an `id_token` in the token response.

## Supported Flows

* **Authorization Code** with PKCE (recommended)
* **Authorization Code** without PKCE (for confidential clients)

**Note:** The Implicit Grant is intentionally not supported as it is deprecated for security reasons.

## id_token Claims

When the `openid` scope is requested, the token response includes a signed JWT `id_token` containing:

* Standard claims: `sub`, `iss`, `aud`, `exp`, `iat`, `auth_time`
* Profile claims (with `profile` scope): `name`, `family_name`, `given_name`, `nickname`, `preferred_username`, `locale`, `gender`, `birthdate`, `updated_at`
* Email claims (with `email` scope): `email`, `email_verified`
* Address claims (with `address` scope): `address` object
* Phone claims (with `phone` scope): `phone_number`, `phone_number_verified`

## OIDC Client Configuration Examples

### Generic OIDC Client

```
Discovery URL: https://galette.example.org/plugins/oauth2/.well-known/openid-configuration
Client ID: your_client_id
Client Secret: your_client_secret
Scopes: openid profile email
```

### Nextcloud (with OIDC)

Use the "Social Login" or "OpenID Connect" app with:
- Discovery URL: `https://galette.example.org/plugins/oauth2/.well-known/openid-configuration`
- Scopes: `openid profile email member:groups`

# More information about OAuth2 Server
* https://oauth2.thephpleague.com/
* https://github.com/thephpleague/oauth2-server/
