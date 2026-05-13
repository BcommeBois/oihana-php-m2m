<?php

namespace tests\oihana\m2m;

use oihana\m2m\M2MApiClient;

use xyz\oihana\schema\constants\auth\TokenRequestValue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use ReflectionClass;
use RuntimeException;

use xyz\oihana\schema\auth\Keyfile;

/**
 * Tests for the M2MApiClient construction + auto-sufficient keyfile
 * resolution.
 *
 * The full token-exchange path (`getToken`) is integration-level and
 * exercised end-to-end against a real identity provider — these unit
 * tests focus on the constructor contract, fromKeyfile factory, and
 * override semantics.
 */
#[CoversClass( M2MApiClient::class )]
class M2MApiClientTest extends TestCase
{
    /**
     * A minimal RSA-shaped private key payload — never used to sign,
     * just sufficient for the constructor's "non-empty" check.
     */
    private const string FAKE_KEY = "-----BEGIN RSA PRIVATE KEY-----\nFAKE\n-----END RSA PRIVATE KEY-----\n" ;

    public function testConstructorAcceptsAutoSufficientKeyfile() :void
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
        $this->assertSame( 'openid profile'         , $this->readPrivate( $client , 'scope' ) ) ;
    }

    public function testConstructorOverrideArgumentsBeatKeyfileFields() :void
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
            keyfile    : $keyfile ,
            issuer     : 'https://override-idp.local' ,
            apiBaseUrl : 'https://override-api.local' ,
            scope      : 'openid extra'
        ) ;

        $this->assertSame( 'https://override-idp.local' , $this->readPrivate( $client , 'issuer' ) ) ;
        $this->assertSame( 'https://override-api.local' , $this->readPrivate( $client , 'apiBaseUrl' ) ) ;
        $this->assertSame( 'openid extra'              , $this->readPrivate( $client , 'scope' ) ) ;
    }

    public function testConstructorScopeFallsBackToDefaultWhenNeitherKeyfileNorOverride() :void
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

    public function testConstructorAcceptsLegacyClientIdOnlyKeyfile() :void
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

    public function testConstructorThrowsWhenKeyMissing() :void
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

    public function testConstructorThrowsWhenKeyIdMissing() :void
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

    public function testConstructorThrowsWhenBothUserIdAndClientIdMissing() :void
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

    public function testConstructorThrowsWhenIssuerUnresolvable() :void
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

    public function testConstructorThrowsWhenApiBaseUrlUnresolvable() :void
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

    public function testConstructorTrimsTrailingSlashes() :void
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

    public function testConstructorDefaultsTokenPathToZitadelConvention() :void
    {
        $client = new M2MApiClient( $this->minimalKeyfile() ) ;

        $this->assertSame( M2MApiClient::DEFAULT_TOKEN_PATH , $this->readPrivate( $client , 'tokenPath' ) ) ;
        $this->assertSame( '/oauth/v2/token'                , $this->readPrivate( $client , 'tokenPath' ) ) ;
    }

    public function testConstructorAcceptsCustomTokenPath() :void
    {
        $client = new M2MApiClient
        (
            keyfile   : $this->minimalKeyfile() ,
            tokenPath : '/oauth/token'
        ) ;

        $this->assertSame( '/oauth/token' , $this->readPrivate( $client , 'tokenPath' ) ) ;
    }

    public function testConstructorPrependsLeadingSlashToTokenPath() :void
    {
        $client = new M2MApiClient
        (
            keyfile   : $this->minimalKeyfile() ,
            tokenPath : 'realms/my-realm/protocol/openid-connect/token'
        ) ;

        $this->assertSame
        (
            '/realms/my-realm/protocol/openid-connect/token' ,
            $this->readPrivate( $client , 'tokenPath' )
        ) ;
    }

    public function testFromKeyfileReadsAndDecodesAutoSufficientKeyfile() :void
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

    public function testFromKeyfileForwardsTokenPathOverride() :void
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
                keyfilePath : $path ,
                tokenPath   : '/oauth/token'
            ) ;

            $this->assertSame( '/oauth/token' , $this->readPrivate( $client , 'tokenPath' ) ) ;
        }
        finally
        {
            @unlink( $path ) ;
        }
    }

    public function testFromKeyfileThrowsOnUnreadablePath() :void
    {
        $this->expectException( RuntimeException::class ) ;
        $this->expectExceptionMessageMatches( '/not readable/' ) ;

        M2MApiClient::fromKeyfile( '/tmp/this-file-must-not-exist-' . bin2hex( random_bytes( 6 ) ) ) ;
    }

    public function testFromKeyfileThrowsOnInvalidJson() :void
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
    private function minimalKeyfile() :array
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
     */
    private function readPrivate( M2MApiClient $client , string $property ) :mixed
    {
        $ref      = new ReflectionClass( M2MApiClient::class ) ;
        $propRef  = $ref->getProperty( $property ) ;

        return $propRef->getValue( $client ) ;
    }
}
