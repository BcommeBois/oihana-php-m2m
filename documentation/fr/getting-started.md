# Démarrage rapide

Ce guide vous emmène d'un `composer require` neuf à un appel API authentifié réussi en moins de 2 minutes.

## Pré-requis

- **PHP 8.4+** avec l'extension `json` activée (par défaut).
- **Composer** installé localement.
- Un **keyfile JSON** pour un compte de service M2M, fourni par l'administrateur de l'API. Voir [keyfile-format.md](keyfile-format.md) pour le schéma.
- L'**URL de base** de l'API, l'**URL de l'émetteur** (issuer) de l'IdP, et le **scope OAuth2** demandé. Les keyfiles modernes les portent déjà — vous n'en avez besoin qu'avec un keyfile legacy ou pour les surcharger à l'exécution.

## Étape 1 — Installer

```shell
composer require oihana/php-m2m
```

## Étape 2 — Stocker le keyfile en sécurité

Sauvegardez le keyfile JSON hors de votre arborescence versionnée, avec des permissions restrictives :

```shell
mkdir -p /etc/my-service/secrets
mv ~/Downloads/m2m-keyfile.json /etc/my-service/secrets/m2m-keyfile.json
chmod 600 /etc/my-service/secrets/m2m-keyfile.json
```

En déploiement conteneurisé, montez le keyfile comme un secret en lecture seule (Kubernetes `Secret`, secret Docker swarm, AWS Secrets Manager, Vault, …) — ne l'intégrez jamais dans l'image.

## Étape 3 — Construire le client

```php
<?php

use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile( '/etc/my-service/secrets/m2m-keyfile.json' ) ;
```

La factory lit le JSON, valide les champs obligatoires (`key`, `keyId`, et l'un de `userId` / `clientId`), et résout `issuer` / `apiBaseUrl` / `scope` à partir du contenu du keyfile.

## Étape 4 — Appeler l'API

```php
// GET — lister des ressources
$widgets = $client->get( '/widgets' ) ;

// POST — créer une ressource
$created = $client->post( '/widgets' , [ 'name' => 'Foo' , 'price' => 9.99 ] ) ;

// PATCH — mise à jour partielle
$updated = $client->patch( '/widgets/42' , [ 'price' => 12.50 ] ) ;

// PUT — remplacement complet
$replaced = $client->put( '/widgets/42' , [ 'name' => 'Bar' , 'price' => 14.00 ] ) ;

// DELETE — supprimer une ressource
$client->delete( '/widgets/42' ) ;
```

Tous les verbes retournent l'**enveloppe JSON décodée** sous forme d'`array<string, mixed>` associatif. Un tableau vide est retourné quand le corps n'est pas un objet JSON (par exemple pour une réponse `204 No Content`).

## Étape 5 — Gérer les erreurs

Encadrez les appels d'un `try / catch` pour distinguer un keyfile mort d'une panne réseau transitoire :

```php
use oihana\m2m\exceptions\KeyfileInvalidException;
use GuzzleHttp\Exception\GuzzleException;

try
{
    $widgets = $client->get( '/widgets' ) ;
}
catch( KeyfileInvalidException $e )
{
    // Le keyfile est rejeté par l'IdP ou par l'API.
    // Action : re-télécharger un keyfile neuf depuis l'UI admin.
    error_log( 'M2M keyfile invalide : ' . $e->getMessage() ) ;
}
catch( GuzzleException $e )
{
    // Réseau / DNS / 5xx / 403 / … . Réessayer plus tard.
    error_log( 'Échec HTTP M2M : ' . $e->getMessage() ) ;
}
```

Voir [error-handling.md](error-handling.md) pour le catalogue complet des exceptions.

## Étape 6 — (Optionnel) Surcharger les champs à la construction

Pour des tests locaux, pointer un keyfile de staging vers une API différente :

```php
$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/etc/my-service/secrets/staging-keyfile.json' ,
    apiBaseUrl  : 'http://localhost:8000'
) ;
```

Même syntaxe pour `$issuer`, `$scope`, `$tokenPath`, et `$http` (un client Guzzle pré-configuré).

## Étape 7 — (Optionnel) Pré-chauffer le cache de token

Utile pour les smoke tests, les health checks, ou pour valider l'échange IdP indépendamment de tout appel API :

```php
$accessToken = $client->getToken() ;  // déclenche l'échange IdP (ou retourne le token caché)
```

Le token retourné peut aussi être passé à une bibliothèque HTTP tierce si vous avez besoin d'un appel ponctuel hors de `M2MApiClient`.

## Suite

- Apprenez le [schéma complet du keyfile](keyfile-format.md).
- Comprenez le [cycle de vie du token](token-lifecycle.md) (fenêtre de cache + politique de retry sur 401).
- Adaptez le client à votre IdP — voir [fournisseurs d'identité non-Zitadel](advanced/non-zitadel-idps.md).
- Ajoutez de l'observabilité — voir [injection de client HTTP](advanced/http-client-injection.md) pour les middlewares Guzzle.
