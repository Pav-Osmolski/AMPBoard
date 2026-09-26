<?php
namespace AMPBoard\Export;

interface ProcessRunner {
	/** @return array{success:bool,output:string} Arguments are passed without a shell. */
	public function run( array $arguments, string $directory, string $input = '' ): array;
}
