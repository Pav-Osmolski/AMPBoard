<?php
namespace AMPBoard\Apache;

/** Native runtime observations used by the read-only Apache inspector. */
interface RuntimeReader {
	public function isApache(): bool;
	public function apacheVersion(): ?string;
	public function moduleInfo(): string;
	public function environment(): array;
	public function iniFiles(): array;
}
