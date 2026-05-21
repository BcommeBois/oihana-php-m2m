# Oihana PHP M2M — Documentation (Français)

> ⚠️ **Vous lisez la documentation de la branche legacy `compat/php-7.4`.**
> L'utilisation de cette branche est **fortement déconseillée** — PHP 7.4 est en
> fin de vie depuis le 2022-11-28 et ne reçoit plus de correctifs de sécurité.
> Migrez vers [`main`](https://github.com/BcommeBois/oihana-php-m2m/tree/main)
> (PHP 8.4+) dès que votre hébergeur le permet. L'API publique de
> `M2MApiClient` est strictement identique : la migration se résume à un
> `composer require` et à un bump du runtime, sans aucun changement de code
> côté application. Certains extraits ci-dessous évoquent `oihana/php-enums` /
> `php-files` / `php-schema` comme bonne pratique ; ces paquets **ne sont pas**
> installés par cette branche (leurs constantes sont inlinées localement sous
> `oihana\m2m\{enums,files,schema}`) et ne redeviennent pertinents qu'après
> migration.

> Client HTTP machine-à-machine (M2M) léger et conforme OIDC, pour APIs protégées par JWT.

## Sommaire

### Démarrage

- [Démarrage rapide](getting-started.md) — installation + premier appel en moins de 2 minutes.
- [Format du keyfile](keyfile-format.md) — référence complète des champs et considérations de sécurité.

### Comportement à l'exécution

- [Cycle de vie du token](token-lifecycle.md) — cache, rafraîchissement proactif, retry réactif sur 401.
- [Gestion des erreurs](error-handling.md) — catalogue des exceptions et actions de récupération recommandées.

### Bonnes pratiques

- [Astuces & bonnes pratiques](tips.md) — constantes typées de `oihana/php-enums` + `oihana/php-files` pour éviter les magic strings.

### Avancé

- [Étendre le client](advanced/extending-the-client.md) — héritage pour instrumentation, en-têtes personnalisés, enveloppes non-JSON.
- [Injection de client HTTP](advanced/http-client-injection.md) — middlewares Guzzle (logs, retry-on-5xx, télémétrie).
- [Fournisseurs d'identité non-Zitadel](advanced/non-zitadel-idps.md) — adapter `tokenPath` et `scope` pour Auth0, Keycloak, …

## Autres langues

- 🇬🇧 [English documentation](../en/README.md)
