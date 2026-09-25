<?php

namespace AMPBoard\Apache;

/** Boundary for trusted, application-built shell commands; never pass raw request input. */
interface CommandRunner {
	/** @return array{output:string,success:bool} */
	public function run( string $command ): array;
}
