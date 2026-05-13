# Keyfile format

The **keyfile** is a JSON document that authenticates an M2M (machine-to-machine) service account against an OAuth2 / OIDC identity provider. It is generated **once** by the API administrator at service creation (or key rotation), handed to the M2M consumer, and **never** persisted server-side.

The keyfile is **auto-sufficient**: it carries both the cryptographic material (RSA private key + key ID) and the connection metadata (issuer URL, API base URL, OAuth2 scope) so a third-party developer can connect to the API without any additional configuration.

## JSON schema

```json
{
    "type":        "serviceaccount",
    "keyId":       "303384786189271040",
    "key":         "-----BEGIN RSA PRIVATE KEY-----\n…\n-----END RSA PRIVATE KEY-----\n",
    "userId":      "303384547813785600",
    "clientId":    null,
    "issuer":      "https://my-org.zitadel.cloud",
    "audience":    "303380000000000000",
    "scope":       "openid profile urn:zitadel:iam:org:project:id:303380000000000000:aud",
    "apiBaseUrl":  "https://api.example.com"
}
```

## Field reference

| Field        | Required | Origin              | Description                                                                                                            |
|--------------|----------|---------------------|------------------------------------------------------------------------------------------------------------------------|
| `key`        | ✅       | IdP                 | RSA private key in PEM format. Used to sign the JWT bearer assertion.                                                  |
| `keyId`      | ✅       | IdP                 | Identifies this specific key on the IdP side. Becomes the `kid` of the JWT header.                                     |
| `userId`     | ⚠️\*     | IdP                 | The IdP user identifier (= the `sub` claim of the resulting access token). Modern service-account flow.                |
| `clientId`   | ⚠️\*     | IdP                 | OAuth2 clientId of a registered application. Legacy API-app flow. Used as `iss` and `sub` of the JWT bearer assertion. |
| `type`       | ⚪        | IdP                 | Optional informational field (e.g. `serviceaccount`).                                                                  |
| `issuer`     | ✅       | API (auto-injected) | The IdP issuer URL (e.g. `https://my-org.zitadel.cloud`). Used to derive the token endpoint as `{issuer}{tokenPath}`.  |
| `apiBaseUrl` | ✅       | API (auto-injected) | The base URL of the resource API. Concatenated with the request path on each call.                                     |
| `scope`      | ⚪        | API (auto-injected) | The OAuth2 scope to request at the token endpoint. Falls back to `openid` when omitted.                                |
| `audience`   | ⚪        | API (auto-injected) | The audience expected in the access token (typically the IdP project identifier). Informational at the client level.   |

\* **At least one of `userId` or `clientId`** must be present. `userId` is the modern Zitadel Service User identifier; `clientId` is the legacy OAuth-registered API application identifier.

## Override at runtime

Any of `issuer`, `apiBaseUrl`, `scope`, and the (separate) `tokenPath` can be overridden via the constructor without modifying the keyfile JSON on disk — useful for local testing:

```php
use oihana\m2m\M2MApiClient;

$client = M2MApiClient::fromKeyfile
(
    keyfilePath : '/secrets/staging-keyfile.json' ,
    apiBaseUrl  : 'http://localhost:8000'         // override apiBaseUrl
) ;
```

## Validation

The constructor enforces the following invariants and throws `RuntimeException` otherwise:

- `key` must be present and non-empty.
- `keyId` must be present and non-empty.
- At least one of `userId` or `clientId` must be present and non-empty.
- `issuer` must be resolvable (either from the keyfile or from the constructor override).
- `apiBaseUrl` must be resolvable (either from the keyfile or from the constructor override).

Trailing slashes on `issuer` and `apiBaseUrl` are stripped automatically so concatenation never produces a double slash.

## Security considerations

- **The key is sensitive.** Treat the keyfile with the same care as a private SSH key or a database password.
- **Never commit it to version control.** Add `*-keyfile.json` (or your project's secret-naming convention) to `.gitignore`.
- **Restrict file permissions.** `chmod 600` (Unix) or equivalent ACL on Windows.
- **Use a secret manager in production.** Kubernetes `Secret`, AWS Secrets Manager, HashiCorp Vault, …
- **Rotate periodically.** The API exposes a key-rotation endpoint — rotate the key and replace the keyfile on the consumer side. The previous key is invalidated immediately on rotation.
- **Detect compromise via the IdP audit log.** Each token exchange is logged on the IdP side; unusual `iat` patterns or unexpected source IPs indicate a leaked keyfile.

## Lifecycle

1. **Service creation** — the API administrator creates an M2M service. The API returns the keyfile JSON exactly once; if you don't save it, you'll need a key rotation.
2. **Distribution** — hand the keyfile to the M2M consumer through a secure channel (encrypted message, secret manager, …).
3. **Consumer use** — the consumer loads it via `M2MApiClient::fromKeyfile()` and starts calling the API.
4. **Rotation** — the administrator triggers a key rotation. A new keyfile is returned; the previous one stops working immediately.
5. **Deactivation / deletion** — the administrator deactivates or deletes the service. The next `getToken()` call fails with `KeyfileInvalidException`.
