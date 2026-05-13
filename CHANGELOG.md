# The "Oihana PHP M2M" library - Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-05-13

### Added

- `oihana\m2m\M2MApiClient` — OIDC-compliant M2M HTTP client for APIs protected by JWT (RFC 7521 + RFC 7523 jwt-bearer flow).
  - Auto-sufficient keyfile resolution (`issuer`, `apiBaseUrl`, `scope` carried by the JSON, all overridable via constructor).
  - `fromKeyfile()` factory to load the keyfile JSON from disk in one line.
  - Verb helpers: `get()`, `post()`, `patch()`, `put()`, `delete()` returning the decoded JSON envelope.
  - Public `getToken()` to pre-warm the cache or feed the raw token to a third-party library.
  - Proactive in-memory token cache with `REFRESH_SAFETY_MARGIN` (60 s) before hard expiration.
  - Reactive one-shot retry on 401 with cache invalidation, then `KeyfileInvalidException` if the second token is also rejected.
  - Configurable token endpoint path via the `$tokenPath` constructor argument (defaults to `/oauth/v2/token`, Zitadel convention) — supports Auth0, Keycloak, and any RFC 7523-compliant IdP.
  - Extension hooks (`call`, `decodeResponse`, `doRequest`) marked `protected` for subclassing (instrumentation, custom headers, non-JSON envelopes).
- `oihana\m2m\exceptions\KeyfileInvalidException` — raised when the IdP refuses the JWT bearer assertion or when two consecutive 401 prove the keyfile is no longer accepted.
- Unit test suite for constructor contract, `fromKeyfile` factory, override semantics, and `tokenPath` configurability.
