<?php

namespace oihana\m2m\schema ;

/**
 * Field names of the keyfile JSON document used to bootstrap an M2M
 * client (RSA private key + connection metadata).
 *
 * @package oihana\m2m\schema
 */
class Keyfile
{
    const API_BASE_URL = 'apiBaseUrl' ;
    const AUDIENCE     = 'audience'   ;
    const CLIENT_ID    = 'clientId'   ;
    const ISSUER       = 'issuer'     ;
    const KEY          = 'key'        ;
    const KEY_ID       = 'keyId'      ;
    const SCOPE        = 'scope'      ;
    const TYPE         = 'type'       ;
    const USER_ID      = 'userId'     ;
}
