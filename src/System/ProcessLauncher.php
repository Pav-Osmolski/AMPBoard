<?php
namespace AMPBoard\System;

interface ProcessLauncher {
	/** Arguments are passed separately, without a shell. True means the launcher exited successfully. */
	public function launch( array $arguments ): bool;
}
