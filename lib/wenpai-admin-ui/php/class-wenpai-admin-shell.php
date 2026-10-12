<?php
/**
 * Settings-plugin chrome: top bar, tabs, wrap, footer.
 * Strings are passed in by the product plugin (no kit text domain).
 *
 * Markup matches prototypes: .wenpai-top > .wenpai-wrap > .wenpai-head,
 * then main.wenpai-main.wenpai-wrap, footer.foot.
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
		 *     @type string $slug            Product slug (wpslug / wpsss).
		 *     @type string $name            Product display name (already translated).
		 *     @type string $mark_html       Product SVG for .wenpai-mark.
		 *     @type string $home_url        Brand link. Defaults to first tab url.
		 *     @type string $current         Current tab id.
		 *     @type array  $tabs            array of array{ id, label, url, icon_html? }.
		 *     @type string $head_extra      Optional HTML after tabs (wizard exit). Caller escapes.
		 *     @type string $help_url        Optional.
		 *     @type string $help_label      Optional, already translated.
		 *     @type string $help_icon_html  Optional SVG, caller escapes.
		 *     @type string $feedback_url    Optional.
		 *     @type string $feedback_label  Optional.
		 *     @type string $feedback_icon_html Optional SVG.
		 *     @type string $version         Plugin version string.
		 *     @type string $doc_url         Footer doc link.
		 *     @type string $doc_label       Footer doc label.
		 * }
		 */
		public static function open( $args ) {
			$name    = isset( $args['name'] ) ? $args['name'] : '';
			$mark    = isset( $args['mark_html'] ) ? $args['mark_html'] : '';
			$current = isset( $args['current'] ) ? $args['current'] : '';
			$tabs    = isset( $args['tabs'] ) && is_array( $args['tabs'] ) ? $args['tabs'] : array();
			$home    = isset( $args['home_url'] ) ? $args['home_url'] : '';
			if ( '' === $home && isset( $tabs[0]['url'] ) ) {
				$home = $tabs[0]['url'];
			}

			echo '<div class="wenpai-app">';
			echo '<div class="wenpai-top"><div class="wenpai-wrap">';
			echo '<div class="wenpai-head">';
			echo '<a class="wenpai-brand" href="' . esc_url( $home ) . '">';
			echo '<span class="wenpai-mark">' . $mark . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
			echo '<span class="wenpai-name">' . esc_html( $name ) . '</span>';
			echo '</a>';
			if ( array() !== $tabs ) {
				echo '<nav class="wenpai-tabs" aria-label="' . esc_attr( $name ) . '">';
				foreach ( $tabs as $tab ) {
					$id    = isset( $tab['id'] ) ? $tab['id'] : '';
					$label = isset( $tab['label'] ) ? $tab['label'] : '';
					$url   = isset( $tab['url'] ) ? $tab['url'] : '';
					$icon  = isset( $tab['icon_html'] ) ? $tab['icon_html'] : '';
					$cls   = 'wenpai-tab';
					if ( $id === $current ) {
						$cls .= ' is-active';
					}
					echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">';
					echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
					echo esc_html( $label );
					echo '</a>';
				}
				echo '</nav>';
			}
			if ( ! empty( $args['head_extra'] ) ) {
				echo $args['head_extra']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
			}
			echo '<div class="wenpai-head-right">';
			if ( ! empty( $args['help_url'] ) ) {
				$hl  = isset( $args['help_label'] ) ? $args['help_label'] : '帮助';
				$hcl = 'btn btn-ghost';
				if ( 'help' === $current ) {
					$hcl .= ' is-active';
				}
				$hicon = isset( $args['help_icon_html'] ) ? $args['help_icon_html'] : '';
				echo '<a class="' . esc_attr( $hcl ) . '" href="' . esc_url( $args['help_url'] ) . '">';
				echo $hicon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
				echo esc_html( $hl );
				echo '</a>';
			}
			if ( ! empty( $args['feedback_url'] ) ) {
				$fl    = isset( $args['feedback_label'] ) ? $args['feedback_label'] : '反馈';
				$ficon = isset( $args['feedback_icon_html'] ) ? $args['feedback_icon_html'] : '';
				echo '<a class="btn btn-ghost" href="' . esc_url( $args['feedback_url'] ) . '">';
				echo $ficon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
				echo esc_html( $fl );
				echo '</a>';
			}
			echo '</div>';
			echo '</div></div></div>';
			echo '<main class="wenpai-main wenpai-wrap">';
		}

		/**
		 * Close main + footer.
		 *
		 * @param array $args Same as open(); uses version / doc_url / doc_label.
		 */
		public static function close( $args = array() ) {
			$ver = isset( $args['version'] ) ? $args['version'] : '';
			$doc = isset( $args['doc_url'] ) ? $args['doc_url'] : '';
			$dl  = isset( $args['doc_label'] ) ? $args['doc_label'] : '';

			echo '<footer class="foot">';
			if ( '' !== $ver ) {
				echo '<span>' . esc_html( $ver ) . '</span>';
			}
			if ( '' !== $doc ) {
				echo '<span class="r"><a href="' . esc_url( $doc ) . '">' . esc_html( $dl ) . '</a></span>';
			}
			echo '</footer>';
			echo '</main>';
			echo '</div>';
		}

		/**
		 * Page heading inside main.
		 *
		 * @param string $title Already translated.
		 * @param string $lede  Already translated. Optional.
		 * @param string $aside Optional HTML for .mode (escaped by caller).
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
				echo '<div class="mode">' . $aside . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
			}
			echo '</div>';
		}
	}
}
