<?php
require_once __DIR__ . '/../autoload.php';
/** Compatibility wrappers for trusted executable profiles and custom integrations. */
function build_url_name( $folderName, array $column, array &$errors ): string {
	$result = ( new \AMPBoard\Folders\UrlRules() )->apply( (string) $folderName, $column );
	foreach ( $result['errors'] as $error ) { $errors[] = htmlspecialchars( $error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	return $result['name'] ?? '__SKIP__';
}
function resolve_template_html( $templateName, array $templatesByName ): string {
	return \AMPBoard\Folders\LinkTemplates::resolve( (string) $templateName, $templatesByName );
}
function render_item_html( $templateHtml, $urlName, $disableLinks ): string {
	return \AMPBoard\Folders\LinkTemplates::render( (string) $templateHtml, (string) $urlName, (bool) $disableLinks );
}
function extract_template_hosts_for_url( string $templateHtml, string $urlName ): array {
	return \AMPBoard\Folders\LinkTemplates::hosts( $templateHtml, $urlName );
}
