<?php
/**
 * Template manager for Catch the Ace CPT.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class TemplateManager {

	public function __construct() {
		\add_filter( 'template_include', array( $this, 'maybe_use_plugin_template' ) );
		\add_action( 'init', array( $this, 'add_winners_rewrite' ) );
		\add_action( 'init', array( $this, 'add_checkout_rewrite' ) );
		\add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		\add_action( 'template_redirect', array( $this, 'maybe_disable_cache' ), 0 );
	}

	/**
	 * Register a winners endpoint only for the Catch the Ace CPT.
	 *
	 * @return void
	 */
	public function add_winners_rewrite(): void {
		\add_rewrite_rule(
			'^catch-the-ace/([^/]+)/winners/?$',
			'index.php?post_type=catch-the-ace&name=$matches[1]&view_winners=1',
			'top'
		);
	}

	/**
	 * Register a checkout endpoint only for the Catch the Ace CPT.
	 *
	 * @return void
	 */
	public function add_checkout_rewrite(): void {
		\add_rewrite_rule(
			'^catch-the-ace/([^/]+)/checkout/?$',
			'index.php?post_type=catch-the-ace&name=$matches[1]&view_checkout=1',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'view_winners';
		$vars[] = 'view_checkout';
		return $vars;
	}

	/**
	 * Use plugin templates for the Catch the Ace CPT when the theme doesn't provide overrides.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function maybe_use_plugin_template( string $template ): string {
		if ( \is_post_type_archive( 'catch-the-ace' ) ) {
			return $this->resolve_template( 'archive-catch-the-ace.php', $template );
		}

		if ( \is_singular( 'catch-the-ace' ) ) {
			$view_winners = \get_query_var( 'view_winners' );
			$view_checkout = \get_query_var( 'view_checkout' );
			if ( '' !== $view_checkout ) {
				return $this->resolve_template( 'single-catch-the-ace__checkout.php', $template );
			}
			if ( '' !== $view_winners ) {
				return $this->resolve_template( 'single-catch-the-ace__winners.php', $template );
			}

			return $this->resolve_template( 'single-catch-the-ace.php', $template );
		}

		return $template;
	}

	/**
	 * Disable page caching for dynamic checkout requests.
	 *
	 * @return void
	 */
	public function maybe_disable_cache(): void {
		if ( ! \is_singular( 'catch-the-ace' ) ) {
			return;
		}

		$view_checkout = \get_query_var( 'view_checkout' );
		if ( '' === $view_checkout ) {
			return;
		}

		if ( ! \defined( 'DONOTCACHEPAGE' ) ) {
			\define( 'DONOTCACHEPAGE', true );
		}
		if ( ! \defined( 'DONOTCACHEOBJECT' ) ) {
			\define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! \defined( 'DONOTMINIFY' ) ) {
			\define( 'DONOTMINIFY', true );
		}

		\nocache_headers();
	}

	/**
	 * Resolve template path with theme override support.
	 *
	 * @param string $filename Template filename.
	 * @param string $fallback Current template.
	 * @return string
	 */
	private function resolve_template( string $filename, string $fallback ): string {
		$theme_template = \locate_template( $filename );
		if ( $theme_template ) {
			return $theme_template;
		}

		$plugin_template = LOTTO_PATH . 'templates/' . $filename;
		if ( \file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $fallback;
	}
}
