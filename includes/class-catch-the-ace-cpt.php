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
		// Print the admin icon CSS late in the head to avoid sending output before redirects/headers.
		\add_action( 'admin_head', array( $this, 'add_icon' ) );
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
		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'has_archive'        => 'catch-the-ace',
			'exclude_from_search'=> false,
			'rewrite'            => array(
				'slug'       => 'catch-the-ace',
				'with_front' => false,
			),
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			'menu_icon'          => 'none', //we fill this later
		);

		\register_post_type( 'catch-the-ace', $args );
	}

	public function add_icon() : void {
		$icon_path = plugin_dir_path( LOTTO_FILE ) . '/assets/images/spade.svg';
		$svg = file_get_contents( $icon_path );
		$data_uri = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);

		echo '<style>
			/* Your menu slug: toplevel_page_{menu_slug} */
			#adminmenu .menu-icon-catch-the-ace .wp-menu-image {
			color: #a7aaad; /* default WP icon color */
			}

			#adminmenu .menu-icon-catch-the-ace .wp-menu-image:before {
			content: "";
			display: block;
			width: 20px;
			height: 20px;
			margin: 0 auto; 
			background-color: currentColor;

			-webkit-mask: url("'.$data_uri.'") no-repeat 50% 50%;
			mask: url("'.$data_uri.'") no-repeat 50% 50%;
			-webkit-mask-size: 20px 20px;
			mask-size: 20px 20px;
			}

			/* Hover/focus */
			#adminmenu li.menu-icon-catch-the-ace:hover .wp-menu-image,
			#adminmenu li.menu-icon-catch-the-ace > a:focus .wp-menu-image {
			color: #fff;
			}

			/* Current / active */
			#adminmenu li.current.menu-icon-catch-the-ace .wp-menu-image,
			#adminmenu li.wp-has-current-submenu.menu-icon-catch-the-ace .wp-menu-image {
			color: #fff;
			}
		</style>';
	}
}
