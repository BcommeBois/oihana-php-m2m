# Error handling

`M2MApiClient` raises three categories of exceptions, each with a distinct meaning and a recommended recovery action.

## Exception catalogue

| Exception                                     | When it is raised                                                                                                | Recommended action                                                       |
|-----------------------------------------------|------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------|
| `RuntimeException`                            | The keyfile is malformed at construction (missing `key`, `keyId`, or both `userId`/`clientId`; unresolvable `issuer` / `apiBaseUrl`). | Fix the keyfile JSON or supply the missing field via constructor override. Throws once at construction; never reaches runtime. |
| `KeyfileInvalidException`                     | The IdP refused the JWT bearer assertion at the token endpoint, OR the resource API returned 401 twice in a row with two distinct fresh tokens. | Re-download a fresh keyfile from the admin UI. The service may have been rotated, deactivated, or deleted server-side. |
| `GuzzleHttp\Exception\GuzzleException`        | Underlying HTTP / network failure (DNS, timeout, TLS handshake, …).                                              | Retry with exponential backoff. If persistent, check network connectivity and the resource API's health. |

## Defensive call pattern

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
    // Operator action required. Page on-call.
    $logger->critical( 'M2M keyfile invalid' , [ 'error' => $e->getMessage() ] ) ;
    throw $e ;
}
catch( GuzzleException $e )
{
    // Transient failure. Retry with backoff.
    $logger->warning( 'M2M HTTP failure' , [ 'error' => $e->getMessage() ] ) ;
    // … schedule retry …
}
catch( \RuntimeException $e )
{
    // Configuration error. Fix the keyfile or the constructor arguments.
    $logger->critical( 'M2M misconfiguration' , [ 'error' => $e->getMessage() ] ) ;
    throw $e ;
}
```

## What is NOT an exception

`M2MApiClient` does **not** throw on HTTP 2xx, 3xx, 4xx (other than 401), or 5xx responses. The decoded JSON envelope is returned and the caller is responsible for inspecting the status — for instance to retry on 5xx or to surface a 403 as an authorisation error.

If you need an exception-on-error behaviour, subclass `M2MApiClient` and override `decodeResponse()` to throw on non-2xx — see [advanced/extending-the-client.md](advanced/extending-the-client.md).

## Why two consecutive 401 ?

The 401 retry policy distinguishes a **stale token** (cache miss after a server-side revocation) from a **dead keyfile** (the IdP no longer accepts assertions signed with this key).

- **One 401 → cache invalidation + fresh exchange + replay.** The most common case is a token that expired faster than the safety margin predicted (clock drift, IdP-side revocation, …). A fresh exchange almost always succeeds.
- **Two 401 in a row with two distinct fresh tokens → `KeyfileInvalidException`.** The IdP issued a brand-new token and the resource API still rejected it. The keyfile itself is the problem — operator action is required.

This avoids both useless re-exchanges (when the failure isn't auth-related) and silent token-loops (when the keyfile is dead).

## Mapping error responses

If your API returns structured error envelopes (e.g. JSON-API, RFC 7807), inspect the returned array directly:

```php
$response = $client->get( '/widgets/42' ) ;

if( isset( $response[ 'error' ] ) )
{
    // Application-level error : 4xx with a JSON body.
    throw new MyDomainException( $response[ 'error' ][ 'message' ] ?? 'Unknown error' ) ;
}
```

For status-code-based dispatching, subclass `M2MApiClient` and override `decodeResponse()` (or `call()`) to capture the response status before decoding — see the [extending-the-client](advanced/extending-the-client.md) guide.
