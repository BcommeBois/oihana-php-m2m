# Oihana PHP - M2M

![Oihana PHP M2M](https://raw.githubusercontent.com/BcommeBois/oihana-php-m2m/main/assets/images/oihana-php-m2m-logo-inline-512x160.png)

> ## ⚠️ Strongly discouraged — legacy compatibility branch
>
> **You are looking at the `compat/php-7.4` branch.** Its only reason to exist
> is to unblock services pinned to a PHP version that has been **end-of-life
> since 2022-11-28** and no longer receives security patches from the PHP team.
>
> Using this branch in production is **highly discouraged** :
>
> - PHP 7.4 itself is unmaintained — any CVE in the runtime stays unpatched.
> - This branch only receives **security fixes for the M2M client itself** ;
>   no new features, no upstream IdP integrations, no performance work.
> - It ships an inlined RS256 signer instead of the audited `firebase/php-jwt`
>   (because the only 7.4-compatible 6.x line is flagged by an open advisory).
>   The inlined code is minimal and tested, but it is one less audited
>   surface than `main`.
>
> **Migrate to [`main`](https://github.com/BcommeBois/oihana-php-m2m/tree/main)
> (PHP 8.4+) as soon as your host operator allows it.** The public API of
> `M2MApiClient` is identical, so the upgrade is `composer require
> oihana/php-m2m:^1.0` and a PHP runtime bump — no code changes needed in
> your application.

Lightweight, OIDC-compliant **Machine-to-Machine (M2M) HTTP client** for APIs protected by JWT.

Implements the OAuth 2.0 `urn:ietf:params:oauth:client-assertion-type:jwt-bearer` flow ([RFC 7521](https://datatracker.ietf.org/doc/html/rfc7521) + [RFC 7523](https://datatracker.ietf.org/doc/html/rfc7523)) so a service account can sign a short-lived JWT assertion with its own RSA key, exchange it at the identity provider's token endpoint for an access token, and call a protected API with `Authorization: Bearer …`.

Designed for [Zitadel](https://zitadel.com) out of the box, compatible with any RFC 7523-compliant identity provider (Auth0, Keycloak, …) via a configurable token endpoint path.

[![Latest Version](https://img.shields.io/packagist/v/oihana/php-m2m.svg?style=flat-square)](https://packagist.org/packages/oihana/php-m2m)
[![Total Downloads](https://img.shields.io/packagist/dt/oihana/php-m2m.svg?style=flat-square)](https://packagist.org/packages/oihana/php-m2m)
[![License](https://img.shields.io/packagist/l/oihana/php-m2m.svg?style=flat-square)](LICENSE)

## ✨ Features

- **Two-line setup** from a self-sufficient keyfile JSON — no global config, no service container required.
- **Proactive token caching** — one IdP exchange per ~59 minutes (configurable safety margin).
- **Reactive 401 retry** — handles clock drift, premature revocation, and key rotation with one transparent re-exchange.
- **Strongly typed errors** — `KeyfileInvalidException` distinguishes a dead keyfile from a transient network glitch.
- **IdP-agnostic** — defaults to Zitadel's `/oauth/v2/token`, override `$tokenPath` for Auth0, Keycloak, …
- **Subclass-friendly** — `call()`, `doRequest()`, `decodeResponse()` are `protected` extension hooks for instrumentation, correlation IDs, custom envelopes.
- **Zero hidden state** — pass your own Guzzle client to inject middlewares (logging, retry-on-5xx, telemetry).

## 📦 Installation

> **PHP 7.4 compatibility branch** — this is the `compat/php-7.4` branch. It is
> feature-equivalent to `1.0.0` on `main`, with downgraded language features
> and a self-contained dependency surface (no `oihana/php-*` runtime deps).
>
> For the canonical PHP 8.4+ release, see the [`main`](https://github.com/BcommeBois/oihana-php-m2m/tree/main) branch.

Install via [Composer](https://getcomposer.org):

```shell
composer require oihana/php-m2m:dev-compat/php-7.4
```

## 🚀 Quick Start

### 1. Obtain a keyfile

A keyfile is a JSON document handed to you **once** by the API administrator at service creation (or key rotation). It is **never** persisted server-side — store it securely on the calling service's host (file system, secret manager, …).

A keyfile is **auto-sufficient**: it carries both the cryptographic material (the RSA private key + key id) and the connection metadata (issuer URL, API base URL, OAuth2 scope) so a third-party developer can connect without any further configuration.

```json
{
    "type":        "serviceaccount",
    "keyId":       "303384786189271040",
    "key":         "-----BEGIN RSA PRIVATE KEY-----\n…\n-----END RSA PRIVATE KEY-----\n",
    "userId":      "303384547813785600",
    "issuer":      "https://my-org.zitadel.cloud",
    "audience":    "303380000000000000",
    "scope":       "openid profile urn:zitadel:iam:org:project:id:303380000000000000:aud",
    "apiBaseUrl":  "https://api.example.com"
}
```

See [documentation/en/keyfile-format.md](documentation/en/keyfile-format.md) for the full field reference.

### 2. Call the API

```php
use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile( '/secrets/m2m-keyfile.json' ) ;

$widgets = $client->get   ( '/widgets' ) ;
$created = $client->post  ( '/widgets'      , [ 'name' => 'Foo' ] ) ;
$updated = $client->patch ( '/widgets/42'   , [ 'name' => 'Bar' ] ) ;
$client->delete( '/widgets/42' ) ;
```

That's it. Token acquisition, caching, refresh, and 401 retry are handled internally.

### 3. Override fields when needed

A typical use case is pointing a staging keyfile at a local API for end-to-end testing:

```php
$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/staging-keyfile.json' ,
    apiBaseUrl  : 'http://localhost:8000'         // override apiBaseUrl
) ;
```

Same for `$issuer`, `$scope`, `$tokenPath`, and the underlying Guzzle `$http` client.

## 🌐 Identity provider compatibility

The token endpoint path defaults to `/oauth/v2/token` (Zitadel convention). Override it for other providers:

| Identity provider | `$tokenPath`                                                        |
|-------------------|---------------------------------------------------------------------|
| Zitadel (default) | `/oauth/v2/token`                                                   |
| Auth0             | `/oauth/token`                                                      |
| Keycloak          | `/realms/{realm}/protocol/openid-connect/token`                     |
| Generic RFC 7523  | whatever your IdP exposes                                           |

```php
$client = new M2MApiClient
(
    keyfile   : $keyfile ,
    tokenPath : '/oauth/token'  // Auth0
) ;
```

## 🔁 Token lifecycle

- **Proactive refresh** — the access token is cached in memory with its `expires_in` and reused for every call until 60 seconds before its expiration (`REFRESH_SAFETY_MARGIN`). No exchange happens for ~59 minutes on a typical IdP default.
- **Reactive retry on 401** — if the resource API rejects the cached Bearer (clock drift, key rotation, IdP hiccup), the client invalidates its cache, performs **one** fresh exchange, and replays the request once. If the retry still fails with 401, a `KeyfileInvalidException` is thrown.
- **Other failures bubble up** — only HTTP 401 triggers the refresh-and-retry. Network errors, 5xx, 403 (denied) bubble up as-is so they are never masked by a useless re-exchange.

See [documentation/en/token-lifecycle.md](documentation/en/token-lifecycle.md) for the full sequence diagram.

## ⚠️ Error handling

```php
use oihana\m2m\M2MApiClient;
use oihana\m2m\exceptions\KeyfileInvalidException;
use GuzzleHttp\Exception\GuzzleException;

try
{
    $data = $client->get( '/widgets' ) ;
}
catch( KeyfileInvalidException $e )
{
    // Keyfile is no longer accepted by the IdP or the API.
    // Action : re-download a fresh keyfile from the admin UI.
}
catch( GuzzleException $e )
{
    // Network / non-401 failure (timeout, DNS, 5xx, …). Retry later.
}
```

See [documentation/en/error-handling.md](documentation/en/error-handling.md).

## 🧩 Extending the client

`call()`, `doRequest()`, and `decodeResponse()` are `protected` extension hooks. Subclass `M2MApiClient` to add per-request instrumentation, correlation IDs, tenant headers, or to support non-JSON envelopes — without rewriting the public verb methods.

```php
class TracingM2MClient extends M2MApiClient
{
    protected function doRequest( string $method , string $path , ?array $body , string $token ) :\GuzzleHttp\Psr7\Response
    {
        $started = microtime( true ) ;
        $response = parent::doRequest( $method , $path , $body , $token ) ;
        $this->logger->info( "M2M $method $path" , [ 'ms' => ( microtime( true ) - $started ) * 1000 ] ) ;
        return $response ;
    }
}
```

See [documentation/en/advanced/extending-the-client.md](documentation/en/advanced/extending-the-client.md).

## 📚 Documentation

Full documentation is available in two languages :

- 🇬🇧 [English](documentation/en/README.md)
- 🇫🇷 [Français](documentation/fr/README.md)

Topics covered:

- Getting started — install + first call in under 2 minutes.
- Keyfile format — full field reference, security considerations.
- Token lifecycle — caching, proactive refresh, reactive retry on 401.
- Error handling — exception catalogue + recommended recovery actions.
- Tips & best practices — string constants inlined under `oihana\m2m\enums`, `oihana\m2m\files` and `oihana\m2m\schema` to avoid magic strings.
- Advanced — HTTP client injection, subclassing for instrumentation, non-Zitadel IdPs.

## 🧪 Running the tests

```shell
composer install
composer test
```

The unit suite covers the constructor contract, the `fromKeyfile` factory, and the override semantics (including the configurable `tokenPath`). The full token-exchange path is integration-level and exercised end-to-end against a real identity provider.

## 🪪 License

This project is licensed under the [Mozilla Public License 2.0 (MPL-2.0)](LICENSE).

## 👤 Author

**Marc Alcaraz** ([@BcommeBois](https://github.com/BcommeBois)) — Project Founder, Lead Developer
- 🌐 Website : [ooop.fr](https://www.ooop.fr)
- 📧 Email : marc@ooop.fr

## 🔗 Related libraries

> Not pulled in by this branch (kept dependency-free for PHP 7.4 hosts), but
> recommended as drop-in replacements once you can target PHP 8.4+ :
>
> - [oihana/php-enums](https://github.com/BcommeBois/oihana-php-enums) — strongly-typed PHP constant enumerations (HTTP, JWT, OAuth, …)
> - [oihana/php-schema](https://github.com/BcommeBois/oihana-php-schema) — Schema.org-aligned data structures, including the `Keyfile` schema
> - [oihana/php-files](https://github.com/BcommeBois/oihana-php-files) — file system helpers and MIME type enums
