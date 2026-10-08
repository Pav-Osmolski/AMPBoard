<?php
/**
 * Boolean helpers
 *
 * normalise_bool()
 *
 * @author  Pawel Osmolski
 * @version 1.1
 */

require_once __DIR__ . '/../autoload.php';

/**
 * Normalises a boolean from various HTML input forms.
 *
 * @param mixed $v
 *
 * @return string "true" or "false"
 */
function normalise_bool( mixed $v ): string {
	return \AMPBoard\Config\BooleanInput::value( $v ) ? 'true' : 'false';
}
