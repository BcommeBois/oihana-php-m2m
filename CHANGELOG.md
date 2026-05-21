# The "Oihana PHP M2M" library - Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [1.0.0.74] - 2026-05-21 — `compat/php-7.4` branch

PHP 7.4 compatibility branch — feature-equivalent to `1.0.0` on `main`, but with
downgraded language features and a self-contained dependency surface so it can
run on legacy hosts that cannot upgrade to PHP 8.4.

### Changed

- Minimum PHP version : `>=8.4` → `>=7.4`.
- `guzzlehttp/guzzle` pinned to `^7.5` (first 7.x line shipping a PHP 7.2.5+
  minimum). Same API.
- `phpunit/phpunit` pinned to `^9.6`, `nunomaduro/collision` to `^5.11`.
- Typed class constants (`const string`, `const int`) replaced with untyped
  constants in `M2MApiClient` and tests.
- `str_starts_with()` (PHP 8.0+) replaced with `strncmp(...) === 0`.
- Named arguments (`JWT::encode(payload: …)`, `new KeyfileInvalidException(…, previous: …)`,
  `fromKeyfile(keyfilePath: …)`, …) rewritten as positional.
- `#[CoversClass]` PHPUnit attribute replaced with `@covers` docblock.
- `:mixed` return type (PHP 8.0+) removed from the test helper.

### Removed

- Dependency on `firebase/php-jwt` — the 7.x line requires PHP 8.0+, and the 6.x
  line is flagged by a low-severity, disputed advisory (CVE-2025-45769) that
  Composer audit refuses to install. The RS256 signing path used by this
  client (`openssl_sign` + base64url, see `signAssertion()`) is now inlined,
  ~25 lines of `ext-openssl` calls — sufficient for our single use case
  (signing a short-lived JWT bearer assertion with the keyfile's RSA private
  key). Requires `ext-openssl` (core PHP).
- Dependencies on `oihana/php-enums`, `oihana/php-files`, `oihana/php-schema`.
  The handful of string constants actually used are now inlined under
  `oihana\m2m\enums\`, `oihana\m2m\files\` and `oihana\m2m\schema\`.
- `oihana\enums\http\GuzzleOption` usage — replaced by Guzzle's own
  `GuzzleHttp\RequestOptions::HEADERS` (already used for `FORM_PARAMS` / `JSON`).
- `phpdocumentor/shim` dev dependency (PHP 8.1+ only) and the `composer doc`
  script.

### Added

- `oihana\m2m\enums\{AuthScheme, HttpHeader, HttpMethod}` — minimal const-only
  classes covering the values used by the client (`Bearer`, `Authorization`,
  `Accept`, `GET`/`POST`/`PUT`/`PATCH`/`DELETE`).
- `oihana\m2m\files\FileMimeType` — single `JSON` constant.
- `oihana\m2m\schema\{Keyfile, JwtClaim, JWTAlgorithm, TokenRequestField,
  TokenRequestValue, TokenResponseField}` — string-constant classes mirroring
  the keys read from the keyfile and the OAuth2 token endpoint payload.

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
