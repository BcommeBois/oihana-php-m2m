# Gestion des erreurs

`M2MApiClient` lève trois catégories d'exceptions, chacune avec une signification distincte et une action de récupération recommandée.

## Catalogue des exceptions

| Exception                                     | Quand elle est levée                                                                                                                  | Action recommandée                                                                              |
|-----------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------|
| `RuntimeException`                            | Le keyfile est malformé à la construction (manque `key`, `keyId`, ou les deux `userId`/`clientId` ; `issuer` / `apiBaseUrl` non résolvable). | Corriger le JSON du keyfile ou fournir le champ manquant via une surcharge constructeur. Levée une fois à la construction ; n'atteint jamais le runtime. |
| `KeyfileInvalidException`                     | L'IdP a refusé l'assertion JWT bearer au token endpoint, OU l'API ressource a retourné 401 deux fois de suite avec deux tokens neufs distincts. | Re-télécharger un keyfile neuf depuis l'UI admin. Le service a peut-être été roté, désactivé ou supprimé côté serveur. |
| `GuzzleHttp\Exception\GuzzleException`        | Échec HTTP / réseau sous-jacent (DNS, timeout, handshake TLS, …).                                                                     | Réessayer avec backoff exponentiel. Si persistant, vérifier la connectivité réseau et la santé de l'API ressource. |

## Pattern d'appel défensif

```php
use oihana\m2m\M2MApiClient;
use oihana\m2m\exceptions\KeyfileInvalidException;
use GuzzleHttp\Exception\GuzzleException;

try
{
    $client = M2MApiClient::fromKeyfile( '/secrets/m2m-keyfile.json' ) ;
    $widgets = $client->get( '/widgets' ) ;
}
catch( KeyfileInvalidException $e )
{
    // Action opérateur requise. Page on-call.
    $logger->critical( 'M2M keyfile invalide' , [ 'error' => $e->getMessage() ] ) ;
    throw $e ;
}
catch( GuzzleException $e )
{
    // Échec transitoire. Réessayer avec backoff.
    $logger->warning( 'Échec HTTP M2M' , [ 'error' => $e->getMessage() ] ) ;
    // … programmer un retry …
}
catch( \RuntimeException $e )
{
    // Erreur de configuration. Corriger le keyfile ou les arguments constructeur.
    $logger->critical( 'Mauvaise configuration M2M' , [ 'error' => $e->getMessage() ] ) ;
    throw $e ;
}
```

## Ce qui N'EST PAS une exception

`M2MApiClient` ne lève **pas** d'exception sur les réponses HTTP 2xx, 3xx, 4xx (autres que 401), ou 5xx. L'enveloppe JSON décodée est retournée et l'appelant est responsable d'inspecter le statut — par exemple pour réessayer sur 5xx ou pour faire remonter un 403 comme erreur d'autorisation.

Si vous avez besoin d'un comportement « exception sur erreur », héritez de `M2MApiClient` et surchargez `decodeResponse()` pour lever sur non-2xx — voir [advanced/extending-the-client.md](advanced/extending-the-client.md).

## Pourquoi deux 401 consécutifs ?

La politique de retry sur 401 distingue un **token périmé** (cache miss après une révocation côté serveur) d'un **keyfile mort** (l'IdP n'accepte plus les assertions signées avec cette clé).

- **Un 401 → invalidation du cache + échange neuf + replay.** Le cas le plus fréquent est un token expiré plus vite que la marge de sécurité ne l'avait prédit (dérive d'horloge, révocation côté IdP, …). Un échange neuf réussit presque toujours.
- **Deux 401 d'affilée avec deux tokens neufs distincts → `KeyfileInvalidException`.** L'IdP a émis un token tout neuf et l'API ressource l'a quand même rejeté. Le keyfile lui-même est le problème — action opérateur requise.

Cela évite à la fois les nouveaux échanges inutiles (quand l'échec n'est pas lié à l'auth) et les boucles silencieuses sur token (quand le keyfile est mort).

## Mapper les réponses d'erreur

Si votre API retourne des enveloppes d'erreur structurées (par ex. JSON-API, RFC 7807), inspectez le tableau retourné directement :

```php
$response = $client->get( '/widgets/42' ) ;

if( isset( $response[ 'error' ] ) )
{
    // Erreur niveau application : 4xx avec un corps JSON.
    throw new MyDomainException( $response[ 'error' ][ 'message' ] ?? 'Erreur inconnue' ) ;
}
```

Pour un dispatch basé sur le code de statut, héritez de `M2MApiClient` et surchargez `decodeResponse()` (ou `call()`) pour capturer le statut de la réponse avant le décodage — voir le guide [extending-the-client](advanced/extending-the-client.md).
