<?php
/** Modern request configuration: no helper loading, constant publication, session start, or database probes.
 * Trusted PHP profiles can still define constants or change PHP runtime settings.
 */
require_once __DIR__ . "/autoload.php";

$identity = new \AMPBoard\System\Identity( $_SERVER, [ new \AMPBoard\System\UserDiscovery(), 'discover' ] );
$cipher = new \AMPBoard\Security\CredentialCipher( defined( 'CRYPTO_KEY_FILE' ) ? CRYPTO_KEY_FILE : dirname( __DIR__ ) . '/.key' );
$jsonReader = new \AMPBoard\Filesystem\JsonReader();
$profiles = new \AMPBoard\Config\ProfileRepository( __DIR__, $cipher, null, \AMPBoard\Config\LegacyConstants::read(), $jsonReader );
$configLoader = new \AMPBoard\Config\Loader( __DIR__, $identity, $profiles, $jsonReader );
$config = $configLoader->load( false, false );
