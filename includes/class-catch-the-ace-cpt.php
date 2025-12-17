<?php
/**
 * Custom post type registration for Catch the Ace sessions.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceCpt {

	/**
	 * Boot hooks.
	 */
	public function __construct() {
		\add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public function register(): void {
		$labels = array(
			'name'                  => \__( 'Sessions', 'ace-the-catch' ),
			'singular_name'         => \__( 'Session', 'ace-the-catch' ),
			'menu_name'             => \__( 'Catch the Ace', 'ace-the-catch' ),
			'name_admin_bar'        => \__( 'Session', 'ace-the-catch' ),
			'add_new'               => \__( 'Add New Session', 'ace-the-catch' ),
			'add_new_item'          => \__( 'Add New Session', 'ace-the-catch' ),
			'new_item'              => \__( 'New Session', 'ace-the-catch' ),
			'edit_item'             => \__( 'Edit Session', 'ace-the-catch' ),
			'view_item'             => \__( 'View Session', 'ace-the-catch' ),
			'all_items'             => \__( 'All Sessions', 'ace-the-catch' ),
			'search_items'          => \__( 'Search Sessions', 'ace-the-catch' ),
			'not_found'             => \__( 'No sessions found.', 'ace-the-catch' ),
			'not_found_in_trash'    => \__( 'No sessions found in Trash.', 'ace-the-catch' ),
		);

		$menu_icon = 'data:image/svg+xml;base64,' . \base64_encode(
			"<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='black'><path d='M12 2c-2.9 4-8 7-8 11a4 4 0 004 4c1.6 0 3-.8 3.6-2h.8c.6 1.2 2 2 3.6 2a4 4 0 004-4c0-4-5.1-7-8-11zm-1 18v2h2v-2z'/></svg>"
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'has_archive'        => false,
			'exclude_from_search'=> true,
			'rewrite'            => false,
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			// Simple spade icon as a data URI (keeps menu light without bundling card art).
			'menu_icon'          => $menu_icon,
		);

		\register_post_type( 'catch-the-ace', $args );
	}
}
