<?php

namespace tests\oihana\m2m;

use oihana\m2m\M2MApiClient;
use oihana\m2m\schema\Keyfile;
use oihana\m2m\schema\TokenRequestValue;

use PHPUnit\Framework\TestCase;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Tests for the M2MApiClient construction + auto-sufficient keyfile
 * resolution.
 *
 * The full token-exchange path (`getToken`) is integration-level and
 * exercised end-to-end against a real identity provider — these unit
 * tests focus on the constructor contract, fromKeyfile factory, and
 * override semantics.
 *
 * @covers \oihana\m2m\M2MApiClient
 */
class M2MApiClientTest extends TestCase
{
    /**
     * A minimal RSA-shaped private key payload — never used to sign,
     * just sufficient for the constructor's "non-empty" check.
     */
    private const FAKE_KEY = "-----BEGIN RSA PRIVATE KEY-----\nFAKE\n-----END RSA PRIVATE KEY-----\n" ;

    public function testConstructorAcceptsAutoSufficientKeyfile() : void
    {
        $keyfile =
        [
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
            Keyfile::SCOPE        => 'openid profile' ,
        ] ;

        $client = new M2MApiClient( $keyfile ) ;

        $this->assertSame( 'https://idp.example.com' , $this->readPrivate( $client , 'issuer' ) ) ;
        $this->assertSame( 'https://api.example.com' , $this->readPrivate( $client , 'apiBaseUrl' ) ) ;
        $this->assertSame( 'openid profile'          , $this->readPrivate( $client , 'scope' ) ) ;
    }

    public function testConstructorOverrideArgumentsBeatKeyfileFields() : void
    {
        $keyfile =
        [
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
            Keyfile::SCOPE        => 'openid' ,
        ] ;

        $client = new M2MApiClient
        (
            $keyfile ,
            'https://override-idp.local' ,
            'https://override-api.local' ,
            null ,
            'openid extra'
        ) ;

        $this->assertSame( 'https://override-idp.local' , $this->readPrivate( $client , 'issuer' ) ) ;
        $this->assertSame( 'https://override-api.local' , $this->readPrivate( $client , 'apiBaseUrl' ) ) ;
        $this->assertSame( 'openid extra'               , $this->readPrivate( $client , 'scope' ) ) ;
    }

    public function testConstructorScopeFallsBackToDefaultWhenNeitherKeyfileNorOverride() : void
    {
        $keyfile =
        [
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ] ;

        $client = new M2MApiClient( $keyfile ) ;

        $this->assertSame( TokenRequestValue::DEFAULT_SCOPE , $this->readPrivate( $client , 'scope' ) ) ;
    }

    public function testConstructorAcceptsLegacyClientIdOnlyKeyfile() : void
    {
        $keyfile =
        [
            Keyfile::CLIENT_ID    => 'legacy-client@org' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ] ;

        $client = new M2MApiClient( $keyfile ) ;

        $this->assertInstanceOf( M2MApiClient::class , $client ) ;
    }

    public function testConstructorThrowsWhenKeyMissing() : void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/Keyfile is malformed/' ) ;

        new M2MApiClient
        ([
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ]) ;
    }

    public function testConstructorThrowsWhenKeyIdMissing() : void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/Keyfile is malformed/' ) ;

        new M2MApiClient
        ([
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ]) ;
    }

    public function testConstructorThrowsWhenBothUserIdAndClientIdMissing() : void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/Keyfile is malformed/' ) ;

        new M2MApiClient
        ([
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ]) ;
    }

    public function testConstructorThrowsWhenIssuerUnresolvable() : void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/`issuer` is missing/' ) ;

        new M2MApiClient
        ([
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ]) ;
    }

    public function testConstructorThrowsWhenApiBaseUrlUnresolvable() : void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/`apiBaseUrl` is missing/' ) ;

        new M2MApiClient
        ([
            Keyfile::USER_ID => 'user-123' ,
            Keyfile::KEY     => self::FAKE_KEY ,
            Keyfile::KEY_ID  => 'kid-1' ,
            Keyfile::ISSUER  => 'https://idp.example.com' ,
        ]) ;
    }

    public function testConstructorTrimsTrailingSlashes() : void
    {
        $keyfile =
        [
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com/' ,
            Keyfile::API_BASE_URL => 'https://api.example.com/' ,
        ] ;

        $client = new M2MApiClient( $keyfile ) ;

        $this->assertSame( 'https://idp.example.com' , $this->readPrivate( $client , 'issuer' ) ) ;
        $this->assertSame( 'https://api.example.com' , $this->readPrivate( $client , 'apiBaseUrl' ) ) ;
    }

    public function testConstructorDefaultsTokenPathToZitadelConvention() : void
    {
        $client = new M2MApiClient( $this->minimalKeyfile() ) ;

        $this->assertSame( M2MApiClient::DEFAULT_TOKEN_PATH , $this->readPrivate( $client , 'tokenPath' ) ) ;
        $this->assertSame( '/oauth/v2/token'                , $this->readPrivate( $client , 'tokenPath' ) ) ;
    }

    public function testConstructorAcceptsCustomTokenPath() : void
    {
        $client = new M2MApiClient
        (
            $this->minimalKeyfile() ,
            null ,
            null ,
            null ,
            null ,
            '/oauth/token'
        ) ;

        $this->assertSame( '/oauth/token' , $this->readPrivate( $client , 'tokenPath' ) ) ;
    }

    public function testConstructorPrependsLeadingSlashToTokenPath() : void
    {
        $client = new M2MApiClient
        (
            $this->minimalKeyfile() ,
            null ,
            null ,
            null ,
            null ,
            'realms/my-realm/protocol/openid-connect/token'
        ) ;

        $this->assertSame
        (
            '/realms/my-realm/protocol/openid-connect/token' ,
            $this->readPrivate( $client , 'tokenPath' )
        ) ;
    }

    public function testFromKeyfileReadsAndDecodesAutoSufficientKeyfile() : void
    {
        $payload =
        [
            Keyfile::USER_ID      => 'user-456' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-2' ,
            Keyfile::ISSUER       => 'https://idp2.example.com' ,
            Keyfile::API_BASE_URL => 'https://api2.example.com' ,
            Keyfile::SCOPE        => 'openid profile' ,
        ] ;

        $path = sys_get_temp_dir() . '/m2m-keyfile-test-' . bin2hex( random_bytes( 6 ) ) . '.json' ;
        file_put_contents( $path , json_encode( $payload ) ) ;

        try
        {
            $client = M2MApiClient::fromKeyfile( $path ) ;

            $this->assertSame( 'https://idp2.example.com' , $this->readPrivate( $client , 'issuer' ) ) ;
            $this->assertSame( 'https://api2.example.com' , $this->readPrivate( $client , 'apiBaseUrl' ) ) ;
            $this->assertSame( 'openid profile'           , $this->readPrivate( $client , 'scope' ) ) ;
        }
        finally
        {
            @unlink( $path ) ;
        }
    }

    public function testFromKeyfileForwardsTokenPathOverride() : void
    {
        $payload =
        [
            Keyfile::USER_ID      => 'user-789' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-3' ,
            Keyfile::ISSUER       => 'https://idp3.example.com' ,
            Keyfile::API_BASE_URL => 'https://api3.example.com' ,
        ] ;

        $path = sys_get_temp_dir() . '/m2m-keyfile-tokenpath-' . bin2hex( random_bytes( 6 ) ) . '.json' ;
        file_put_contents( $path , json_encode( $payload ) ) ;

        try
        {
            $client = M2MApiClient::fromKeyfile
            (
                $path ,
                null ,
                null ,
                null ,
                null ,
                '/oauth/token'
            ) ;

            $this->assertSame( '/oauth/token' , $this->readPrivate( $client , 'tokenPath' ) ) ;
        }
        finally
        {
            @unlink( $path ) ;
        }
    }

    public function testFromKeyfileThrowsOnUnreadablePath() : void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/not readable/' ) ;

        M2MApiClient::fromKeyfile( '/tmp/this-file-must-not-exist-' . bin2hex( random_bytes( 6 ) ) ) ;
    }

    public function testSignAssertionProducesAJwtVerifiableByOpensslWithTheMatchingPublicKey() : void
    {
        $res = openssl_pkey_new
        ([
            'private_key_bits' => 2048 ,
            'private_key_type' => OPENSSL_KEYTYPE_RSA ,
        ]) ;

        $this->assertNotFalse( $res , 'OpenSSL must be able to generate an RSA keypair for the test environment.' ) ;

        openssl_pkey_export( $res , $privPem ) ;
        $details = openssl_pkey_get_details( $res ) ;
        $pubPem  = $details[ 'key' ] ;

        $keyfile =
        [
            Keyfile::USER_ID      => 'svc-acct-1' ,
            Keyfile::KEY          => $privPem ,
            Keyfile::KEY_ID       => 'kid-test' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ] ;

        $client = new M2MApiClient( $keyfile ) ;

        $method = new ReflectionMethod( M2MApiClient::class , 'signAssertion' ) ;
        $method->setAccessible( true ) ;

        $now = time() ;
        $jwt = $method->invoke
        (
            $client ,
            [
                'iss' => 'svc-acct-1' ,
                'sub' => 'svc-acct-1' ,
                'aud' => 'https://idp.example.com' ,
                'iat' => $now ,
                'exp' => $now + 60 ,
            ] ,
            $privPem ,
            'kid-test'
        ) ;

        $parts = explode( '.' , $jwt ) ;
        $this->assertCount( 3 , $parts , 'A JWS compact serialization must have three dot-separated parts.' ) ;

        list( $headerB64 , $payloadB64 , $signatureB64 ) = $parts ;

        $header  = json_decode( self::base64UrlDecode( $headerB64 )  , true ) ;
        $payload = json_decode( self::base64UrlDecode( $payloadB64 ) , true ) ;

        $this->assertSame( [ 'typ' => 'JWT' , 'alg' => 'RS256' , 'kid' => 'kid-test' ] , $header ) ;
        $this->assertSame( 'svc-acct-1'              , $payload[ 'iss' ] ) ;
        $this->assertSame( 'svc-acct-1'              , $payload[ 'sub' ] ) ;
        $this->assertSame( 'https://idp.example.com' , $payload[ 'aud' ] ) ;
        $this->assertSame( $now                      , $payload[ 'iat' ] ) ;
        $this->assertSame( $now + 60                 , $payload[ 'exp' ] ) ;

        $signingInput = $headerB64 . '.' . $payloadB64 ;
        $signature    = self::base64UrlDecode( $signatureB64 ) ;

        $verified = openssl_verify( $signingInput , $signature , $pubPem , OPENSSL_ALGO_SHA256 ) ;
        $this->assertSame( 1 , $verified , 'RS256 signature must verify with the matching public key.' ) ;
    }

    public function testSignAssertionThrowsOnUnparseablePrivateKey() : void
    {
        $client = new M2MApiClient( $this->minimalKeyfile() ) ;

        $method = new ReflectionMethod( M2MApiClient::class , 'signAssertion' ) ;
        $method->setAccessible( true ) ;

        $this->expectException( \oihana\m2m\exceptions\KeyfileInvalidException::class ) ;
        $this->expectExceptionMessageMatches( '/private key cannot be parsed/' ) ;

        $method->invoke( $client , [ 'iss' => 'x' ] , 'NOT-A-PEM' , 'kid' ) ;
    }

    private static function base64UrlDecode( string $value ) : string
    {
        $remainder = strlen( $value ) % 4 ;
        if( $remainder > 0 )
        {
            $value .= str_repeat( '=' , 4 - $remainder ) ;
        }
        return base64_decode( strtr( $value , '-_' , '+/' ) ) ;
    }

    public function testFromKeyfileThrowsOnInvalidJson() : void
    {
        $path = sys_get_temp_dir() . '/m2m-keyfile-bad-' . bin2hex( random_bytes( 6 ) ) . '.json' ;
        file_put_contents( $path , 'not a json object' ) ;

        try
        {
            $this->expectException( RuntimeException::class ) ;
            $this->expectExceptionMessageMatches( '/not a valid JSON object/' ) ;

            M2MApiClient::fromKeyfile( $path ) ;
        }
        finally
        {
            @unlink( $path ) ;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalKeyfile() : array
    {
        return
        [
            Keyfile::USER_ID      => 'user-123' ,
            Keyfile::KEY          => self::FAKE_KEY ,
            Keyfile::KEY_ID       => 'kid-1' ,
            Keyfile::ISSUER       => 'https://idp.example.com' ,
            Keyfile::API_BASE_URL => 'https://api.example.com' ,
        ] ;
    }

    /**
     * Reads a private property of an M2MApiClient instance via
     * reflection.
     *
     * @param  M2MApiClient $client
     * @param  string       $property
     * @return mixed
     */
    private function readPrivate( M2MApiClient $client , string $property )
    {
        $ref     = new ReflectionClass( M2MApiClient::class ) ;
        $propRef = $ref->getProperty( $property ) ;
        $propRef->setAccessible( true ) ;

        return $propRef->getValue( $client ) ;
    }
}
