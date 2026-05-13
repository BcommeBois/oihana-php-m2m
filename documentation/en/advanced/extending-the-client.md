# Extending the client

`M2MApiClient` exposes three `protected` methods as **extension hooks** — subclass and override them to add per-request instrumentation, custom headers, or non-JSON envelope handling without rewriting the public verb methods.

> 💡 The examples below use typed constants from `oihana/php-enums` and `oihana/php-files` (already required dependencies) instead of magic strings — see [Tips & best practices](../tips.md) for the rationale and the full constant catalogue.

## Extension hooks

| Method                                                                                            | Purpose                                                                                              |
|---------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------|
| `call( string $method , string $path , ?array $body = null ) :array`                              | Per-request instrumentation, retry policy amendments, correlation IDs.                               |
| `doRequest( string $method , string $path , ?array $body , string $token ) :Response`             | Inject extra headers (tenant, correlation, observability), modify the request before it goes out.    |
| `decodeResponse( Response $response ) :array`                                                     | Support non-JSON payloads, enforce a typed envelope, throw on non-2xx, …                             |

All three are documented in PHPDoc on the class itself. Always call `parent::xxx()` unless you intentionally want to bypass the parent behaviour (e.g. to skip the 401 retry policy in `call()`).

## Example 1 — request timing + correlation IDs

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
                'M2M request' ,
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
                'M2M request failed' ,
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

## Example 2 — inject a tenant header on every call

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
        // Add the tenant header on top of the parent's headers.
        // Easiest way : do the request ourselves with the merged headers.
        $options =
        [
            GuzzleOption::HEADERS =>
            [
                HttpHeader::AUTHORIZATION => AuthScheme::prefix( AuthScheme::BEARER ) . $token ,
                HttpHeader::ACCEPT        => FileMimeType::JSON ,
                'X-Tenant-Id'             => $this->tenantId ,    // application-specific header — no constant
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

    // Add accessor methods if the private fields are needed.
    // Alternative : declare them protected in your subclass-friendly fork.
}
```

> **Note on private fields** : the parent's `$http` and `$apiBaseUrl` are `private`. If you need direct access, either inject your own Guzzle client at construction (see [http-client-injection.md](http-client-injection.md)) or fork and elevate those fields to `protected` for a custom-built variant.

> **Note on constants** : `GuzzleOption::HEADERS`, `HttpHeader::AUTHORIZATION`, `AuthScheme::prefix(AuthScheme::BEARER)`, `FileMimeType::JSON` come from `oihana/php-enums` and `oihana/php-files` (already required by `oihana/php-m2m`). See [Tips & best practices](../tips.md) for the full catalogue.

## Example 3 — typed envelope with throw-on-error semantics

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
                    'M2M API returned %d %s: %s' ,
                    $status ,
                    HttpStatusCode::getDescription( $status ) ?? 'Unknown' ,
                    substr( (string) $response->getBody() , 0 , 400 )
                )
            ) ;
        }

        return parent::decodeResponse( $response ) ;
    }
}
```

> `HttpStatusCode::getType()` returns `Output::SUCCESS / REDIRECT / ERROR / INFO` based on the status code's range — no need to remember whether 5xx errors start at 500 or 600. See [Tips & best practices](../tips.md#recipe-4--typed-status-code-inspection).

> ⚠️ Beware: if you override `decodeResponse()` to throw on `>= 400`, the 401 retry policy in `call()` will no longer trigger — `call()` checks the response status **before** delegating to `decodeResponse()`, but a thrown exception bubbles up regardless. To preserve the retry-on-401, also override `call()` and integrate the throw-on-error logic there.

## Example 4 — bypass the 401 retry policy

For an endpoint where you'd rather see the 401 surfaced raw (e.g. an explicit `/auth/whoami` health check):

```php
class NoRetryM2MClient extends M2MApiClient
{
    protected function call( string $method , string $path , ?array $body = null ) :array
    {
        // Skip the parent's retry-on-401 branch.
        $response = $this->doRequest( $method , $path , $body , $this->getToken() ) ;
        return $this->decodeResponse( $response ) ;
    }
}
```

## When NOT to subclass

For most use cases, prefer:

- **Guzzle middlewares** for cross-cutting HTTP concerns (logging, retry-on-5xx, telemetry) — see [http-client-injection.md](http-client-injection.md).
- **Constructor overrides** for per-environment tweaks (different `apiBaseUrl`, different `tokenPath`, different `scope`).

Subclass only when you need behaviour that depends on the `M2MApiClient` lifecycle (token cache, retry policy, response decoding) — i.e. behaviour that pure HTTP middlewares cannot express.
