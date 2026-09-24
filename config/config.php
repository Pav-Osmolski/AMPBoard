<?php
/**
 * Application composition: the compatibility entry point for pages and utilities.
 * Existing profile files and constants remain supported during the migration.
 *
 * @var array<string, mixed> $config
 * @var \AMPBoard\Database\ConnectionFactory $database
 * @var \AMPBoard\Ui\Renderer $ui
 */

require_once __DIR__ . '/autoload.php';

if ( ! defined( 'AMPBOARD_NO_HELPERS' ) ) {
	require_once __DIR__ . '/helpers.php';
}

$cipher = new \AMPBoard\Security\CredentialCipher( defined( 'CRYPTO_KEY_FILE' ) ? CRYPTO_KEY_FILE : dirname( __DIR__ ) . '/.key' );
$profiles = new \AMPBoard\Config\ProfileRepository( __DIR__, $cipher, null, \AMPBoard\Config\LegacyConstants::read() );
$config = ( new \AMPBoard\Config\Loader( __DIR__, $profiles ) )->load( true );
$database = new \AMPBoard\Database\ConnectionFactory( $config['db'] );
$ui = new \AMPBoard\Ui\Renderer( $config, $database );
