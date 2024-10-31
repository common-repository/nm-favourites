<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Settings\CategoriesTags;

defined( 'ABSPATH' ) || exit;

class Wizard {

	public static function init() {
		if ( !get_option( 'nm_favourites_version' ) && !get_option( 'nm_favourites_pro_version' ) ) {
			set_transient( 'nm_favourites_wizard', 1, HOUR_IN_SECONDS );
			add_option( 'nm_favourites_wizard_notice', 1, '', false );
		}
	}

	public static function run() {
		add_action( 'init', [ __CLASS__, 'create_buttons' ], 99 );
		add_action( 'admin_notices', [ __CLASS__, 'show_notice' ] );
		add_action( 'admin_init', array( __CLASS__, 'delete_wizard_notice_option' ) );
	}

	public static function create_buttons() {
		if ( get_transient( 'nm_favourites_wizard' ) ) {
			delete_transient( 'nm_favourites_wizard' );

			// Get the buttons that have been saved by icon and category_id
			$saved_buttons = [];

			foreach ( self::buttons() as $button_data ) {
				$category_id = (new CategoriesTags())->save_category( $button_data );
				if ( $category_id ) {
					$icon = ($button_data[ 'display' ][ 'display_1' ][ 'icon' ] ?? '');
					$icon ? ($saved_buttons[ $icon ] = $category_id) : '';
				}
			}

			foreach ( $saved_buttons as $cat_icon => $cat_id ) {
				/**
				 * We want to set the toggle_with property of the like and dislike buttons but we can only
				 * do this after they have been saved which is when their category id is available.
				 * The toggle_With property is set with the category id of the button to toggle with.
				 */
				if ( in_array( $cat_icon, [ 'like', 'dislike' ] ) ) {
					$cat = nm_favourites()->category( $cat_id );
					$meta = $cat->get_meta();

					if ( 'like' === $cat_icon && !empty( $saved_buttons[ 'dislike' ] ) ) {
						$meta[ 'toggle_with' ][] = $saved_buttons[ 'dislike' ];
					} elseif ( 'dislike' === $cat_icon && !empty( $saved_buttons[ 'like' ] ) ) {
						$meta[ 'toggle_with' ][] = $saved_buttons[ 'like' ];
					}

					$cat->set_meta( $meta );
					$cat->save();
				}
			}
		}
	}

	public static function show_notice() {
		if ( !get_option( 'nm_favourites_wizard_notice' ) ) {
			return;
		}

		$notice1 = __( 'Some default buttons have been created for you. Feel free to use them, modify them or delete them and create your own.', 'nm-favourites' );

		$notice2 = __( 'Set up a page for users to see their favourites.', 'nm-favourites' );
		$settings_url = nm_favourites()->settings()->get_page_url();
		$settings_text = nm_favourites()->settings()->get_heading_text();
		$notice2 .= ' <a href="' . $settings_url . '">' . $settings_text . '</a>';
		$dismiss_text = __( 'Dismiss Permanently', 'nm-favourites' );

		echo '<div class="notice-info notice">' .
		'<p><strong>' . nm_favourites()->name . '</strong></p>' .
		'<p>' . esc_html( $notice1 ) . '</p>' .
		'<p>' . wp_kses_post( $notice2 ) . '</p>' .
		'<p><a href="' . add_query_arg( 'nm_favourites_dismiss_notice', 1 ) . '">' . esc_html( $dismiss_text ) . '</a></p>' .
		'</div>';
	}

	public static function delete_wizard_notice_option() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( !empty( $_GET[ 'nm_favourites_dismiss_notice' ] ) ) {
			delete_option( 'nm_favourites_wizard_notice' );
		}
	}

	public static function buttons() {
		$buttons = [
			[
				'name' => __( 'Favourites', 'nm-favourites' ),
				'enabled' => 1,
				'object_type' => 'post',
				'post_types' => [
					'post',
					'page',
				],
				'display' => [
					'display_1' => [
						'position' => 'before_content',
						'icon' => 'star',
						'text_before' => __( 'Favourite', 'nm-favourites' ),
						'text_after' => __( 'Favourite', 'nm-favourites' ),
						'title_attr_before' => __( 'Favourite', 'nm-favourites' ),
						'title_attr_after' => __( 'Favourite', 'nm-favourites' ),
						'show_count' => 1,
						'order' => '',
						'html_tag' => 'span',
					],
				],
			],
			[
				'name' => __( 'Liked Comments', 'nm-favourites' ),
				'enabled' => 1,
				'toggle_with' => [],
				'object_type' => 'comment',
				'post_types' => [
					'post',
					'page',
				],
				'display' => [
					'display_1' => [
						'position' => 'comment_reply_link',
						'icon' => 'like',
						'text_before' => '',
						'text_after' => '',
						'title_attr_before' => __( 'Like', 'nm-favourites' ),
						'title_attr_after' => __( 'Like', 'nm-favourites' ),
						'show_count' => 1,
						'order' => 0,
						'html_tag' => 'span',
					],
				],
			],
			[
				'name' => __( 'Disliked Comments', 'nm-favourites' ),
				'enabled' => 1,
				'toggle_with' => [],
				'object_type' => 'comment',
				'post_types' => [
					'post',
					'page',
				],
				'display' => [
					'display_1' => [
						'position' => 'comment_reply_link',
						'icon' => 'dislike',
						'text_before' => '',
						'text_after' => '',
						'title_attr_before' => __( 'Dislike', 'nm-favourites' ),
						'title_attr_after' => __( 'Dislike', 'nm-favourites' ),
						'show_count' => 1,
						'order' => 1,
						'html_tag' => 'span',
					],
				],
			],
		];

		if ( class_exists( \WooCommerce::class ) && post_type_exists( 'product' ) ) {
			$buttons[] = [
				'name' => __( 'Wishlist', 'nm-favourites' ),
				'enabled' => 1,
				'object_type' => 'product',
				'post_types' => [
					'product',
				],
				'display' => [
					'display_1' => [
						'position' => '35',
						'icon' => 'heart',
						'text_before' => __( 'Add to wishlist', 'nm-favourites' ),
						'text_after' => __( 'In wishlist', 'nm-favourites' ),
						'title_attr_before' => __( 'Add to wishlist', 'nm-favourites' ),
						'title_attr_after' => __( 'In wishlist', 'nm-favourites' ),
						'show_count' => 1,
						'order' => '',
						'html_tag' => 'a',
					],
				],
			];
		}

		return $buttons;
	}

}
