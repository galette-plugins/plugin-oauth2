---
title: Documentation
description: Galette oAuth2 serveur
---

## Configuration

Pour télécharger automatiquement dépendances :
```
cd plugin-oauth2
composer install
```

## Configuration

### Préparer les clés publiques/privées

```
cd plugin-oauth2/config
openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout -out public.key

vendor/bin/generate-defuse-key
copy-paste the hexadecimal string result in plugin-oauth2/config/encryption-key.php
```

### Configurez un ClientEntity

Renommez `config/config.yml.dist` en `config/config.yml` et modifiez pour
correspondre à votre application tierce :

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

La configuration correspondante de Flarum :

![Exemple de configuration Flarum](examples/flarum.png)

La configuration NextCloud correspondante :

![Exemple de configuration Nextcloud](examples/nextcloud.png)

#### Options disponibles :
* teamonly : seuls membres du bureau peuvent se connecter
* uptodate : seuls les adhérents à jour peuvent se connecter

## Utilisation

### Nextcloud - comment ajouter des groupes pour un adhérent donné
Modifier un adhérent : dans le champ `info_adh`, vous pouvez ajouter une ligne
avec `#GROUPS:group1;group2#`

Exemple :
```
#GROUPS:accouting;home#
```

## Plus d'informations sur le serveur OAuth2
* https://oauth2.thephpleague.com/
* https://github.com/thephpleague/oauth2-server/
