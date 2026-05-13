# Astuces & bonnes pratiques

`oihana/php-m2m` dépend déjà de [`oihana/php-enums`](https://github.com/BcommeBois/oihana-php-enums) et de [`oihana/php-files`](https://github.com/BcommeBois/oihana-php-files), qui exposent des **constantes typées** pour chaque magic string HTTP / Guzzle / MIME utilisé dans cette bibliothèque.

Utilisez-les dans vos sous-classes, vos configurations Guzzle personnalisées et vos middlewares pour garder votre code auto-documenté, sûr en typage et résistant au refactoring.

## Pourquoi éviter les magic strings ?

```php
// ❌ magic strings — pas d'autocomplétion, pas de détection de typo, pas de sécurité au renommage
$http = new Client
(
    [
        'http_errors' => false ,
        'verify'      => true ,
        'timeout'     => 15 ,
        'headers'     => [ 'Authorization' => 'Bearer ' . $token ] ,
    ]
) ;

// ✅ constantes typées — autocomplétion IDE, refactor-safe, auto-documenté
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

Une typo dans `'http_errors'` casse silencieusement la politique de retry sur 401. Une typo dans `GuzzleOption::HTTP_ERRORS` est attrapée au parse.

## Les constantes que vous utiliserez le plus

| Préoccupation                          | Constante                                                  | Remplace                                                                         |
|----------------------------------------|------------------------------------------------------------|----------------------------------------------------------------------------------|
| Clés d'options Guzzle                  | `oihana\enums\http\GuzzleOption::*`                        | `'http_errors'`, `'verify'`, `'timeout'`, `'headers'`, `'handler'`, `'proxy'`, … |
| Noms d'en-têtes HTTP                   | `oihana\enums\http\HttpHeader::*`                          | `'Authorization'`, `'Accept'`, `'Content-Type'`, `'X-Request-Id'`, …             |
| Méthodes HTTP                          | `oihana\enums\http\HttpMethod::*`                          | `'GET'`, `'POST'`, `'PATCH'`, `'PUT'`, `'DELETE'`                                |
| Codes de statut HTTP                   | `oihana\enums\http\HttpStatusCode::*` + helpers            | nombres magiques `200`, `401`, `500`, `429` ; helpers `getDescription()` / `getType()` / `fromException()` |
| Préfixes de schéma d'auth              | `oihana\enums\http\AuthScheme::prefix(AuthScheme::*)`      | `'Bearer '`, `'Basic '`, `'Digest '`, `'OAuth '`                                 |
| Champs de requête OAuth2 / OIDC        | `oihana\enums\oauth2\OAuth2Parameter::*`                   | `'grant_type'`, `'scope'`, `'assertion'`, `'client_id'`, `'code_verifier'`, …    |
| Champs de réponse token OAuth2 / OIDC  | `oihana\enums\oauth2\OAuth2TokenField::*`                  | `'access_token'`, `'expires_in'`, `'refresh_token'`, `'id_token'`, …             |
| Noms de claims JWT enregistrés         | `xyz\oihana\schema\constants\JwtClaim::*`                  | `'iss'`, `'sub'`, `'aud'`, `'iat'`, `'exp'`, `'jti'`, `'nbf'`                    |
| Algorithmes de signature JWT           | `xyz\oihana\schema\constants\JWTAlgorithm::*`              | `'RS256'`, `'HS256'`, `'PS512'`, … (+ helpers `isSymmetric()` / `isAsymmetric()`)|
| Types MIME                             | `oihana\files\enums\FileMimeType::*`                       | `'application/json'`, `'text/html'`, `'application/xml'`, …                      |

Référence :
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

> **Note sur les doublons** — `oihana\enums\oauth2\OAuth2Parameter` et `xyz\oihana\schema\constants\auth\TokenRequestField` couvrent les mêmes clés OAuth2 wire-format. Pareil pour `OAuth2TokenField` ↔ `TokenResponseField`. `M2MApiClient` utilise les variantes schema en interne pour des raisons historiques ; dans votre propre code, préférez `oihana\enums\oauth2\*` (c'est l'emplacement canonique pour les enums OAuth2/OIDC à terme).

## Recette 1 — client Guzzle typé

```php
use oihana\m2m\M2MApiClient;
use oihana\enums\http\GuzzleOption;
use oihana\enums\http\HttpHeader;
use GuzzleHttp\Client;

$http = new Client
(
    [
        GuzzleOption::HTTP_ERRORS => false ,    // ⚠️ garder ceci — requis par la politique de retry sur 401
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

## Recette 2 — sous-classe typée avec en-têtes personnalisés

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
                'X-Tenant-Id'             => $this->tenantId ,    // en-tête spécifique à l'application — pas de constante
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

> Note : conservez `RequestOptions::JSON` pour le corps — la constante vient de Guzzle lui-même et reste la référence canonique pour cette clé. `GuzzleOption::JSON` de `oihana/php-enums` est aussi valide ; mélanger les deux est sans risque puisqu'elles pointent sur la même chaîne `'json'`.

## Recette 3 — middleware retry typé

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

## Recette 4 — inspection typée du code de statut

`HttpStatusCode` est l'outil approprié pour toute décision basée sur le statut d'une réponse. Évitez les nombres magiques type `>= 500` ou `=== 401` éparpillés dans votre code.

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
                    'L\'API a retourné %d %s — %s' ,
                    $status ,
                    HttpStatusCode::getDescription( $status ) ?? 'Inconnu' ,
                    substr( (string) $response->getBody() , 0 , 400 )
                )
            ) ;
        }

        return parent::decodeResponse( $response ) ;
    }
}
```

`HttpStatusCode::fromException()` est aussi très utile quand vous re-émettez une réponse HTTP depuis une exception levée — voir le PHPDoc du helper pour la convention utilisée par les classes Error4xx / Error5xx de `oihana/php-exceptions`.

## Recette 5 — OAuth2 / JWT typés pour des décodeurs de token personnalisés

Quand vous héritez de `M2MApiClient::getToken()` (par ex. pour supporter un grant type, un algorithme de signature, ou un schéma de réponse différents), abandonnez les magic strings et utilisez les enums OAuth2 + JWT typés.

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
        // Assertion personnalisée — par exemple en PS512 plutôt que RS256.
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
            alg   : JWTAlgorithm::PS512 ,                                 // plus fort que RS256
            keyId : $this->keyfile[ Keyfile::KEY_ID ]
        ) ;

        // Form params OAuth2 typés au lieu de magic strings.
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
            throw new KeyfileInvalidException( 'IdP n\'a retourné aucun access_token dans le body.' ) ;
        }

        // Cache + retour — même protocole que le parent.
        $this->cacheToken
        (
            $payload[ OAuth2TokenField::ACCESS_TOKEN ] ,
            (int) ( $payload[ OAuth2TokenField::EXPIRES_IN ] ?? 3600 )
        ) ;

        return $payload[ OAuth2TokenField::ACCESS_TOKEN ] ;
    }
}
```

> Les accesseurs protected utilisés ci-dessus (`getHttp()`, `getIssuer()`, `getTokenPath()`, `getScope()`, `cacheToken()`) sont illustratifs — `M2MApiClient` garde ces champs privés en v1.0. Soit vous les exposez dans votre propre fork sub-class-friendly, soit vous répliquez la logique d'assignation directement. Voir [advanced/extending-the-client.md](advanced/extending-the-client.md).

## Payloads typés Schema.org avec `oihana/php-schema`

`oihana/php-schema` expose des classes typées alignées Schema.org pour le domaine auth. Le schéma `Keyfile` (`xyz\oihana\schema\auth\Keyfile`) hérite de `org\schema\Thing` — ce qui veut dire que chaque keyfile est par construction un objet JSON-LD-sérialisable, typé pour l'IDE, que vous pouvez hydrater, valider et émettre sans jamais toucher aux clés en chaîne.

```php
use xyz\oihana\schema\auth\Keyfile;

// Hydratation depuis JSON
$decoded = json_decode( file_get_contents( '/secrets/m2m-keyfile.json' ) , true ) ;
$keyfile = new Keyfile( $decoded ) ;

// Accès typé — autocomplétion IDE sur chaque champ
echo $keyfile->userId ;
echo $keyfile->apiBaseUrl ;
echo $keyfile->scope ;

// Passage à M2MApiClient (le constructeur accepte un tableau associatif, donc cast en arrière)
use oihana\m2m\M2MApiClient;
$client = new M2MApiClient( (array) $keyfile ) ;

// Re-sérialisation en JSON-LD façon Schema.org
echo json_encode( $keyfile , JSON_PRETTY_PRINT ) ;
// {
//   "@context": "https://schema.org",
//   "@type":    "Keyfile",
//   "userId":   "...",
//   ...
// }
```

### Pourquoi ça compte

- **Autocomplétion IDE + analyse statique** — les typos sur `$keyfile->userId` sont attrapées au parse, contrairement à `$keyfile[ 'userId' ]`.
- **Conformité Schema.org** — le JSON de sortie porte `@context` et `@type`, prêt à être consommé par n'importe quel client JSON-LD (moteurs de recherche, knowledge graphs, couches de fédération).
- **Hooks de validation** — les propriétés typées nullable rendent triviale la détection de champ manquant (`if( $keyfile->userId === null )`).
- **Constantes pour les clés** — quand vous devez utiliser la forme tableau (par ex. côté constructeur), `Keyfile::USER_ID`, `Keyfile::API_BASE_URL`, … restent disponibles comme constantes string typées.

### Autres schémas auth dans `oihana/php-schema`

Le même pattern s'applique au reste du domaine auth — explorez [`xyz\oihana\schema\auth\*`](https://github.com/BcommeBois/oihana-php-schema/tree/main/src/xyz/oihana/schema/auth) pour des classes typées couvrant les tokens OAuth2, les documents OIDC discovery, JWKS, …

## Quand NE PAS utiliser les constantes

- **En-têtes spécifiques à l'application** (par ex. `X-Tenant-Id`, `X-Internal-Trace`) qui sont propres à votre plateforme — gardez-les en chaînes brutes (ou définissez votre propre classe enum `MyAppHeader::`).
- **Bizarreries d'API tierces** avec des noms d'en-têtes non-standards — même logique.
- **Scripts inline ponctuels** où ajouter des `use` produit plus de bruit que les constantes n'apportent de valeur.

Pour tout ce qui est HTTP-standard, préférez les constantes typées — votre futur vous (et votre IDE) vous remercieront.

## Pour aller plus loin

- [README oihana/php-enums](https://github.com/BcommeBois/oihana-php-enums) — le catalogue complet des enums HTTP / OAuth / JWT / Schema.org / communs.
- [README oihana/php-schema](https://github.com/BcommeBois/oihana-php-schema) — classes de données typées alignées Schema.org (Keyfile, tokens OAuth2, JWKS, …) + constantes JWT/OAuth.
- [README oihana/php-files](https://github.com/BcommeBois/oihana-php-files) — helpers système de fichiers et types MIME.
