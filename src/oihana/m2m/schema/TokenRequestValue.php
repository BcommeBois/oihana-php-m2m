<?php

namespace oihana\m2m\schema ;

/**
 * Constant values for the OAuth 2.0 token request fields.
 *
 * @package oihana\m2m\schema
 */
class TokenRequestValue
{
    const DEFAULT_SCOPE    = 'openid' ;
    const GRANT_JWT_BEARER = 'urn:ietf:params:oauth:grant-type:jwt-bearer' ;
}
