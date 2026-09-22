---
title: Dokumentacija
description: Strežnik Galette oAuth2
---

## Nastavitev

Če želite samodejno prenesti pakete odvisnosti:
```
cd plugin-oauth2
composer install
```

## Konfiguracija

### Pripravite javne/zasebne ključe

```
cd plugin-oauth2/config
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key

vendor/bin/generate-defuse-key
copy-paste the hexadecimal string result in plugin-oauth2/config/encryption-key.php
```

### Konfigurirajte ClientEntity

Preimenujte `config/config.yml.dist` v `config/config.yml` in uredite glede na
nastavitve aplikacije tretje osebe:

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

Ustrezna konfiguracija Flarum:

![Primer konfiguracije Flarum](examples/flarum.png)

Ustrezna konfiguracija NextCloud:

![Primer konfiguracije Nextcloud](examples/nextcloud.png)

#### Razpoložljive možnosti:
* teamonly : prijavijo se lahko samo člani osebja
* uptodate : prijavijo se lahko le člani uptodate

## Uporaba

### Nextcloud - kako dodati skupine za določenega člana
Urejanje člana: v polje `info_adh` lahko dodate vrstico z
`#GROUPS:group1;group2#`

Primer:
```
#GROUPS:accouting;home#
```

## Več informacij o strežniku OAuth2
* https://oauth2.thephpleague.com/
* https://github.com/thephpleague/oauth2-server/
