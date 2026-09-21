<?php
/**
 * Semantic name → inline RemixIcon line path.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Icons', false ) ) {
	/**
	 * Product plugins call svg( 'help' ). Do not pass remote SVG.
	 */
	class Wenpai_Admin_Icons {

		/**
		 * @param string $name  Semantic key from icons/map.json.
		 * @param string $class CSS class, default wenpai-ico.
		 * @return string Safe HTML.
		 */
		public static function svg( $name, $class = 'wenpai-ico' ) {
			$dir = function_exists( 'wenpai_admin_ui_winner_dir' ) ? wenpai_admin_ui_winner_dir() : dirname( __DIR__ );
			$file = $dir . '/icons/paths.php';
			$paths = is_readable( $file ) ? include $file : array();
			if ( ! is_array( $paths ) ) {
				$paths = array();
			}
			$d = isset( $paths[ $name ] ) ? $paths[ $name ] : ( isset( $paths['info'] ) ? $paths['info'] : '' );
			if ( '' === $d ) {
				return '';
			}
			return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' . esc_attr( $d ) . '"/></svg>';
		}
	}
}
