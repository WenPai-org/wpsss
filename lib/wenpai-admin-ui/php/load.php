<?php
/**
 * Product plugins require this file only.
 *
 * Each vendor copy registers itself. plugins_loaded (priority 1) then
 * requires the highest VERSION. Do not require the class-*.php files
 * from the product plugin — that would declare classes from a losing copy.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wenpai_admin_ui_register' ) ) {
	/**
	 * @param string $version SemVer from VERSION file.
	 * @param string $dir     Absolute path to this kit copy (parent of php/).
	 */
	function wenpai_admin_ui_register( $version, $dir ) {
		if ( ! isset( $GLOBALS['wenpai_admin_ui_candidates'] ) || ! is_array( $GLOBALS['wenpai_admin_ui_candidates'] ) ) {
			$GLOBALS['wenpai_admin_ui_candidates'] = array();
		}
		$GLOBALS['wenpai_admin_ui_candidates'][] = array(
			'version' => (string) $version,
			'dir'     => rtrim( (string) $dir, '/\\' ),
		);
	}
}

if ( ! function_exists( 'wenpai_admin_ui_boot' ) ) {
	/**
	 * Load the newest candidate. Safe to call more than once.
	 */
	function wenpai_admin_ui_boot() {
		if ( ! empty( $GLOBALS['wenpai_admin_ui_booted'] ) ) {
			return;
		}

		$candidates = isset( $GLOBALS['wenpai_admin_ui_candidates'] ) && is_array( $GLOBALS['wenpai_admin_ui_candidates'] )
			? $GLOBALS['wenpai_admin_ui_candidates']
			: array();

		if ( array() === $candidates ) {
			return;
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				return version_compare( $b['version'], $a['version'] );
			}
		);

		$winner = $candidates[0];
		$GLOBALS['wenpai_admin_ui_winner'] = $winner;
		$GLOBALS['wenpai_admin_ui_booted'] = true;

		$dir = $winner['dir'] . '/php/';
		$files = array(
			'class-wenpai-admin-loader.php',
			'class-wenpai-admin-shell.php',
			'class-wenpai-admin-icons.php',
		);
		foreach ( $files as $file ) {
			$path = $dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}
}

if ( ! function_exists( 'wenpai_admin_ui_winner_dir' ) ) {
	/**
	 * @return string Empty before boot.
	 */
	function wenpai_admin_ui_winner_dir() {
		if ( empty( $GLOBALS['wenpai_admin_ui_winner']['dir'] ) ) {
			return '';
		}
		return (string) $GLOBALS['wenpai_admin_ui_winner']['dir'];
	}
}

wenpai_admin_ui_register(
	trim( (string) file_get_contents( dirname( __DIR__ ) . '/VERSION' ) ),
	dirname( __DIR__ )
);

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'wenpai_admin_ui_boot', 1 );
}
