# HTTP client injection

`M2MApiClient` accepts a pre-configured Guzzle `Client` via the `$http` constructor argument. This is the cleanest way to inject **cross-cutting HTTP concerns** without subclassing — logging, retry-on-5xx, telemetry, custom TLS settings, proxy configuration, …

> 💡 The examples below use typed constants from `oihana/php-enums` (already a required dependency) instead of magic strings — see [Tips & best practices](../tips.md) for the rationale and the full constant catalogue.

## Default Guzzle client

When `$http` is omitted, the constructor builds a default Guzzle client with sensible defaults (shown here with the typed constants for documentation purposes — the library itself currently uses the raw strings internally) :

```php
use oihana\enums\http\GuzzleOption;

new \GuzzleHttp\Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,   // do not throw on 4xx/5xx — let M2MApiClient inspect the status
        GuzzleOption::VERIFY      => true ,    // verify TLS certificates
        GuzzleOption::TIMEOUT     => 15 ,      // 15-second hard timeout per request
    ]
) ;
```

These defaults are intentional :

- **`http_errors => false`** is **mandatory** — the 401 retry policy in `M2MApiClient::call()` inspects the response status. With Guzzle's default behaviour (throwing on 4xx/5xx), the policy would never see the 401.
- **`verify => true`** matches Guzzle's default; mentioned here to make the security posture explicit.
- **`timeout => 15`** prevents indefinite hangs. Tune to match your API's worst-case latency.

## Inject a custom Guzzle client

```php
use oihana\m2m\M2MApiClient;
use oihana\enums\http\GuzzleOption;
use oihana\enums\http\HttpHeader;
use GuzzleHttp\Client;

$http = new Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,    // ⚠️ keep this
        GuzzleOption::VERIFY      => true ,
        GuzzleOption::TIMEOUT     => 30 ,
        GuzzleOption::PROXY       => 'http://corporate-proxy:8080' ,
        GuzzleOption::HEADERS     =>
        [
            HttpHeader::USER_AGENT => 'my-service/1.0 (+https://my-service.example.com)' ,
        ] ,
    ]
) ;

$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/m2m-keyfile.json' ,
    http        : $http
) ;
```

## Add a logging middleware

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger( 'm2m' ) ;
$logger->pushHandler( new StreamHandler( '/var/log/m2m.log' , Logger::INFO ) ) ;

use oihana\enums\http\GuzzleOption;

$stack = HandlerStack::create() ;
$stack->push
(
    Middleware::log
    (
        $logger ,
        new MessageFormatter( '{method} {uri} → {code} ({res_header_content-length} bytes)' )
    )
) ;

$http = new Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,
        GuzzleOption::VERIFY      => true ,
        GuzzleOption::TIMEOUT     => 15 ,
        GuzzleOption::HANDLER     => $stack ,
    ]
) ;

$client = M2MApiClient::fromKeyfile( '/secrets/m2m-keyfile.json' , http : $http ) ;
```

Every token exchange and every API call now lands in `/var/log/m2m.log`.

## Add a retry-on-5xx middleware

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use oihana\enums\http\GuzzleOption;
use oihana\enums\http\HttpStatusCode;
use Psr\Http\Message\RequestInterface;

$stack = HandlerStack::create() ;
$stack->push
(
    Middleware::retry
    (
        function( int $retries , RequestInterface $request , ?Response $response = null , ?\Throwable $exception = null ) :bool
        {
            if( $retries >= 3 ) return false ;
            if( $response !== null && $response->getStatusCode() >= HttpStatusCode::INTERNAL_SERVER_ERROR ) return true ;
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
        GuzzleOption::HTTP_ERRORS => false ,
        GuzzleOption::VERIFY      => true ,
        GuzzleOption::TIMEOUT     => 15 ,
        GuzzleOption::HANDLER     => $stack ,
    ]
) ;

$client = M2MApiClient::fromKeyfile( '/secrets/m2m-keyfile.json' , http : $http ) ;
```

Now 5xx and connection errors are retried with exponential backoff. The `M2MApiClient`'s 401 retry policy is layered on top — different concern, different retry budget.

## Add OpenTelemetry instrumentation

For end-to-end traces, push the OpenTelemetry Guzzle middleware (or any equivalent for your tracing library) onto the stack — every M2M request will then propagate the active trace context and emit a span.

The exact wiring depends on your OpenTelemetry SDK setup, but the pattern is the same as the logging / retry middlewares above : build the handler stack, push the middleware, pass `[ 'handler' => $stack ]` in the Guzzle constructor, inject the client into `M2MApiClient`.

## Why inject vs subclass ?

| Concern                                | Inject Guzzle middleware | Subclass `M2MApiClient` |
|----------------------------------------|--------------------------|-------------------------|
| Logging, telemetry, retry-on-5xx       | ✅                        | ❌ (overkill)            |
| TLS config, proxy, user-agent          | ✅                        | ❌ (overkill)            |
| Per-call correlation IDs               | ✅ (via middleware)       | ✅ (via `doRequest`)     |
| Tenant header / multi-tenancy          | Both work                | ✅ (cleaner if stateful) |
| Bypass / amend the 401 retry policy    | ❌ (HTTP-only concern)    | ✅                       |
| Throw-on-non-2xx semantics             | Partial                  | ✅                       |
| Custom token cache (e.g. Redis)        | ❌                        | ✅                       |

Rule of thumb : if the concern stays at the HTTP layer, inject a middleware. If it touches the M2MApiClient lifecycle, subclass.
