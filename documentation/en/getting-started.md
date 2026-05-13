# Getting started

This guide walks you from a fresh `composer require` to a successful authenticated API call in under 2 minutes.

## Prerequisites

- **PHP 8.4+** with the `json` extension enabled (default).
- **Composer** installed locally.
- A **keyfile JSON** for an M2M service account, obtained from the API administrator. See [keyfile-format.md](keyfile-format.md) for the schema.
- The API's **base URL**, the IdP's **issuer URL**, and the requested **OAuth2 scope**. Modern keyfiles already carry these — you only need them if you're working with a legacy keyfile or want to override at runtime.

## Step 1 — Install

```shell
composer require oihana/php-m2m
```

## Step 2 — Place the keyfile securely

Save the keyfile JSON outside your version-controlled tree, with restrictive file permissions:

```shell
mkdir -p /etc/my-service/secrets
mv ~/Downloads/m2m-keyfile.json /etc/my-service/secrets/m2m-keyfile.json
chmod 600 /etc/my-service/secrets/m2m-keyfile.json
```

In containerised deployments, mount the keyfile as a read-only secret (Kubernetes `Secret`, Docker swarm secret, AWS Secrets Manager, Vault, …) — never bake it into the image.

## Step 3 — Build the client

```php
<?php

use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile( '/etc/my-service/secrets/m2m-keyfile.json' ) ;
```

The factory reads the JSON, validates required fields (`key`, `keyId`, and one of `userId` / `clientId`), and resolves `issuer` / `apiBaseUrl` / `scope` from the keyfile contents.

## Step 4 — Call the API

```php
// GET — list resources
$widgets = $client->get( '/widgets' ) ;

// POST — create a resource
$created = $client->post( '/widgets' , [ 'name' => 'Foo' , 'price' => 9.99 ] ) ;

// PATCH — partial update
$updated = $client->patch( '/widgets/42' , [ 'price' => 12.50 ] ) ;

// PUT — full replace
$replaced = $client->put( '/widgets/42' , [ 'name' => 'Bar' , 'price' => 14.00 ] ) ;

// DELETE — remove a resource
$client->delete( '/widgets/42' ) ;
```

All verbs return the **decoded JSON envelope** as an associative `array<string, mixed>`. An empty array is returned when the body is not a JSON object (e.g. for `204 No Content` responses).

## Step 5 — Handle errors

Wrap calls in a `try / catch` to distinguish a dead keyfile from a transient network failure:

```php
use oihana\m2m\exceptions\KeyfileInvalidException;
use GuzzleHttp\Exception\GuzzleException;

try
{
    $widgets = $client->get( '/widgets' ) ;
}
catch( KeyfileInvalidException $e )
{
    // The keyfile was rejected by the IdP or the API.
    // Action : re-download a fresh keyfile from the admin UI.
    error_log( 'M2M keyfile invalid: ' . $e->getMessage() ) ;
}
catch( GuzzleException $e )
{
    // Network / DNS / 5xx / 403 / … . Retry later.
    error_log( 'M2M HTTP failure: ' . $e->getMessage() ) ;
}
```

See [error-handling.md](error-handling.md) for the full exception catalogue.

## Step 6 — (Optional) Override fields at construction

For local testing, point a staging keyfile at a different API:

```php
$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/etc/my-service/secrets/staging-keyfile.json' ,
    apiBaseUrl  : 'http://localhost:8000'
) ;
```

Same syntax for `$issuer`, `$scope`, `$tokenPath`, and `$http` (a pre-configured Guzzle client).

## Step 7 — (Optional) Pre-warm the token cache

Useful for smoke tests, health checks, or to validate the IdP exchange in isolation from any API call:

```php
$accessToken = $client->getToken() ;  // performs the token exchange (or returns the cached one)
```

The returned token can also be fed to a third-party HTTP library if you need a one-off call outside `M2MApiClient`.

## Next steps

- Learn the full [keyfile schema](keyfile-format.md).
- Understand the [token lifecycle](token-lifecycle.md) (cache window + 401 retry policy).
- Adapt the client to your IdP — see [non-Zitadel identity providers](advanced/non-zitadel-idps.md).
- Add observability — see [HTTP client injection](advanced/http-client-injection.md) for Guzzle middlewares.
