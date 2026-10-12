<?php
/**
 * Settings-plugin chrome: top bar, tabs, wrap, footer.
 * Strings are passed in by the product plugin (no kit text domain).
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Shell', false ) ) {
	/**
	 * Echoes markup. Product fills the inner pages.
	 */
	class Wenpai_Admin_Shell {

		/**
		 * @param array $args {
		 *     @type string $slug          Product slug (wpslug / wpsss).
		 *     @type string $name          Product display name (already translated).
		 *     @type string $mark_html     Product SVG for .wenpai-mark.
		 *     @type string $current       Current tab id.
		 *     @type array  $tabs          array of array{ id, label, url }.
		 *     @type string $help_url      Optional.
		 *     @type string $help_label    Optional, already translated.
		 *     @type string $feedback_url  Optional.
		 *     @type string $feedback_label Optional.
		 *     @type string $version       Plugin version string.
		 *     @type string $doc_url       Footer doc link.
		 *     @type string $doc_label     Footer doc label.
		 * }
		 */
		public static function open( $args ) {
			$slug = isset( $args['slug'] ) ? sanitize_html_class( $args['slug'] ) : '';
			$name = isset( $args['name'] ) ? $args['name'] : '';
			$mark = isset( $args['mark_html'] ) ? $args['mark_html'] : '';
			$current = isset( $args['current'] ) ? $args['current'] : '';
			$tabs = isset( $args['tabs'] ) && is_array( $args['tabs'] ) ? $args['tabs'] : array();

			echo '<div class="wenpai-app">';
			echo '<div class="wenpai-top"><div class="wenpai-head">';
			echo '<span class="wenpai-mark">' . $mark . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
			echo '<span class="wenpai-name">' . esc_html( $name ) . '</span>';
			echo '<nav class="wenpai-tabs" aria-label="' . esc_attr( $name ) . '">';
			foreach ( $tabs as $tab ) {
				$id    = isset( $tab['id'] ) ? $tab['id'] : '';
				$label = isset( $tab['label'] ) ? $tab['label'] : '';
				$url   = isset( $tab['url'] ) ? $tab['url'] : '';
				$cls   = 'wenpai-tab';
				if ( $id === $current ) {
					$cls .= ' is-active';
				}
				echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '</nav>';
			echo '<span class="wenpai-head-right">';
			if ( ! empty( $args['help_url'] ) ) {
				$hl = isset( $args['help_label'] ) ? $args['help_label'] : '帮助';
				echo '<a class="btn btn-ghost" href="' . esc_url( $args['help_url'] ) . '">' . esc_html( $hl ) . '</a>';
			}
			if ( ! empty( $args['feedback_url'] ) ) {
				$fl = isset( $args['feedback_label'] ) ? $args['feedback_label'] : '反馈';
				echo '<a class="btn btn-ghost" href="' . esc_url( $args['feedback_url'] ) . '">' . esc_html( $fl ) . '</a>';
			}
			echo '</span>';
			echo '</div></div>';
			echo '<div class="wenpai-wrap">';
			unset( $slug );
		}

		/**
		 * Close wrap + footer.
		 *
		 * @param array $args Same as open(); uses version / doc_url / doc_label.
		 */
		public static function close( $args = array() ) {
			$ver = isset( $args['version'] ) ? $args['version'] : '';
			$doc = isset( $args['doc_url'] ) ? $args['doc_url'] : '';
			$dl  = isset( $args['doc_label'] ) ? $args['doc_label'] : '';

			echo '</div>'; // .wenpai-wrap
			echo '<div class="wenpai-foot">';
			if ( '' !== $ver ) {
				echo '<span class="wenpai-foot-ver">' . esc_html( $ver ) . '</span>';
			}
			if ( '' !== $doc ) {
				echo '<a class="wenpai-foot-doc" href="' . esc_url( $doc ) . '">' . esc_html( $dl ) . '</a>';
			}
			echo '</div>';
			echo '</div>'; // .wenpai-app
		}

		/**
		 * Page heading inside the wrap.
		 *
		 * @param string $title Already translated.
		 * @param string $lede  Already translated. Optional.
		 * @param string $aside Optional HTML for the right of the heading (escaped by caller).
		 */
		public static function page_head( $title, $lede = '', $aside = '' ) {
			echo '<div class="wenpai-page-head">';
			echo '<div>';
			echo '<h1 class="wenpai-h1">' . esc_html( $title ) . '</h1>';
			if ( '' !== $lede ) {
				echo '<p class="wenpai-lede">' . esc_html( $lede ) . '</p>';
			}
			echo '</div>';
			if ( '' !== $aside ) {
				echo '<div class="wenpai-mode">' . $aside . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
			}
			echo '</div>';
		}
	}
}
