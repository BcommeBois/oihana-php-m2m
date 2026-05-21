<?php

namespace oihana\m2m\schema ;

/**
 * Standard JWT claim names (RFC 7519 §4.1) used when building the
 * bearer assertion sent at the identity provider's token endpoint.
 *
 * @package oihana\m2m\schema
 */
class JwtClaim
{
    const AUDIENCE   = 'aud' ;
    const EXPIRES_AT = 'exp' ;
    const ISSUED_AT  = 'iat' ;
    const ISSUER     = 'iss' ;
    const JWT_ID     = 'jti' ;
    const NOT_BEFORE = 'nbf' ;
    const SUBJECT    = 'sub' ;
}
