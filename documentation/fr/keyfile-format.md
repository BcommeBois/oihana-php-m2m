# Format du keyfile

Le **keyfile** est un document JSON qui authentifie un compte de service M2M (machine-à-machine) auprès d'un fournisseur d'identité OAuth2 / OIDC. Il est généré **une seule fois** par l'administrateur de l'API à la création du service (ou à la rotation de clé), remis au consommateur M2M, et **jamais** persisté côté serveur.

Le keyfile est **auto-suffisant** : il porte à la fois la matière cryptographique (clé privée RSA + identifiant de clé) et les métadonnées de connexion (URL de l'émetteur, URL de base de l'API, scope OAuth2) afin qu'un développeur tiers puisse se connecter à l'API sans configuration supplémentaire.

## Schéma JSON

```json
{
    "type":        "serviceaccount",
    "keyId":       "303384786189271040",
    "key":         "-----BEGIN RSA PRIVATE KEY-----\n…\n-----END RSA PRIVATE KEY-----\n",
    "userId":      "303384547813785600",
    "clientId":    null,
    "issuer":      "https://my-org.zitadel.cloud",
    "audience":    "303380000000000000",
    "scope":       "openid profile urn:zitadel:iam:org:project:id:303380000000000000:aud",
    "apiBaseUrl":  "https://api.example.com"
}
```

## Référence des champs

| Champ        | Obligatoire | Origine            | Description                                                                                                                 |
|--------------|-------------|--------------------|-----------------------------------------------------------------------------------------------------------------------------|
| `key`        | ✅           | IdP                | Clé privée RSA au format PEM. Sert à signer l'assertion JWT bearer.                                                         |
| `keyId`      | ✅           | IdP                | Identifie cette clé spécifique côté IdP. Devient le `kid` de l'en-tête JWT.                                                 |
| `userId`     | ⚠️\*        | IdP                | Identifiant utilisateur IdP (= claim `sub` du access token résultant). Flux moderne service-account.                        |
| `clientId`   | ⚠️\*        | IdP                | clientId OAuth2 d'une application enregistrée. Flux legacy API-app. Utilisé comme `iss` et `sub` de l'assertion JWT bearer. |
| `type`       | ⚪           | IdP                | Champ informationnel optionnel (par ex. `serviceaccount`).                                                                  |
| `issuer`     | ✅           | API (auto-injecté) | URL de l'émetteur IdP (par ex. `https://my-org.zitadel.cloud`). Le token endpoint est dérivé comme `{issuer}{tokenPath}`.   |
| `apiBaseUrl` | ✅           | API (auto-injecté) | URL de base de l'API ressource. Concaténé avec le path de la requête à chaque appel.                                        |
| `scope`      | ⚪           | API (auto-injecté) | Scope OAuth2 demandé au token endpoint. Retombe sur `openid` si omis.                                                       |
| `audience`   | ⚪           | API (auto-injecté) | Audience attendue dans le access token (typiquement l'identifiant projet IdP). Informationnel côté client.                  |

\* **Au moins un de `userId` ou `clientId`** doit être présent. `userId` est l'identifiant moderne Zitadel Service User ; `clientId` est l'identifiant legacy d'une application OAuth enregistrée.

## Surcharger à l'exécution

Chacun de `issuer`, `apiBaseUrl`, `scope`, et le `tokenPath` (séparé) peut être surchargé via le constructeur sans modifier le keyfile JSON sur disque — utile pour les tests locaux :

```php
use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/staging-keyfile.json' ,
    apiBaseUrl  : 'http://localhost:8000'         // surcharge apiBaseUrl
) ;
```

## Validation

Le constructeur applique les invariants suivants et lève une `RuntimeException` sinon :

- `key` doit être présent et non vide.
- `keyId` doit être présent et non vide.
- Au moins un de `userId` ou `clientId` doit être présent et non vide.
- `issuer` doit être résolvable (depuis le keyfile ou depuis la surcharge constructeur).
- `apiBaseUrl` doit être résolvable (depuis le keyfile ou depuis la surcharge constructeur).

Les slashes finaux sur `issuer` et `apiBaseUrl` sont automatiquement supprimés afin que la concaténation ne produise jamais de double slash.

## Considérations de sécurité

- **La clé est sensible.** Traitez le keyfile avec le même soin qu'une clé SSH privée ou qu'un mot de passe de base de données.
- **Ne le committez jamais en versionnage.** Ajoutez `*-keyfile.json` (ou la convention de nommage de secrets de votre projet) au `.gitignore`.
- **Restreignez les permissions de fichier.** `chmod 600` (Unix) ou ACL équivalente sous Windows.
- **Utilisez un gestionnaire de secrets en production.** Kubernetes `Secret`, AWS Secrets Manager, HashiCorp Vault, …
- **Effectuez des rotations périodiques.** L'API expose un endpoint de rotation de clé — effectuez la rotation et remplacez le keyfile côté consommateur. La clé précédente est invalidée immédiatement à la rotation.
- **Détectez les compromissions via le journal d'audit IdP.** Chaque échange de token est tracé côté IdP ; des patterns `iat` inhabituels ou des IPs source inattendues indiquent un keyfile fuité.

## Cycle de vie

1. **Création du service** — l'administrateur de l'API crée un service M2M. L'API retourne le keyfile JSON exactement une fois ; si vous ne le sauvegardez pas, il faudra une rotation de clé.
2. **Distribution** — remettez le keyfile au consommateur M2M par un canal sécurisé (message chiffré, gestionnaire de secrets, …).
3. **Utilisation** — le consommateur le charge via `M2MApiClient::fromKeyfile()` et commence à appeler l'API.
4. **Rotation** — l'administrateur déclenche une rotation de clé. Un nouveau keyfile est retourné ; le précédent cesse de fonctionner immédiatement.
5. **Désactivation / suppression** — l'administrateur désactive ou supprime le service. Le prochain appel à `getToken()` échoue avec `KeyfileInvalidException`.
