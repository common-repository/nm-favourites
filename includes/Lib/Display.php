<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class Display {

	public static function run() {
		if ( is_admin() ) {
			return;
		}

		add_filter( 'the_content', [ __CLASS__, 'the_content' ] );
		add_filter( 'comment_text', [ __CLASS__, 'comment_text' ] );
		add_filter( 'comment_reply_link', [ __CLASS__, 'comment_text' ] );
		add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'product_button' ], -1 );
	}

	private static function button_positions( $object_type, $display_name = 'display_1' ) {
		$fields = nm_favourites()->category_button_fields( '', $object_type, $display_name );
		return array_filter( array_flip( $fields->button_positions() ) );
	}

	private static function get_buttons_positions( $object_type, $display_name = 'display_1' ) {
		$post_type = get_post_type();
		$positions = [];

		foreach ( get_option( 'nm_favourites_button_settings', [] ) as $btn_id => $btn_data ) {
			if ( !empty( $btn_data[ 'enabled' ] ) &&
				in_array( $post_type, ($btn_data[ 'post_types' ] ?? [] ), true ) &&
				in_array( ($btn_data[ 'display' ][ $display_name ][ 'position' ] ?? '' ),
					self::button_positions( $object_type, $display_name ) ) ) {
				// Add order key to root of button data so that we can sort easily.
				$btn_data[ 'order' ] = $btn_data[ 'display' ][ $display_name ][ 'order' ] ?? '';
				$positions[ $btn_data[ 'display' ][ $display_name ][ 'position' ] ][ $btn_id ] = $btn_data;
			}
		}
		return $positions;
	}

	private static function get_buttons_by_data( $button_datas, $wrap = false, $echo = false ) {
		(new Fields())->sort_by_order( $button_datas );
		$buttons_array = array_map( function ( $key ) {
			$button = nm_favourites()->button( $key, true );
			return $button->get();
		}, array_keys( $button_datas ) );

		$buttons = implode( '', $buttons_array );
		$val = $wrap ? '<div class="nm_favourites_btns_wrapper">' . $buttons . '</div>' : $buttons;

		if ( $echo ) {
			echo wp_kses( $val, nm_favourites()->settings()->allowed_post_tags() );
		} else {
			return wp_kses( $val, nm_favourites()->settings()->allowed_post_tags() );
		}
	}

	public static function the_content( $content ) {
		if ( get_the_ID() !== ( int ) get_option( 'page_on_front' ) ) {
			foreach ( self::get_buttons_positions( 'post' ) as $position => $buttons_data ) {
				$wrapped_buttons = self::get_buttons_by_data( $buttons_data, true );
				switch ( $position ) {
					case 'before_content':
						$content = $wrapped_buttons . $content;
						break;
					case 'after_content':
						$content = $content . $wrapped_buttons;
						break;
				}
			}
		}
		return $content;
	}

	public static function comment_text( $content ) {
		foreach ( self::get_buttons_positions( 'comment' ) as $position => $buttons_data ) {
			switch ( $position ) {
				case 'before_comment':
					if ( doing_filter( 'comment_text' ) ) {
						$wrapped_buttons = self::get_buttons_by_data( $buttons_data, true );
						$content = $wrapped_buttons . $content;
					}
					break;
				case 'after_comment':
					if ( doing_filter( 'comment_text' ) ) {
						$wrapped_buttons = self::get_buttons_by_data( $buttons_data, true );
						$content = $content . $wrapped_buttons;
					}
					break;
				case 'comment_reply_link':
					if ( doing_filter( 'comment_reply_link' ) ) {
						$buttons = self::get_buttons_by_data( $buttons_data );
						$margined_btns = '<span style="margin-right:20px">' . $buttons . '</span>';
						$content = $margined_btns . $content;
					}
					break;
			}
		}

		return $content;
	}

	public static function product_button() {
		foreach ( self::get_buttons_positions( 'product' ) as $position => $buttons_data ) {
			if ( 0 === strpos( $position, 'woocommerce_' ) ) {
				add_action( $position, function () use ( $buttons_data ) {
					self::get_buttons_by_data( $buttons_data, false, true );
				} );
			} elseif ( is_numeric( $position ) ) {
				add_action( 'woocommerce_single_product_summary', function () use ( $buttons_data ) {
					self::get_buttons_by_data( $buttons_data, false, true );
				}, ( int ) $position );
			}
		}
	}

}
