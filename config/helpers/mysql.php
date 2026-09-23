<?php

/**
 * Normalise MySQL-family version strings from mysqli->server_info.
 *
 * Examples:
 * - "10.11.7-MariaDB-1:10.11.7+maria~deb11-log"  -> "10.11.7-MariaDB"
 * - "8.0.36-0.el9"                               -> "8.0.36"
 * - "5.7.42-cll-lve"                             -> "5.7.42"
 * - "8.0.36-28"          (Percona)               -> "8.0.36-Percona"
 * - "8.0.33-ndb-8.0.33"  (MySQL Cluster)         -> "8.0.33-ndb"
 * - "5.7.mysql_aurora.2.11.1" (Aurora)           -> "Aurora MySQL 5.7 (2.11.1)"
 *
 * @param string $serverInfo
 *
 * @return string
 */
function normaliseDbServerInfo( string $serverInfo ): string {
	$info = trim( $serverInfo );

	// Aurora MySQL: 5.7.mysql_aurora.2.11.1 or 8.0.32.amazon_aurora.3.04.0
	if ( preg_match( '/^(?<base>\d+\.\d+)\.(?:mysql_aurora|amazon_aurora)\.(?<track>[\d.]+)/i', $info, $m ) ) {
		return "Aurora MySQL {$m['base']} ({$m['track']})";
	}

	// MySQL NDB Cluster: 8.0.33-ndb-8.0.33  -> keep "8.0.33-ndb"
	if ( preg_match( '/^(?<ver>\d+(?:\.\d+){1,3})-ndb(?:-[\d.]+)?/i', $info, $m ) ) {
		return "{$m['ver']}-ndb";
	}

	// Percona: 8.0.36-28[-anything] -> "8.0.36-Percona"
	// Heuristic: version-<release>, and version_comment will say Percona, but we infer here.
	if ( stripos( $info, 'percona' ) !== false || preg_match( '/^\d+(?:\.\d+){1,3}-\d+(?:-[A-Za-z0-9_.-]+)?$/', $info ) ) {
		if ( preg_match( '/^(?<ver>\d+(?:\.\d+){1,3})-\d+/', $info, $m ) ) {
			return "{$m['ver']}-Percona";
		}
	}

	// MariaDB: keep "X.Y[.Z]-MariaDB" and drop packaging/log tails.
	if ( stripos( $info, 'MariaDB' ) !== false ) {
		if ( preg_match( '/^(?<ver>\d+(?:\.\d+){1,3})-MariaDB\b/i', $info, $m ) ) {
			return "{$m['ver']}-MariaDB";
		}
	}

	// Plain MySQL with distro clutter (Debian, Ubuntu, EL, Amazon, CloudLinux, -log, etc.)
	// Keep leading X.Y[.Z], optionally a short edition token like -ndb handled earlier.
	if ( preg_match( '/^(?<ver>\d+(?:\.\d+){1,3})\b/i', $info, $m ) ) {
		return $m['ver'];
	}

	// Fallback
	return $info;
}
