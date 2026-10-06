<?php
namespace AMPBoard\Ui;

use AMPBoard\Filesystem\DirectoryCatalog;
use AMPBoard\Folders\LinkTemplates;
use AMPBoard\Folders\UrlRules;

/** Prepares the folder panel from supplied profile snapshots and lazy discovery. */
final class FolderPresenter {
	private array $columns;
	private LinkTemplates $templates;
	private DirectoryCatalog $directories;
	private \Closure $validHost;
	private UrlRules $rules;
	public function __construct( array $columns, LinkTemplates $templates, DirectoryCatalog $directories, callable $validHost ) {
		$this->columns = $columns; $this->templates = $templates; $this->directories = $directories;
		$this->validHost = \Closure::fromCallable( $validHost ); $this->rules = new UrlRules();
	}
	public function prepare(): array {
		$view = [ 'empty' => null, 'columns' => [], 'errors' => [], 'hasVhostFilteredColumns' => false ];
		if ( $this->columns === [] || ! $this->templates->hasTemplates() ) {
			$view['empty'] = $this->columns === [] ? ( $this->templates->hasTemplates() ? 'folders' : 'both' ) : 'templates';
			return $view;
		}
		foreach ( $this->columns as $column ) {
			if ( ! is_array( $column ) ) { $view['errors'][] = 'Column configuration must be an object.'; continue; }
			$title = isset( $column['title'] ) ? (string) $column['title'] : 'Untitled';
			$requireVhost = ! empty( $column['requireVhost'] );
			$view['hasVhostFilteredColumns'] = $view['hasVhostFilteredColumns'] || $requireVhost;
			$normal = $this->directories->resolve( $column['dir'] ?? '' ); $dir = $normal['dir'];
			if ( $normal['error'] !== null ) { $view['errors'][] = $normal['error'] . ' (Column: ' . $title . ')'; }
			$folders = $dir ? $this->directories->listDirectories( $dir ) : [];
			$prepared = [ 'title' => $title, 'href' => isset( $column['href'] ) ? (string) $column['href'] : '',
				'disable' => ! empty( $column['disableLinks'] ), 'requireVhost' => $requireVhost, 'dir' => $dir,
				'state' => ! $dir || ! is_dir( $dir ) ? 'missing' : ( $folders === [] ? 'empty' : 'ready' ), 'items' => [] ];
			$template = $this->templates->html( isset( $column['linkTemplate'] ) ? (string) $column['linkTemplate'] : 'basic' );
			$exclude = isset( $column['excludeList'] ) && is_array( $column['excludeList'] ) ? $column['excludeList'] : [];
			foreach ( $folders as $folder ) {
				if ( in_array( $folder, $exclude, true ) ) { continue; }
				$result = $this->rules->apply( $folder, $column );
				if ( $result['name'] === null ) { continue; }
				foreach ( $result['errors'] as $error ) { $view['errors'][] = $error; }
				$name = $result['name'];
				if ( $requireVhost ) {
					$valid = false;
					foreach ( LinkTemplates::hosts( $template, $name ) as $host ) {
						if ( ( $this->validHost )( $host ) ) { $valid = true; break; }
					}
					if ( ! $valid ) { continue; }
				}
				$prepared['items'][] = [ 'folder' => $folder, 'name' => $name, 'html' => LinkTemplates::render( $template, $name, $prepared['disable'] ) ];
			}
			$view['columns'][] = $prepared;
		}
		$view['errors'] = array_values( array_unique( $view['errors'] ) );
		return $view;
	}
}
