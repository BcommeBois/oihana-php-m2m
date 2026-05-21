<?php

namespace oihana\m2m\schema ;

/**
 * Form field names sent in the `application/x-www-form-urlencoded`
 * body of a request to the OAuth 2.0 token endpoint.
 *
 * @package oihana\m2m\schema
 */
class TokenRequestField
{
    const ASSERTION  = 'assertion'  ;
    const GRANT_TYPE = 'grant_type' ;
    const SCOPE      = 'scope'      ;
}
