# Token lifecycle

`M2MApiClient` handles token acquisition, caching, refresh, and 401 retry transparently. This page documents the exact sequence so you can debug edge cases or build expectations for capacity / observability.

## End-to-end sequence

```
Consumer code                M2MApiClient                  Identity Provider           Resource API
     │                            │                              │                          │
     │  ── get('/widgets') ────▶ │                              │                          │
     │                            │ ── getToken() ──┐            │                          │
     │                            │                 │            │                          │
     │                            │ cache miss?     │            │                          │
     │                            │                 ▼            │                          │
     │                            │  sign JWT bearer assertion   │                          │
     │                            │ ── POST {issuer}{tokenPath} ▶                           │
     │                            │                              │                          │
     │                            │ ◀───────── 200 + access_token + expires_in ─────────────│
     │                            │ cache token + cachedExpiresAt                           │
     │                            │ ── doRequest(GET /widgets, Bearer ──────────────────▶ │
     │                            │                                                          │
     │                            │ ◀────────── 200 + JSON body ────────────────────────── │
     │  ◀──── decoded array ─────│                                                          │
```

## Proactive refresh

The cached token is reused for every call until **`REFRESH_SAFETY_MARGIN` seconds before its hard expiration** (default: 60 s).

Concretely, if the IdP returns `expires_in: 3600`, the cache is valid for ~3540 seconds (59 minutes), then the next call triggers a fresh exchange.

```php
// First call: cache miss → IdP exchange + API call
$client->get( '/widgets' ) ;

// Subsequent calls within the cache window: API call only
$client->get( '/widgets' ) ;
$client->get( '/widgets' ) ;
// …

// Just before expiration: cache miss → IdP exchange + API call
$client->get( '/widgets' ) ;
```

The 60-second safety margin protects against clock drift between the consumer host and the IdP — if your system clocks are tightly synchronised (NTP), you can reduce it; if they drift heavily, increase it.

## Reactive 401 retry

Even with proactive refresh, a cached token may be rejected by the resource API for several reasons:

- **Server-side key rotation** — the administrator rotated the M2M key and the previous one is now invalid.
- **Service deactivation / deletion** — the M2M service was disabled.
- **Premature revocation** — the token was explicitly revoked at the IdP (e.g. compromise response).
- **Clock drift** — the resource API's `iat`/`exp` validation is stricter than the consumer's safety margin.
- **IdP hiccup** — the IdP issued a token but its session-store replication is lagging.

When the resource API returns **HTTP 401**, `M2MApiClient` :

1. Invalidates the cached token (`cachedToken = null`, `cachedExpiresAt = 0`).
2. Calls `getToken()` again — this performs **one** fresh exchange against the IdP.
3. Replays the original request with the freshly obtained Bearer.

If the second attempt also returns **HTTP 401**, the keyfile itself is no longer accepted. A `KeyfileInvalidException` is thrown so the operator can react (re-download a fresh keyfile from the admin UI).

## What does NOT trigger a refresh

Only HTTP 401 triggers the refresh-and-retry. The following bubble up as-is:

| Outcome              | Behaviour                                                                                |
|----------------------|------------------------------------------------------------------------------------------|
| HTTP 2xx             | Decoded JSON envelope returned to the caller.                                            |
| HTTP 3xx             | Bubbles up as Guzzle response (the client does not follow redirects automatically).      |
| HTTP 4xx (non-401)   | Decoded JSON envelope returned. The caller is responsible for inspecting the status.     |
| HTTP 5xx             | Decoded JSON envelope returned. The caller is responsible for retrying with backoff.     |
| Network error        | `GuzzleException` thrown (timeout, DNS failure, TLS handshake failure, …).               |
| IdP rejected at exchange | `KeyfileInvalidException` thrown immediately (no retry — a re-exchange would fail too). |

This separation is intentional : a refresh-and-retry on 5xx or 403 would mask the real failure and waste an IdP round-trip.

## Observability hooks

To log every token exchange (or every API call), inject a Guzzle client with a logging middleware and use it for both — see [advanced/http-client-injection.md](advanced/http-client-injection.md).

For per-request timing, subclass `M2MApiClient` and override `doRequest()` — see [advanced/extending-the-client.md](advanced/extending-the-client.md).

## Related constants

| Constant                                  | Default              | Purpose                                                                  |
|-------------------------------------------|----------------------|--------------------------------------------------------------------------|
| `M2MApiClient::ASSERTION_TTL_SECONDS`     | `60`                 | Lifetime of the JWT bearer assertion sent to the token endpoint.         |
| `M2MApiClient::DEFAULT_TOKEN_TTL`         | `3600`               | Fallback TTL when the IdP omits `expires_in` in its response.            |
| `M2MApiClient::REFRESH_SAFETY_MARGIN`     | `60`                 | Refresh the cached token this many seconds before its hard expiration.   |
| `M2MApiClient::DEFAULT_TOKEN_PATH`        | `/oauth/v2/token`    | Default token endpoint path on the issuer host (Zitadel convention).     |
