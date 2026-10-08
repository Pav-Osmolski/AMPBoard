<?php
/** Isolated origin/CSRF checks; native sessions use only temporary storage. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';

use AMPBoard\Http\RequestOrigin;
use AMPBoard\Http\NativeSession;
use AMPBoard\Http\SessionStore;
use AMPBoard\Security\CsrfToken;

function checkRequest( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
final class FixtureSession implements SessionStore {
	public array $data = [];
	public bool $available = true;
	public bool $writable = true;
	public int $starts = 0;
	public function start(): bool { ++$this->starts; return $this->available; }
	public function read( string $key ) { return $this->data[$key] ?? null; }
	public function write( string $key, $value ): bool {
		if ( ! $this->writable ) { return false; }
		$this->data[$key] = $value; return true;
	}
	public function regenerate(): bool { return $this->available; }
}

$base = [ 'HTTP_HOST' => 'localhost', 'HTTPS' => 'off' ];
foreach ( [
	[ [], true ],
	[ [ 'HTTP_ORIGIN' => 'http://LOCALHOST:80' ], true ],
	[ [ 'HTTP_REFERER' => 'http://localhost/settings?view=settings' ], true ],
	[ [ 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://other.test/' ], false ],
	[ [ 'HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost:8080' ], true ],
	[ [ 'HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost' ], false ],
	[ [ 'HTTPS' => 'on', 'HTTP_HOST' => 'localhost:443', 'HTTP_ORIGIN' => 'https://localhost' ], true ],
	[ [ 'HTTP_HOST' => '[::1]:8080', 'HTTP_ORIGIN' => 'http://[::1]:8080' ], true ],
	[ [ 'HTTP_HOST' => '' ], false ],
	[ [ 'HTTP_HOST' => 'localhost/path' ], false ],
	[ [ 'HTTP_HOST' => 'localhost?query' ], false ],
	[ [ 'HTTP_HOST' => 'user@localhost' ], false ],
	[ [ 'HTTP_HOST' => 'localhost:99999' ], false ],
	[ [ 'HTTP_ORIGIN' => 'https://localhost' ], false ],
	[ [ 'HTTP_ORIGIN' => 'null' ], false ],
	[ [ 'HTTP_ORIGIN' => '/relative' ], false ],
	[ [ 'HTTP_ORIGIN' => 'http://localhost http://other.test' ], false ],
	[ [ 'HTTP_ORIGIN' => 'http://user@localhost' ], false ],
	[ [ 'HTTP_ORIGIN' => "http://localhost\n" ], false ],
	[ [ 'HTTP_ORIGIN' => 'http://localhost\\other' ], false ],
	[ [ 'HTTP_ORIGIN' => [] ], false ],
	[ [ 'HTTP_HOST' => [] ], false ],
	[ [ 'HTTP_X_FORWARDED_HOST' => 'other.test', 'HTTP_ORIGIN' => 'http://other.test' ], false ],
] as $case ) {
	checkRequest( ( new RequestOrigin( array_replace( $base, $case[0] ) ) )->isSameOrigin() === $case[1], 'Origin case: ' . json_encode( $case[0] ) );
}
$snapshot = new RequestOrigin( $base ); $base['HTTP_HOST'] = 'other.test';
checkRequest( $snapshot->isSameOrigin() && ! $snapshot->isSecure(), 'Independent request snapshot' );
$session = new FixtureSession(); $counter = 0;
$csrf = new CsrfToken( $session, static function ( int $length ) use ( &$counter ): string {
	checkRequest( $length === 32, '256-bit token entropy requested' ); return str_repeat( chr( ++$counter ), $length );
} );
checkRequest( $session->starts === 0 && $counter === 0, 'Construction has no session or random side effects' );
$first = $csrf->token();
checkRequest( strlen( $first ) === 64 && preg_match( '/^[0-9a-f]+$/', $first ) === 1 && $csrf->token() === $first && $counter === 1, 'Create once and reuse token' );
checkRequest( ! $csrf->verify( null ) && ! $csrf->verify( '' ) && ! $csrf->verify( 'wrong' ) && $csrf->token() === $first, 'Invalid tokens do not rotate' );
checkRequest( $csrf->verify( $first ) && $csrf->token() !== $first && ! $csrf->verify( $first ), 'Success rotates and rejects replay' );
$other = new FixtureSession(); $otherCsrf = new CsrfToken( $other );
checkRequest( ! $otherCsrf->verify( $csrf->token() ) && $other->data === [], 'Independent sessions do not accept each other tokens' );
$session->available = false;
checkRequest( $csrf->token() === '' && ! $csrf->verify( $session->data['csrf_token'] ), 'Unavailable session fails closed even with stale data' );
$session->available = true; $session->writable = false; $current = $session->data['csrf_token'];
checkRequest( ! $csrf->verify( $current ) && $session->data['csrf_token'] === $current, 'Failed rotation cannot accept token' );
$session->data = [];
checkRequest( $csrf->token() === '' && $session->data === [], 'Unstored new token is not exposed' );
$session->writable = true;
foreach ( [ '', [], 123, null ] as $value ) {
	$session->data['csrf_token'] = $value;
	checkRequest( ! $csrf->verify( '' ) && strlen( $csrf->token() ) === 64, 'Invalid stored token repaired, never accepted' );
}
$broken = new CsrfToken( $session, static function () { throw new RuntimeException( 'fixture entropy failure' ); } );
$current = $session->data['csrf_token'];
checkRequest( ! $broken->verify( $current ) && $session->data['csrf_token'] === $current, 'Entropy failure leaves existing token unchanged' );
$session->data = [];
checkRequest( $broken->token() === '' && $session->data === [], 'Entropy failure cannot expose token' );
$short = new CsrfToken( $session, static function () { return 'short'; } );
checkRequest( $short->token() === '', 'Injected generator must return 32 bytes' );

// No output until native session/headers checks have finished.
$root = sys_get_temp_dir() . '/ampboard-session-' . bin2hex( random_bytes( 8 ) ); mkdir( $root, 0700 );
session_save_path( $root ); ini_set( 'session.gc_probability', '0' );
$native = new NativeSession( true );
checkRequest( session_status() === PHP_SESSION_NONE && ! $native->write( 'csrf_token', 'unstarted' ), 'Native constructor and writes cannot start session' );
checkRequest( $native->start(), 'Native session starts' );
$params = session_get_cookie_params();
checkRequest( $params['secure'] && $params['httponly'] && $params['samesite'] === 'Lax', 'Existing cookie policy retained' );
$tokens = new CsrfToken( $native ); $token = $tokens->token(); $id = session_id();
checkRequest( $native->regenerate() && session_id() !== $id && $tokens->token() === $token, 'ID regeneration retains token' );
session_write_close();
checkRequest( $tokens->token() === $token, 'Token survives closing and reopening native storage' );
require __DIR__ . '/../config/helpers/security.php';
$_SERVER = [ 'HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost:8080' ];
checkRequest( request_is_same_origin() && csrf_get_token() === $token && csrf_verify( $token ) && ! $tokens->verify( $token ), 'Legacy wrappers share session and policies' );
session_destroy();
// A real start failure must not expose a token or leave an active session.
session_save_path( $root . '/missing' );
checkRequest( ( new CsrfToken( new NativeSession() ) )->token() === '' && session_status() !== PHP_SESSION_ACTIVE, 'Native startup failure' );
foreach ( glob( $root . '/*' ) as $file ) { unlink( $file ); } rmdir( $root );
echo "PASS request origins, session boundaries and CSRF rotation\n";
checkRequest( ! ( new NativeSession() )->start(), 'Headers already sent fails closed' );

foreach ( [ 'valid', 'origin', 'method', 'key' ] as $scenario ) {
	passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/read-config-request.php' ) . ' ' . escapeshellarg( $scenario ), $code );
	checkRequest( $code === 0, 'Read config handler ' . $scenario );
}
