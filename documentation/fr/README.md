# Oihana PHP M2M — Documentation (Français)

> Client HTTP machine-à-machine (M2M) léger et conforme OIDC, pour APIs protégées par JWT.

## Sommaire

### Démarrage

- [Démarrage rapide](getting-started.md) — installation + premier appel en moins de 2 minutes.
- [Format du keyfile](keyfile-format.md) — référence complète des champs et considérations de sécurité.

### Comportement à l'exécution

- [Cycle de vie du token](token-lifecycle.md) — cache, rafraîchissement proactif, retry réactif sur 401.
- [Gestion des erreurs](error-handling.md) — catalogue des exceptions et actions de récupération recommandées.

### Avancé

- [Étendre le client](advanced/extending-the-client.md) — héritage pour instrumentation, en-têtes personnalisés, enveloppes non-JSON.
- [Injection de client HTTP](advanced/http-client-injection.md) — middlewares Guzzle (logs, retry-on-5xx, télémétrie).
- [Fournisseurs d'identité non-Zitadel](advanced/non-zitadel-idps.md) — adapter `tokenPath` et `scope` pour Auth0, Keycloak, …

## Autres langues

- 🇬🇧 [English documentation](../en/README.md)
