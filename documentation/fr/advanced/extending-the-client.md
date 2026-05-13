# Étendre le client

`M2MApiClient` expose trois méthodes `protected` comme **hooks d'extension** — héritez et surchargez-les pour ajouter de l'instrumentation par requête, des en-têtes personnalisés, ou la gestion d'enveloppes non-JSON sans réécrire les méthodes verbe publiques.

> 💡 Les exemples ci-dessous utilisent les constantes typées de `oihana/php-enums` et `oihana/php-files` (déjà dépendances requises) plutôt que des magic strings — voir [Astuces & bonnes pratiques](../tips.md) pour le rationnel et le catalogue complet des constantes.

## Hooks d'extension

| Méthode                                                                                            | Rôle                                                                                                |
|----------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| `call( string $method , string $path , ?array $body = null ) :array`                               | Instrumentation par requête, amendements à la politique de retry, identifiants de corrélation.       |
| `doRequest( string $method , string $path , ?array $body , string $token ) :Response`              | Injecter des en-têtes additionnels (tenant, corrélation, observabilité), modifier la requête sortante.|
| `decodeResponse( Response $response ) :array`                                                      | Supporter des payloads non-JSON, imposer une enveloppe typée, lever sur non-2xx, …                  |

Les trois sont documentées en PHPDoc sur la classe elle-même. Appelez toujours `parent::xxx()` sauf si vous voulez intentionnellement contourner le comportement parent (par ex. pour sauter la politique de retry sur 401 dans `call()`).

## Exemple 1 — timing de requête + identifiants de corrélation

```php
use oihana\m2m\M2MApiClient;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

class TracingM2MClient extends M2MApiClient
{
    public function __construct
    (
        array                     $keyfile ,
        private LoggerInterface   $logger  ,
        ?string                   $issuer     = null ,
        ?string                   $apiBaseUrl = null ,
        ?\GuzzleHttp\Client       $http       = null ,
        ?string                   $scope      = null ,
        ?string                   $tokenPath  = null
    )
    {
        parent::__construct( $keyfile , $issuer , $apiBaseUrl , $http , $scope , $tokenPath ) ;
    }

    protected function doRequest( string $method , string $path , ?array $body , string $token ) :Response
    {
        $correlationId = bin2hex( random_bytes( 8 ) ) ;
        $started       = microtime( true ) ;

        try
        {
            $response = parent::doRequest( $method , $path , $body , $token ) ;

            $this->logger->info
            (
                'Requête M2M' ,
                [
                    'method'         => $method ,
                    'path'           => $path ,
                    'status'         => $response->getStatusCode() ,
                    'correlation_id' => $correlationId ,
                    'duration_ms'    => ( microtime( true ) - $started ) * 1000 ,
                ]
            ) ;

            return $response ;
        }
        catch( \Throwable $e )
        {
            $this->logger->error
            (
                'Échec requête M2M' ,
                [
                    'method'         => $method ,
                    'path'           => $path ,
                    'correlation_id' => $correlationId ,
                    'error'          => $e->getMessage() ,
                ]
            ) ;
            throw $e ;
        }
    }
}
```

## Exemple 2 — injecter un en-tête tenant à chaque appel

```php
use oihana\m2m\M2MApiClient;
use oihana\enums\http\AuthScheme;
use oihana\enums\http\GuzzleOption;
use oihana\enums\http\HttpHeader;
use oihana\files\enums\FileMimeType;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;

class TenantedM2MClient extends M2MApiClient
{
    public function __construct
    (
        array   $keyfile  ,
        private string $tenantId ,
        ?string $issuer     = null ,
        ?string $apiBaseUrl = null ,
        ?\GuzzleHttp\Client $http = null ,
        ?string $scope      = null ,
        ?string $tokenPath  = null
    )
    {
        parent::__construct( $keyfile , $issuer , $apiBaseUrl , $http , $scope , $tokenPath ) ;
    }

    protected function doRequest( string $method , string $path , ?array $body , string $token ) :Response
    {
        // Ajouter l'en-tête tenant en plus des en-têtes parent.
        // Approche la plus simple : refaire la requête nous-mêmes avec les en-têtes fusionnés.
        $options =
        [
            GuzzleOption::HEADERS =>
            [
                HttpHeader::AUTHORIZATION => AuthScheme::prefix( AuthScheme::BEARER ) . $token ,
                HttpHeader::ACCEPT        => FileMimeType::JSON ,
                'X-Tenant-Id'             => $this->tenantId ,    // en-tête spécifique à l'application — pas de constante
            ] ,
        ] ;

        if( $body !== null )
        {
            $options[ RequestOptions::JSON ] = $body ;
        }

        /** @var Response $response */
        $response = $this->getHttp()->request( $method , $this->getApiBaseUrl() . $path , $options ) ;

        return $response ;
    }

    // Ajoutez des accesseurs si les champs privés sont requis.
    // Alternative : déclarez-les protected dans une variante sub-class-friendly de la lib.
}
```

> **Note sur les champs privés** : les `$http` et `$apiBaseUrl` parent sont `private`. Si vous avez besoin d'un accès direct, soit vous injectez votre propre client Guzzle à la construction (voir [http-client-injection.md](http-client-injection.md)), soit vous forkez et élevez ces champs en `protected` pour une variante custom.

> **Note sur les constantes** : `GuzzleOption::HEADERS`, `HttpHeader::AUTHORIZATION`, `AuthScheme::prefix(AuthScheme::BEARER)`, `FileMimeType::JSON` viennent de `oihana/php-enums` et `oihana/php-files` (déjà requis par `oihana/php-m2m`). Voir [Astuces & bonnes pratiques](../tips.md) pour le catalogue complet.

## Exemple 3 — enveloppe typée avec sémantique « lever sur erreur »

```php
use oihana\m2m\M2MApiClient;
use oihana\enums\http\HttpStatusCode;
use oihana\enums\Output;
use GuzzleHttp\Psr7\Response;

class StrictM2MClient extends M2MApiClient
{
    protected function decodeResponse( Response $response ) :array
    {
        $status = $response->getStatusCode() ;

        if( HttpStatusCode::getType( $status ) === Output::ERROR )
        {
            throw new \RuntimeException
            (
                sprintf
                (
                    'L\'API M2M a retourné %d %s : %s' ,
                    $status ,
                    HttpStatusCode::getDescription( $status ) ?? 'Inconnu' ,
                    substr( (string) $response->getBody() , 0 , 400 )
                )
            ) ;
        }

        return parent::decodeResponse( $response ) ;
    }
}
```

> `HttpStatusCode::getType()` retourne `Output::SUCCESS / REDIRECT / ERROR / INFO` selon la plage du code de statut — plus besoin de retenir si les erreurs 5xx commencent à 500 ou à 600. Voir [Astuces & bonnes pratiques](../tips.md#recette-4--inspection-typée-du-code-de-statut).

> ⚠️ Attention : si vous surchargez `decodeResponse()` pour lever sur `>= 400`, la politique de retry sur 401 dans `call()` ne se déclenchera plus — `call()` vérifie le statut de la réponse **avant** de déléguer à `decodeResponse()`, mais une exception levée remonte quoi qu'il arrive. Pour préserver le retry-on-401, surchargez aussi `call()` et intégrez-y la logique « lever sur erreur ».

## Exemple 4 — contourner la politique de retry sur 401

Pour un endpoint où vous préféreriez voir le 401 remonter brut (par ex. un health check explicite `/auth/whoami`) :

```php
class NoRetryM2MClient extends M2MApiClient
{
    protected function call( string $method , string $path , ?array $body = null ) :array
    {
        // Sauter la branche retry-on-401 du parent.
        $response = $this->doRequest( $method , $path , $body , $this->getToken() ) ;
        return $this->decodeResponse( $response ) ;
    }
}
```

## Quand NE PAS hériter

Pour la majorité des cas, préférez :

- Les **middlewares Guzzle** pour les préoccupations HTTP transversales (logs, retry-on-5xx, télémétrie) — voir [http-client-injection.md](http-client-injection.md).
- Les **surcharges constructeur** pour les ajustements par environnement (autre `apiBaseUrl`, autre `tokenPath`, autre `scope`).

N'héritez que lorsque vous avez besoin d'un comportement qui dépend du cycle de vie de `M2MApiClient` (cache de token, politique de retry, décodage de réponse) — c'est-à-dire un comportement que les middlewares HTTP purs ne peuvent pas exprimer.
