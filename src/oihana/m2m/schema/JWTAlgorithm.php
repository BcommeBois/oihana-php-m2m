<?php

namespace oihana\m2m\schema ;

/**
 * JWT signing algorithm identifiers (RFC 7518).
 *
 * @package oihana\m2m\schema
 */
class JWTAlgorithm
{
    const HS256 = 'HS256' ;
    const HS384 = 'HS384' ;
    const HS512 = 'HS512' ;

    const PS256 = 'PS256' ;
    const PS384 = 'PS384' ;
    const PS512 = 'PS512' ;

    const RS256 = 'RS256' ;
    const RS384 = 'RS384' ;
    const RS512 = 'RS512' ;
}
