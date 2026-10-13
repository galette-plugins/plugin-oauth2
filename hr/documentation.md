---
title: Dokumentacija
description: Galetteov OAuth2 poslužitelj
---

## Postavljanje

Za automatsko preuzimanje paketa ovisnosti:
```
cd plugin-oauth2
composer install
```

## Podešavanje

### Priprema javnog i privatnog ključa

```
cd plugin-oauth2/config
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key

vendor/bin/generate-defuse-key
copy-paste the hexadecimal string result in plugin-oauth2/config/encryption-key.php
```

### Podešavanje ClientEntity

Preimenujte `config/config.yml.dist` u `config/config.yml` i uredite prema
postavkama vanjske aplikacije:

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

Odgovarajuće postavke Flaruma:

![Primjer postavki Flaruma](examples/flarum.png)

Odgovarajuće postavke Nextclouda:

![Primjer postavki Nextclouda](examples/nextcloud.png)

#### Dostupne opcije:
* teamonly: prijaviti se mogu samo članovi uprave
* uptodate: prijaviti se mogu samo članovi s podmirenom članarinom

## Uporaba

### Nextcloud – dodavanje grupa određenom članu
Uredite člana: u polje `info_adh` možete dodati redak `#GROUPS:group1;group2#`

Primjer:
```
#GROUPS:accouting;home#
```

## Više informacija o OAuth2 poslužitelju
* https://oauth2.thephpleague.com/
* https://github.com/thephpleague/oauth2-server/
