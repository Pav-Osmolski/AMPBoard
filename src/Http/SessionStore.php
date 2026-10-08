<?php
namespace AMPBoard\Http;

/** Session operations needed by forms; implementations must report unavailable storage. */
interface SessionStore {
	public function start(): bool;
	public function read( string $key );
	public function write( string $key, $value ): bool;
	public function regenerate(): bool;
}
