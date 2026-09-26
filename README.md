Makes Galette act as a oAuth2 server; so it is possible to use existing members to log-in on third party websites, like [Flarum](https://flarum.org/), [Nextcloud](https://nextcloud.com/), and so on!

Most of the time, oAuth2 client capacities on third party websites are available from "plugins". Check their docs and forums ;)

# Setup

This project uses `league/oauth2-server`, `defuse/php-encryption` and `hassankhan/config` packages; `symfony/yaml` is provided by Galette.

To automatically download these packages:
```
cd plugin-oauth2
composer install
```

# Updating from version 3.0.x

Some settings are now mandatory, clients that do not follow them are refused (the reason is written in Galette logs):
- each client must declare its `redirect_uri`: the callback URL of the client application, exactly as the application sends it. Use a list if the application uses several URLs.
- each client must have its own `password`; the `global` password is no longer used, and the `abc123` example password is refused.
- only the "authorization code" and "refresh token" grants are available.
- scopes from the `scopes` entry are checked by default on the authorization screen; they are no longer given if the member unchecks them.
- members already logged in to the authorization screen will have to log in again.

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

```
global:
    title: 'Galette'

galette_flarum:
    password: 'a-long-random-secret'
    title: 'Forum Flarum'
    redirect_uri: 'http://192.168.1.99/flarum/public/auth/passport'
    redirect_logout: 'http://192.168.1.99/flarum/public'
galette_nc:
    password: 'another-long-random-secret'
    title: 'Nextcloud'
    redirect_uri: 'http://192.168.1.99/nextcloud/apps/sociallogin/custom_oauth2/galette'
    redirect_logout: 'http://192.168.1.99/nextcloud'
    scopes:
        - member:groups
```

`password` and `redirect_uri` are mandatory for each client. `redirect_uri` can be a list of URLs.

Other client entries:
* `title`: application name displayed on login and authorization screens,
* `redirect_logout`: where to send members after they log out; Galette home page if not set,
* `authorize`: who can log in, see below,
* `scopes`: scopes requested by default, see below,
* `legacy_data`: set to `true` to get data as they were sent before version 3.0.0.

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
To declare multiple scopes, separate them with a space like `member member:phones member:localization`.

* `member`: default, basic scope - always included:
  * user id,
  * user full name,
  * login,
  * email,
  * language,
  * status
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
  * full address
* `member:phones`:
  * mobile phone
  * phone
* `member:socials`:
  * all registered social networks
* `member:groups`:
  * groups member is part of
* `member:due_date`:
  * due date

# More information about OAuth2 Server
* https://oauth2.thephpleague.com/
* https://github.com/thephpleague/oauth2-server/
