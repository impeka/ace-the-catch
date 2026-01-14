<?php
/**
 * Benefactors taxonomy for Catch the Ace sessions.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceBenefactors {

	public const TAXONOMY = 'cta_benefactor';

	public function __construct() {
		\add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the Benefactors taxonomy for session posts.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => \__( 'Benefactors', 'ace-the-catch' ),
			'singular_name'              => \__( 'Benefactor', 'ace-the-catch' ),
			'search_items'               => \__( 'Search Benefactors', 'ace-the-catch' ),
			'all_items'                  => \__( 'All Benefactors', 'ace-the-catch' ),
			'edit_item'                  => \__( 'Edit Benefactor', 'ace-the-catch' ),
			'update_item'                => \__( 'Update Benefactor', 'ace-the-catch' ),
			'add_new_item'               => \__( 'Add New Benefactor', 'ace-the-catch' ),
			'new_item_name'              => \__( 'New Benefactor Name', 'ace-the-catch' ),
			'menu_name'                  => \__( 'Benefactors', 'ace-the-catch' ),
		);

		$args = array(
			'labels'            => $labels,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'rewrite'           => false,
			'query_var'         => false,
		);

		\register_taxonomy( self::TAXONOMY, array( 'catch-the-ace' ), $args );
	}
}

