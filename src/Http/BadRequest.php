<?php
namespace AMPBoard\Http;

/** Existing settings-error response: public generic text, details in the server log. */
final class BadRequest {
	public static function send( string $message ): void {
		http_response_code( 400 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo 'Bad request.';
		error_log( '[submit.php] ' . $message );
		exit;
	}
}
