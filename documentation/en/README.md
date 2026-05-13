# Oihana PHP M2M — Documentation (English)

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
