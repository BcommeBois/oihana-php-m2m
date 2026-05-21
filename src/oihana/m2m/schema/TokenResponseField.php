<?php

namespace oihana\m2m\schema ;

/**
 * Field names of the JSON body returned by the OAuth 2.0 token
 * endpoint after a successful exchange (RFC 6749 §5.1).
 *
 * @package oihana\m2m\schema
 */
class TokenResponseField
{
    const ACCESS_TOKEN = 'access_token' ;
    const EXPIRES_IN   = 'expires_in'   ;
}
