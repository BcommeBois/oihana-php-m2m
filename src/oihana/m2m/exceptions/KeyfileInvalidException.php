<?php

namespace oihana\m2m\exceptions ;

use RuntimeException;

/**
 * Thrown when the keyfile is no longer accepted, either by the
 * identity provider (the JWT bearer assertion gets refused at the
 * token endpoint) or by the resource API (two consecutive 401 with
 * distinct fresh tokens).
 *
 * Hint to the operator : the application has likely been rotated,
 * deactivated or deleted server-side. Download a fresh keyfile from
 * the admin UI.
 *
 * @package oihana\m2m\exceptions
 * @author  Marc Alcaraz
 */
class KeyfileInvalidException extends RuntimeException
{
}
