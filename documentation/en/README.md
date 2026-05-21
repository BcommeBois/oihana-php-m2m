# Oihana PHP M2M — Documentation (English)

> ⚠️ **You are reading the documentation of the `compat/php-7.4` legacy branch.**
> Using this branch is **strongly discouraged** — PHP 7.4 has been end-of-life since
> 2022-11-28 and no longer receives security patches. Migrate to
> [`main`](https://github.com/BcommeBois/oihana-php-m2m/tree/main) (PHP 8.4+) as
> soon as your host operator allows it. The public API of `M2MApiClient` is
> identical, so the upgrade is a `composer require` and a runtime bump — no
> code changes needed in your application. Some snippets below reference
> `oihana/php-enums` / `php-files` / `php-schema` as a best practice ; those
> packages are **not** installed by this branch (their string constants are
> inlined locally under `oihana\m2m\{enums,files,schema}`) and only become
> relevant once you migrate.

> Lightweight, OIDC-compliant Machine-to-Machine HTTP client for APIs protected by JWT.

## Index

### Getting started

- [Getting started](getting-started.md) — install + first call in under 2 minutes.
- [Keyfile format](keyfile-format.md) — full field reference, security considerations.

### Runtime behaviour

- [Token lifecycle](token-lifecycle.md) — caching, proactive refresh, reactive retry on 401.
- [Error handling](error-handling.md) — exception catalogue + recommended recovery actions.

### Best practices

- [Tips & best practices](tips.md) — typed constants from `oihana/php-enums` + `oihana/php-files` to avoid magic strings.

### Advanced

- [Extending the client](advanced/extending-the-client.md) — subclassing for instrumentation, custom headers, non-JSON envelopes.
- [HTTP client injection](advanced/http-client-injection.md) — inject Guzzle middlewares (logging, retry-on-5xx, telemetry).
- [Non-Zitadel identity providers](advanced/non-zitadel-idps.md) — adapt `tokenPath` and scope for Auth0, Keycloak, …

## Other languages

- 🇫🇷 [Documentation française](../fr/README.md)
