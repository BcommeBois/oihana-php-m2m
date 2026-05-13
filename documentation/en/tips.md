# Tips & best practices

`oihana/php-m2m` already depends on [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) and [`oihana/php-files`](https://github.com/BcommeBois/oihana-php-files), which expose **typed constants** for every HTTP / Guzzle / MIME magic string used in this library.

Use them in your own subclasses, custom Guzzle configurations, and middlewares to keep your code self-documenting, type-safe, and refactor-friendly.

## Why avoid magic strings ?

```php
// ❌ magic strings — no autocomplete, no typo detection, no rename safety
$http = new Client
(
    [
        'http_errors' => false ,
        'verify'      => true ,
        'timeout'     => 15 ,
        'headers'     => [ 'Authorization' => 'Bearer ' . $token ] ,
    ]
) ;

// ✅ typed constants — IDE autocomplete, refactor-safe, self-documenting
use oihana\enums\http\GuzzleOption;
use oihana\enums\http\HttpHeader;
use oihana\enums\http\AuthScheme;

$http = new Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,
        GuzzleOption::VERIFY      => true ,
        GuzzleOption::TIMEOUT     => 15 ,
        GuzzleOption::HEADERS     =>
        [
            HttpHeader::AUTHORIZATION => AuthScheme::prefix( AuthScheme::BEARER ) . $token ,
        ] ,
    ]
) ;
```

A typo in `'http_errors'` silently breaks the 401 retry policy. A typo in `GuzzleOption::HTTP_ERRORS` is caught at parse time.

## The constants you'll use most

| Concern                        | Constant                                                  | Replaces                                                                         |
|--------------------------------|-----------------------------------------------------------|----------------------------------------------------------------------------------|
| Guzzle option keys             | `oihana\enums\http\GuzzleOption::*`                       | `'http_errors'`, `'verify'`, `'timeout'`, `'headers'`, `'handler'`, `'proxy'`, … |
| HTTP header names              | `oihana\enums\http\HttpHeader::*`                         | `'Authorization'`, `'Accept'`, `'Content-Type'`, `'X-Request-Id'`, …             |
| HTTP methods                   | `oihana\enums\http\HttpMethod::*`                         | `'GET'`, `'POST'`, `'PATCH'`, `'PUT'`, `'DELETE'`                                |
| HTTP status codes              | `oihana\enums\http\HttpStatusCode::*` + helpers           | `200`, `401`, `500`, `429` magic numbers ; `getDescription()` / `getType()` / `fromException()` helpers |
| Auth scheme prefixes           | `oihana\enums\http\AuthScheme::prefix(AuthScheme::*)`     | `'Bearer '`, `'Basic '`, `'Digest '`, `'OAuth '`                                 |
| OAuth2 / OIDC request fields   | `oihana\enums\oauth2\OAuth2Parameter::*`                  | `'grant_type'`, `'scope'`, `'assertion'`, `'client_id'`, `'code_verifier'`, …    |
| OAuth2 / OIDC token response   | `oihana\enums\oauth2\OAuth2TokenField::*`                 | `'access_token'`, `'expires_in'`, `'refresh_token'`, `'id_token'`, …             |
| JWT registered claim names     | `xyz\oihana\schema\constants\JwtClaim::*`                 | `'iss'`, `'sub'`, `'aud'`, `'iat'`, `'exp'`, `'jti'`, `'nbf'`                    |
| JWT signing algorithms         | `xyz\oihana\schema\constants\JWTAlgorithm::*`             | `'RS256'`, `'HS256'`, `'PS512'`, … (+ `isSymmetric()` / `isAsymmetric()` helpers)|
| MIME types                     | `oihana\files\enums\FileMimeType::*`                      | `'application/json'`, `'text/html'`, `'application/xml'`, …                      |

Reference :
- [oihana/php-enums — GuzzleOption](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/http/GuzzleOption.php)
- [oihana/php-enums — HttpHeader](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/http/HttpHeader.php)
- [oihana/php-enums — HttpMethod](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/http/HttpMethod.php)
- [oihana/php-enums — HttpStatusCode](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/http/HttpStatusCode.php)
- [oihana/php-enums — AuthScheme](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/http/AuthScheme.php)
- [oihana/php-enums — OAuth2Parameter](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/oauth2/OAuth2Parameter.php)
- [oihana/php-enums — OAuth2TokenField](https://github.com/BcommeBois/oihana-php-enums/blob/main/src/oihana/enums/oauth2/OAuth2TokenField.php)
- [oihana/php-schema — JwtClaim](https://github.com/BcommeBois/oihana-php-schema/blob/main/src/xyz/oihana/schema/constants/JwtClaim.php)
- [oihana/php-schema — JWTAlgorithm](https://github.com/BcommeBois/oihana-php-schema/blob/main/src/xyz/oihana/schema/constants/JWTAlgorithm.php)
- [oihana/php-files — FileMimeType](https://github.com/BcommeBois/oihana-php-files/blob/main/src/oihana/files/enums/FileMimeType.php)

> **Note on overlap** — `oihana\enums\oauth2\OAuth2Parameter` and `xyz\oihana\schema\constants\auth\TokenRequestField` cover the same OAuth2 wire-format keys. Same for `OAuth2TokenField` ↔ `TokenResponseField`. `M2MApiClient` uses the schema variants internally for historical reasons ; in your own code prefer `oihana\enums\oauth2\*` (it's the canonical home for OAuth2/OIDC enums going forward).

## Recipe 1 — typed Guzzle client

```php
use oihana\m2m\M2MApiClient;
use oihana\enums\http\GuzzleOption;
use oihana\enums\http\HttpHeader;
use GuzzleHttp\Client;

$http = new Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,    // ⚠️ keep this — required by the 401 retry policy
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

## Recipe 2 — typed subclass with custom headers

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
        array          $keyfile  ,
        private string $tenantId ,
        ?string        $issuer     = null ,
        ?string        $apiBaseUrl = null ,
        ?\GuzzleHttp\Client $http  = null ,
        ?string        $scope      = null ,
        ?string        $tokenPath  = null
    )
    {
        parent::__construct( $keyfile , $issuer , $apiBaseUrl , $http , $scope , $tokenPath ) ;
    }

    protected function doRequest( string $method , string $path , ?array $body , string $token ) :Response
    {
        $options =
        [
            GuzzleOption::HEADERS =>
            [
                HttpHeader::AUTHORIZATION => AuthScheme::prefix( AuthScheme::BEARER ) . $token ,
                HttpHeader::ACCEPT        => FileMimeType::JSON ,
                HttpHeader::X_REQUEST_ID  => bin2hex( random_bytes( 8 ) ) ,
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
}
```

> Note : keep `RequestOptions::JSON` for the body — it comes from Guzzle itself and is the canonical reference for that key. `GuzzleOption::JSON` from `oihana/php-enums` is also valid, but mixing both is fine since they map to the same `'json'` string.

## Recipe 3 — typed retry middleware

```php
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
        fn( int $retries ) :int => 1000 * ( 2 ** $retries )
    )
) ;

$http = new \GuzzleHttp\Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,
        GuzzleOption::VERIFY      => true ,
        GuzzleOption::TIMEOUT     => 15 ,
        GuzzleOption::HANDLER     => $stack ,
    ]
) ;
```

## Recipe 4 — typed status-code inspection

`HttpStatusCode` is the right tool for any decision based on a response's status. Avoid magic numbers like `>= 500` or `=== 401` scattered through your codebase.

```php
use oihana\enums\http\HttpStatusCode;
use oihana\enums\Output;
use GuzzleHttp\Psr7\Response;

class StatusAwareM2MClient extends M2MApiClient
{
    protected function decodeResponse( Response $response ) :array
    {
        $status = $response->getStatusCode() ;
        $type   = HttpStatusCode::getType( $status ) ; // Output::SUCCESS / REDIRECT / ERROR / INFO

        if( $type === Output::ERROR )
        {
            throw new \RuntimeException
            (
                sprintf
                (
                    'API returned %d %s — %s' ,
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

`HttpStatusCode::fromException()` is also handy when re-emitting an HTTP response from a thrown exception — see the helper's PHPDoc for the convention used by `oihana/php-exceptions` Error4xx / Error5xx classes.

## Recipe 5 — typed OAuth2 / JWT for custom token decoders

When subclassing `M2MApiClient::getToken()` (e.g. to support a different grant type, signing algorithm, or response schema), drop the magic strings and use the typed OAuth2 + JWT enums.

```php
use oihana\m2m\M2MApiClient;
use oihana\m2m\exceptions\KeyfileInvalidException;
use oihana\enums\http\GuzzleOption;
use oihana\enums\oauth2\OAuth2Parameter;
use oihana\enums\oauth2\OAuth2TokenField;
use xyz\oihana\schema\constants\JwtClaim;
use xyz\oihana\schema\constants\JWTAlgorithm;
use xyz\oihana\schema\auth\Keyfile;
use Firebase\JWT\JWT;

class CustomTokenM2MClient extends M2MApiClient
{
    public function getToken() :string
    {
        // Custom assertion — for instance using PS512 instead of RS256.
        $now = time() ;
        $assertion = JWT::encode
        (
            payload :
            [
                JwtClaim::ISSUER     => $this->keyfile[ Keyfile::USER_ID ] ,
                JwtClaim::SUBJECT    => $this->keyfile[ Keyfile::USER_ID ] ,
                JwtClaim::AUDIENCE   => $this->getIssuer() ,
                JwtClaim::ISSUED_AT  => $now ,
                JwtClaim::EXPIRES_AT => $now + 60 ,
                JwtClaim::JWT_ID     => bin2hex( random_bytes( 16 ) ) ,  // anti-replay
            ] ,
            key   : $this->keyfile[ Keyfile::KEY ] ,
            alg   : JWTAlgorithm::PS512 ,                                 // stronger than RS256
            keyId : $this->keyfile[ Keyfile::KEY_ID ]
        ) ;

        // Typed OAuth2 form params instead of magic strings.
        $response = $this->getHttp()->post
        (
            $this->getIssuer() . $this->getTokenPath() ,
            [
                GuzzleOption::FORM_PARAMS =>
                [
                    OAuth2Parameter::GRANT_TYPE => 'urn:ietf:params:oauth:grant-type:jwt-bearer' ,
                    OAuth2Parameter::SCOPE      => $this->getScope() ,
                    OAuth2Parameter::ASSERTION  => $assertion ,
                ] ,
            ]
        ) ;

        $payload = json_decode( (string) $response->getBody() , true ) ?: [] ;

        if( empty( $payload[ OAuth2TokenField::ACCESS_TOKEN ] ) )
        {
            throw new KeyfileInvalidException( 'IdP returned no access_token in body.' ) ;
        }

        // Cache + return — same protocol as the parent.
        $this->cacheToken
        (
            $payload[ OAuth2TokenField::ACCESS_TOKEN ] ,
            (int) ( $payload[ OAuth2TokenField::EXPIRES_IN ] ?? 3600 )
        ) ;

        return $payload[ OAuth2TokenField::ACCESS_TOKEN ] ;
    }
}
```

> The protected accessors used above (`getHttp()`, `getIssuer()`, `getTokenPath()`, `getScope()`, `cacheToken()`) are illustrative — `M2MApiClient` keeps these fields private in v1.0. Either expose them in your own subclass-friendly fork, or replicate the assignment logic directly. See [advanced/extending-the-client.md](advanced/extending-the-client.md).

## Typed Schema.org payloads with `oihana/php-schema`

`oihana/php-schema` exposes Schema.org-aligned typed classes for the auth domain. The `Keyfile` schema (`xyz\oihana\schema\auth\Keyfile`) extends `org\schema\Thing` — meaning every keyfile is by construction a JSON-LD-serialisable, IDE-typed object you can hydrate, validate, and emit without ever touching string keys.

```php
use xyz\oihana\schema\auth\Keyfile;

// Hydrate from JSON
$decoded = json_decode( file_get_contents( '/secrets/m2m-keyfile.json' ) , true ) ;
$keyfile = new Keyfile( $decoded ) ;

// Typed access — IDE autocomplete on every field
echo $keyfile->userId ;
echo $keyfile->apiBaseUrl ;
echo $keyfile->scope ;

// Pass to M2MApiClient (constructor accepts an associative array, so cast back)
use oihana\m2m\M2MApiClient;
$client = new M2MApiClient( (array) $keyfile ) ;

// Re-serialise as Schema.org-flavoured JSON-LD
echo json_encode( $keyfile , JSON_PRETTY_PRINT ) ;
// {
//   "@context": "https://schema.org",
//   "@type":    "Keyfile",
//   "userId":   "...",
//   ...
// }
```

### Why this matters

- **IDE autocomplete + static analysis** — typos on `$keyfile->userId` are caught at parse time, unlike `$keyfile[ 'userId' ]`.
- **Schema.org compliance** — the JSON output carries `@context` and `@type`, ready to be consumed by any JSON-LD client (search engines, knowledge graphs, federation layers).
- **Validation hooks** — typed nullable properties make missing-field detection trivial (`if( $keyfile->userId === null )`).
- **Constants for keys** — when you must use the array form (e.g. constructor-side), `Keyfile::USER_ID`, `Keyfile::API_BASE_URL`, … are still there as typed string constants.

### Other auth schemas in `oihana/php-schema`

The same pattern applies to the rest of the auth domain — explore [`xyz\oihana\schema\auth\*`](https://github.com/BcommeBois/oihana-php-schema/tree/main/src/xyz/oihana/schema/auth) for typed classes covering OAuth2 tokens, OIDC discovery documents, JWKS, …

## When to NOT use the constants

- **Application-specific headers** (e.g. `X-Tenant-Id`, `X-Internal-Trace`) that are unique to your platform — keep them as raw strings (or define your own `MyAppHeader::` enum class).
- **Third-party API quirks** with non-standard header names — same logic.
- **Inline one-off scripts** where adding `use` statements adds more noise than the constants save.

For everything HTTP-standard, prefer the typed constants — your future self (and your IDE) will thank you.

## Going further

- [oihana/php-enums README](https://github.com/BcommeBois/oihana-php-enums) — the full catalogue of HTTP / OAuth / JWT / Schema.org / common enums.
- [oihana/php-schema README](https://github.com/BcommeBois/oihana-php-schema) — typed Schema.org-aligned data classes (Keyfile, OAuth2 tokens, JWKS, …) + JWT/OAuth constants.
- [oihana/php-files README](https://github.com/BcommeBois/oihana-php-files) — file system helpers and MIME types.
