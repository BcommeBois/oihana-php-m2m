# Fournisseurs d'identité non-Zitadel

`M2MApiClient` adopte par défaut les conventions de [Zitadel](https://zitadel.com), mais le flux sous-jacent ([RFC 7521](https://datatracker.ietf.org/doc/html/rfc7521) + [RFC 7523](https://datatracker.ietf.org/doc/html/rfc7523) `urn:ietf:params:oauth:client-assertion-type:jwt-bearer`) est un standard. La plupart des fournisseurs OAuth2 / OIDC modernes le supportent, avec deux ajustements au maximum :

1. Le **path du token endpoint** (`tokenPath`).
2. Le **scope** demandé au token endpoint (`scope`).

Les deux sont configurables via les arguments constructeur — pas besoin de fork.

## Matrice des fournisseurs

| Fournisseur        | `tokenPath`                                       | `scope`                                                | Champ identifiant            |
|--------------------|---------------------------------------------------|--------------------------------------------------------|------------------------------|
| Zitadel (défaut)   | `/oauth/v2/token`                                 | `openid` + `urn:zitadel:iam:org:project:id:<id>:aud`   | `userId` (Service User)      |
| Auth0              | `/oauth/token`                                    | `openid profile` + l'audience de votre API             | `clientId` (application M2M) |
| Keycloak           | `/realms/{realm}/protocol/openid-connect/token`   | `openid` + scopes personnalisés                        | `clientId`                   |
| Générique RFC 7523 | selon la documentation du fournisseur             | selon la documentation du fournisseur                  | `clientId` ou `userId`       |

## Zitadel (défaut)

Aucune configuration nécessaire. Le keyfile généré par Zitadel pour un Service User est auto-suffisant.

```php
use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile( '/secrets/zitadel-keyfile.json' ) ;
```

## Auth0

Auth0 supporte le grant jwt-bearer pour les applications Machine-to-Machine. Le token endpoint est `/oauth/token` (sans `v2`).

```php
use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/auth0-keyfile.json' ,
    tokenPath   : '/oauth/token' ,
    scope       : 'openid profile read:widgets write:widgets'  // les permissions de votre API
) ;
```

Pour le keyfile JSON, structurez-le manuellement car Auth0 n'émet pas le même format :

```json
{
    "type":        "auth0-m2m",
    "keyId":       "your-auth0-key-id",
    "key":         "-----BEGIN RSA PRIVATE KEY-----\n…\n-----END RSA PRIVATE KEY-----\n",
    "clientId":    "your-auth0-application-clientId",
    "issuer":      "https://your-tenant.eu.auth0.com",
    "audience":    "https://api.example.com",
    "scope":       "openid profile read:widgets",
    "apiBaseUrl":  "https://api.example.com"
}
```

> Auth0 exige que vous enregistriez la clé de signature de l'application dans le dashboard Auth0 (Applications → Settings → Advanced → Endpoints). Le `keyId` que vous fournissez doit correspondre à celui enregistré là-bas.

## Keycloak

Keycloak organise les endpoints OAuth par realm. Le token endpoint est `/realms/{realm}/protocol/openid-connect/token`.

```php
use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/keycloak-keyfile.json' ,
    tokenPath   : '/realms/my-realm/protocol/openid-connect/token'
) ;
```

Format du keyfile :

```json
{
    "type":        "keycloak-m2m",
    "keyId":       "your-keycloak-key-id",
    "key":         "-----BEGIN RSA PRIVATE KEY-----\n…\n-----END RSA PRIVATE KEY-----\n",
    "clientId":    "your-keycloak-clientId",
    "issuer":      "https://keycloak.example.com",
    "audience":    "your-keycloak-clientId",
    "scope":       "openid profile",
    "apiBaseUrl":  "https://api.example.com"
}
```

Configurez le client Keycloak pour l'authentification **Signed JWT** et téléversez la clé publique dans l'onglet Credentials du client.

## Fournisseur RFC 7523 générique

Tout fournisseur OAuth2 qui supporte `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer` fonctionne de la même façon. Lisez la documentation de votre IdP pour trouver :

- Le path du token endpoint.
- Si l'IdP attend `iss` et `sub` égaux au `clientId` (application enregistrée) ou à un identifiant utilisateur (compte de service).
- La syntaxe de scope requise (certains exigent un paramètre `audience` explicite, que Zitadel encode via le scope `urn:zitadel:iam:org:project:id:<id>:aud`).

## Ce qui N'EST PAS configurable (pour l'instant)

La release v1.0 actuelle hardcode :

- Le grant type (`urn:ietf:params:oauth:grant-type:jwt-bearer`).
- L'algorithme de signature (`RS256`).
- Le format de requête du token endpoint (corps form-encoded avec `grant_type` + `scope` + `assertion`).

Si vous avez besoin d'un grant type différent (`client_credentials` avec `client_assertion`), d'un algorithme de signature différent (`RS512`, `ES256`), ou d'un format de requête différent, **héritez de `M2MApiClient` et surchargez `getToken()`** — voir [extending-the-client.md](extending-the-client.md).

Les contributions pour rendre cela configurable en v2.0 sont les bienvenues — ouvrez une issue sur [github.com/BcommeBois/oihana-php-m2m/issues](https://github.com/BcommeBois/oihana-php-m2m/issues).
