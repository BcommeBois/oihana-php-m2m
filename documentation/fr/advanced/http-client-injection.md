# Injection de client HTTP

`M2MApiClient` accepte un `Client` Guzzle pré-configuré via l'argument constructeur `$http`. C'est la façon la plus propre d'injecter des **préoccupations HTTP transversales** sans hériter — logs, retry-on-5xx, télémétrie, paramètres TLS personnalisés, configuration proxy, …

## Client Guzzle par défaut

Quand `$http` est omis, le constructeur construit un client Guzzle par défaut avec des réglages sains :

```php
new \GuzzleHttp\Client
(
    [
        'http_errors' => false ,   // ne pas lever sur 4xx/5xx — laisser M2MApiClient inspecter le statut
        'verify'      => true ,    // vérifier les certificats TLS
        'timeout'     => 15 ,      // timeout dur de 15 secondes par requête
    ]
) ;
```

Ces défauts sont intentionnels :

- **`http_errors => false`** est **obligatoire** — la politique de retry sur 401 dans `M2MApiClient::call()` inspecte le statut de la réponse. Avec le comportement Guzzle par défaut (lever sur 4xx/5xx), la politique ne verrait jamais le 401.
- **`verify => true`** correspond au défaut Guzzle ; mentionné ici pour rendre explicite la posture de sécurité.
- **`timeout => 15`** empêche les blocages indéfinis. Ajustez selon la latence pire-cas de votre API.

## Injecter un client Guzzle personnalisé

```php
use oihana\m2m\M2MApiClient;
use GuzzleHttp\Client;

$http = new Client
(
    [
        'http_errors' => false ,    // ⚠️ garder ceci
        'verify'      => true ,
        'timeout'     => 30 ,
        'proxy'       => 'http://corporate-proxy:8080' ,
        'headers'     =>
        [
            'User-Agent' => 'my-service/1.0 (+https://my-service.example.com)' ,
        ] ,
    ]
) ;

$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/m2m-keyfile.json' ,
    http        : $http
) ;
```

## Ajouter un middleware de logging

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger( 'm2m' ) ;
$logger->pushHandler( new StreamHandler( '/var/log/m2m.log' , Logger::INFO ) ) ;

$stack = HandlerStack::create() ;
$stack->push
(
    Middleware::log
    (
        $logger ,
        new MessageFormatter( '{method} {uri} → {code} ({res_header_content-length} octets)' )
    )
) ;

$http = new Client
(
    [
        'http_errors' => false ,
        'verify'      => true ,
        'timeout'     => 15 ,
        'handler'     => $stack ,
    ]
) ;

$client = M2MApiClient::fromKeyfile( '/secrets/m2m-keyfile.json' , http : $http ) ;
```

Chaque échange de token et chaque appel API atterrit désormais dans `/var/log/m2m.log`.

## Ajouter un middleware retry-on-5xx

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

$stack = HandlerStack::create() ;
$stack->push
(
    Middleware::retry
    (
        function( int $retries , RequestInterface $request , ?Response $response = null , ?\Throwable $exception = null ) :bool
        {
            if( $retries >= 3 ) return false ;
            if( $response !== null && $response->getStatusCode() >= 500 ) return true ;
            if( $exception instanceof \GuzzleHttp\Exception\ConnectException ) return true ;
            return false ;
        } ,
        function( int $retries ) :int
        {
            return 1000 * ( 2 ** $retries ) ; // 1s, 2s, 4s
        }
    )
) ;

$http = new Client
(
    [
        'http_errors' => false ,
        'verify'      => true ,
        'timeout'     => 15 ,
        'handler'     => $stack ,
    ]
) ;

$client = M2MApiClient::fromKeyfile( '/secrets/m2m-keyfile.json' , http : $http ) ;
```

Les 5xx et les erreurs de connexion sont désormais réessayés avec backoff exponentiel. La politique de retry sur 401 de `M2MApiClient` se superpose — préoccupation différente, budget de retry différent.

## Ajouter de l'instrumentation OpenTelemetry

Pour des traces bout-en-bout, poussez le middleware Guzzle OpenTelemetry (ou n'importe quel équivalent pour votre bibliothèque de tracing) sur la stack — chaque requête M2M propagera alors le contexte de trace actif et émettra un span.

Le câblage exact dépend de la configuration de votre SDK OpenTelemetry, mais le pattern est le même que pour les middlewares logging / retry ci-dessus : construire la handler stack, pousser le middleware, passer `[ 'handler' => $stack ]` au constructeur Guzzle, injecter le client dans `M2MApiClient`.

## Pourquoi injecter vs hériter ?

| Préoccupation                          | Injecter middleware Guzzle | Hériter de `M2MApiClient` |
|----------------------------------------|----------------------------|---------------------------|
| Logs, télémétrie, retry-on-5xx         | ✅                          | ❌ (overkill)              |
| Config TLS, proxy, user-agent          | ✅                          | ❌ (overkill)              |
| ID de corrélation par appel            | ✅ (via middleware)         | ✅ (via `doRequest`)       |
| En-tête tenant / multi-tenant          | Les deux fonctionnent      | ✅ (plus propre si stateful)|
| Contourner / amender la politique de retry sur 401 | ❌ (préoccupation HTTP-only) | ✅                         |
| Sémantique « lever sur non-2xx »       | Partiel                    | ✅                         |
| Cache de token custom (par ex. Redis)  | ❌                          | ✅                         |

Règle de pouce : si la préoccupation reste à la couche HTTP, injectez un middleware. Si elle touche au cycle de vie de M2MApiClient, héritez.
