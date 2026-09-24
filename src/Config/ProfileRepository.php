<?php

namespace AMPBoard\Config;

use AMPBoard\Security\CredentialCipher;
use RuntimeException;

/** Resolves profiles and persists their data without publishing global constants. */
final class ProfileRepository {
	private string $directory;
	private CredentialCipher $cipher;
	private AtomicFileWriter $writer;
	private array $overrides;

	public function __construct( string $directory, CredentialCipher $cipher, ?AtomicFileWriter $writer = null, array $overrides = [] ) {
		$this->directory = $directory;
		$this->cipher = $cipher;
		$this->writer = $writer ?? new AtomicFileWriter();
		$this->overrides = $overrides;
	}

	public function load( string $user ): array {
		$reader = new ProfileReader();
		$local = $reader->read( $this->directory . '/local.php' );
		$default = $this->directory . '/profiles/default';
		$target = $this->directory . '/profiles/' . sanitizeFolderName( $user );
		$active = is_dir( $target ) ? $target : $default;
		$lock = $active === $target && is_writable( $active )
			? @fopen( $active . '/.profile.lock', 'c+b' )
			: ( is_file( $active . '/.profile.lock' ) ? @fopen( $active . '/.profile.lock', 'rb' ) : false );
		if ( $lock === false && is_file( $active . '/.profile.lock' ) ) { throw new RuntimeException( 'Cannot open profile lock.' ); }
		try {
			if ( $lock !== false && ! flock( $lock, LOCK_SH ) ) { throw new RuntimeException( 'Cannot read profile lock.' ); }
			$profile = $reader->read( $active . '/user_config.php', $local['settings'] );
			$json = [];
			foreach ( ProfileSchema::JSON as $key => $name ) { $json[ $key ] = read_json_array_safely( $active . '/' . $name ); }
		} finally { if ( $lock !== false ) { fclose( $lock ); } }
		// Legacy UI variables are defaults; local constants always took precedence.
		$localPriority = $local['legacy'] ? array_intersect_key( $local['settings'], array_flip( ProfileSchema::CONSTANTS ) ) : $local['settings'];
		$settings = array_replace( ProfileSchema::defaults(), $local['settings'], $profile['settings'], $localPriority, $this->overrides );
		$db = [ 'host' => $settings['DB_HOST'] ];
		foreach ( [ 'user' => 'DB_USER', 'pass' => 'DB_PASSWORD' ] as $key => $name ) {
			$value = $settings[ $name ];
			$db[ $key ] = is_array( $value ) ? $this->cipher->decrypt( $value['encrypted'] ) : $this->cipher->resolveLegacy( $value );
		}
		return [ 'settings' => $settings, 'db' => $db, 'php' => array_replace( $profile['php'], $local['php'] ),
			'profile' => $json, 'default' => $default, 'target' => $target, 'active' => $active ];
	}

	public function save( string $user, array $data ): void {
		$files = [];
		foreach ( ProfileSchema::JSON as $key => $name ) {
			$files[ $name ] = json_encode( $data['profile'][ $key ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
		}
		$settings = $data['settings'];
		foreach ( [ 'DB_USER', 'DB_PASSWORD' ] as $name ) {
			if ( isset( $settings[ $name ] ) ) { $settings[ $name ] = [ 'encrypted' => $this->cipher->encrypt( $settings[ $name ] ) ]; }
		}
		// Commit PHP last: migration only becomes visible after sidecars are ready.
		$files['user_config.php'] = "<?php\n/** Auto-generated AMPBoard profile, format 1. */\nreturn "
			. var_export( [ 'version' => 1, 'settings' => $settings, 'php' => $data['php'] ], true ) . ";\n";
		$this->writer->write( $this->directory . '/profiles/' . sanitizeFolderName( $user ), $files );
	}
}
