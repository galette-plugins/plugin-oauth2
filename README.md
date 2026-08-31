Makes Galette act as a oAuth2 server; so it is possible to use existing members to log-in on third party websites, like [Flarum](https://flarum.org/), [Nextcould](https://nextcloud.com/), and so on!

# Setup

This project use `league/oauth2-server`, `symfony/yaml` and `hassankhan/config` packages.

To automatically download these packages:
```
cd plugin-oauth2
composer install
```

# Configuration

## Prepare public/private keys

```
cd plugin-oauth2/config
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key

vendor/bin/generate-defuse-key
copy-paste the hexadecimal string result in plugin-oauth2/config/encryption-key.php
```

## Configure a ClientEntity

Rename `config/config.yml.dist` to `config/config.yml` and edit according to your third party applicaiton settings:

```
global:
    password: abc123

galette_flarum:
    title: 'Forum Flarum'
    redirect_logout: 'http://192.168.1.99/flarum/public'
    options: teamonly
galette_nc:
    title: 'Nextcloud'
    redirect_logout: 'http://192.168.1.99/nextcloud'
    options: uptodate
galette_xxxxx:

```

The corresponding Flarum configuration:

![Flarum configuration example](examples/flarum.png)

The corresponding NextCloud configuration:

![Nextcloud configuration example](examples/nextcloud.png)

### Available options :
* teamonly : only staff members can login
* uptodate : only uptodate members can login

# Usage

## Nextcloud - how add groups for a specific member
Edit a member : In `info_adh` field you can add a line with `#GROUPS:group1;group2#`

Example :
```
#GROUPS:accouting;home#
```

# More information about OAuth2 Server
* https://oauth2.thephpleague.com/
* https://github.com/thephpleague/oauth2-server/
