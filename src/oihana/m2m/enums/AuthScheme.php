<?php

namespace oihana\m2m\enums ;

/**
 * HTTP authentication schemes used as the first token of the
 * `Authorization` header (RFC 7235).
 *
 * @package oihana\m2m\enums
 */
class AuthScheme
{
    const BEARER = 'Bearer' ;

    /**
     * Returns the scheme followed by a single space, ready to prefix a token
     * value in an Authorization header.
     *
     * @param string $scheme One of the class constants.
     * @return string
     */
    public static function prefix( string $scheme ) : string
    {
        return $scheme . ' ' ;
    }
}
