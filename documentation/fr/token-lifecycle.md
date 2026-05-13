# Cycle de vie du token

`M2MApiClient` gère l'acquisition, la mise en cache, le rafraîchissement et le retry sur 401 de façon transparente. Cette page documente la séquence exacte pour que vous puissiez débugger les cas limites ou bâtir des attentes en termes de capacité / observabilité.

## Séquence bout-en-bout

```
Code consommateur            M2MApiClient                  Fournisseur d'identité      API ressource
     │                            │                               │                          │
     │  ── get('/widgets') ────▶  │                               │                          │
     │                            │ ── getToken() ──┐             │                          │
     │                            │                 │             │                          │
     │                            │ cache miss ?    │             │                          │
     │                            │                 ▼             │                          │
     │                            │  signe l'assertion JWT bearer │                          │
     │                            │ ── POST {issuer}{tokenPath} ▶                            │
     │                            │                              │                           │
     │                            │ ◀───────── 200 + access_token + expires_in ───────────── │
     │                            │ cache token + cachedExpiresAt                            │
     │                            │ ── doRequest(GET /widgets, Bearer ──────────────────▶    │
     │                            │                                                          │
     │                            │ ◀────────── 200 + corps JSON ──────────────────────────  │
     │  ◀──── tableau décodé ──── │                                                          │
```

## Rafraîchissement proactif

Le token caché est réutilisé pour chaque appel jusqu'à **`REFRESH_SAFETY_MARGIN` secondes avant son expiration dure** (défaut : 60 s).

Concrètement, si l'IdP retourne `expires_in: 3600`, le cache est valide pendant ~3540 secondes (59 minutes), puis le prochain appel déclenche un nouvel échange.

```php
// Premier appel : cache miss → échange IdP + appel API
$client->get( '/widgets' ) ;

// Appels suivants dans la fenêtre de cache : appel API uniquement
$client->get( '/widgets' ) ;
$client->get( '/widgets' ) ;
// …

// Juste avant expiration : cache miss → échange IdP + appel API
$client->get( '/widgets' ) ;
```

La marge de sécurité de 60 secondes protège contre les dérives d'horloge entre l'hôte consommateur et l'IdP — si vos horloges système sont étroitement synchronisées (NTP), vous pouvez la réduire ; si elles dérivent fortement, augmentez-la.

## Retry réactif sur 401

Même avec le rafraîchissement proactif, un token caché peut être rejeté par l'API ressource pour plusieurs raisons :

- **Rotation de clé côté serveur** — l'administrateur a effectué une rotation de la clé M2M et la précédente est désormais invalide.
- **Désactivation / suppression du service** — le service M2M a été désactivé.
- **Révocation prématurée** — le token a été explicitement révoqué côté IdP (par ex. réponse à une compromission).
- **Dérive d'horloge** — la validation `iat`/`exp` côté API ressource est plus stricte que la marge de sécurité côté consommateur.
- **Hoquet IdP** — l'IdP a émis un token mais la réplication de son session-store est en retard.

Quand l'API ressource retourne **HTTP 401**, `M2MApiClient` :

1. Invalide le token caché (`cachedToken = null`, `cachedExpiresAt = 0`).
2. Appelle à nouveau `getToken()` — ce qui effectue **un** échange neuf contre l'IdP.
3. Rejoue la requête originale avec le Bearer fraîchement obtenu.

Si la deuxième tentative retourne aussi **HTTP 401**, le keyfile lui-même n'est plus accepté. Une `KeyfileInvalidException` est levée pour que l'opérateur réagisse (re-télécharger un keyfile neuf depuis l'UI admin).

## Ce qui NE déclenche PAS de rafraîchissement

Seul HTTP 401 déclenche le refresh-and-retry. Les cas suivants remontent tels quels :

| Résultat              | Comportement                                                                                  |
|-----------------------|-----------------------------------------------------------------------------------------------|
| HTTP 2xx              | Enveloppe JSON décodée retournée à l'appelant.                                                |
| HTTP 3xx              | Remonte sous forme de réponse Guzzle (le client ne suit pas les redirections automatiquement).|
| HTTP 4xx (hors 401)   | Enveloppe JSON décodée retournée. L'appelant est responsable d'inspecter le statut.            |
| HTTP 5xx              | Enveloppe JSON décodée retournée. L'appelant est responsable de réessayer avec backoff.        |
| Erreur réseau         | `GuzzleException` levée (timeout, échec DNS, échec handshake TLS, …).                          |
| IdP rejette à l'échange | `KeyfileInvalidException` levée immédiatement (pas de retry — un nouvel échange échouerait également). |

Cette séparation est intentionnelle : un refresh-and-retry sur 5xx ou 403 masquerait l'échec réel et gaspillerait un aller-retour IdP.

## Hooks d'observabilité

Pour journaliser chaque échange de token (ou chaque appel API), injectez un client Guzzle avec un middleware de logging et utilisez-le pour les deux — voir [advanced/http-client-injection.md](advanced/http-client-injection.md).

Pour le timing par requête, héritez de `M2MApiClient` et surchargez `doRequest()` — voir [advanced/extending-the-client.md](advanced/extending-the-client.md).

## Constantes liées

| Constante                                  | Défaut               | Rôle                                                                          |
|--------------------------------------------|----------------------|-------------------------------------------------------------------------------|
| `M2MApiClient::ASSERTION_TTL_SECONDS`      | `60`                 | Durée de vie de l'assertion JWT bearer envoyée au token endpoint.             |
| `M2MApiClient::DEFAULT_TOKEN_TTL`          | `3600`               | TTL de repli quand l'IdP omet `expires_in` dans sa réponse.                   |
| `M2MApiClient::REFRESH_SAFETY_MARGIN`      | `60`                 | Rafraîchir le token caché ce nombre de secondes avant son expiration dure.    |
| `M2MApiClient::DEFAULT_TOKEN_PATH`         | `/oauth/v2/token`    | Path par défaut du token endpoint sur l'hôte issuer (convention Zitadel).     |
