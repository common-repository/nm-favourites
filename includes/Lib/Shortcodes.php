<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Lib\User;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	public function run() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'init', [ $this, 'add' ] );
		add_action( 'init', [ $this, 'delete_user_category' ] );
	}

	public function add() {
		add_shortcode( 'nm_favourites', [ $this, 'button' ] );
	}

	/**
	 * [nm_favourites id=1 object_id=2] // for user
	 * [nm_favourites id=1 object_id=2 show=users per_page=10]
	 * [nm_favourites id=1 show=tags per_page=10]
	 * [nm_favourites id=1 show=most_tagged limit=10]
	 * [nm_favourites] // for user
	 * [nm_favourites show=categories per_page=10] // for user
	 *
	 * per_page controls pagination. Default is 10 items per page.
	 * limit controls how many items you want to display in total.
	 */
	public function button( $atts ) {
		if ( !nm_favourites()->is_pro && !empty( $atts ) ) {
			unset( $atts[ 'per_page' ] );
		}

		if ( !empty( $atts[ 'id' ] ) ) {
			$id = $atts[ 'id' ];
			$object_id = $atts[ 'object_id' ] ?? true;

			if ( empty( $atts[ 'show' ] ) ) {
				$button = nm_favourites()->button( $id, $object_id );
				$template = $button->get();
			} else {
				if ( !(new User)->is_valid() ) {
					$template = $this->get_login_notice();
				} else {
					switch ( $atts[ 'show' ] ) {
						case 'tags':
							$template = nm_favourites()->category( $id, $this->get_current_user_id() )->get_user_tags_template( $atts );
							break;
						case 'most_tagged':
							$template = nm_favourites()->category( $id )->get_most_tagged_template( $atts );
							break;
						case 'users':
							$template = nm_favourites()->button( $id, $object_id )->tag()->get_users_template( $atts );
							break;
					}
				}
			}
		} else {
			// Showing user categories and tags template
			if ( empty( $atts[ 'show' ] ) || 'categories' === $atts[ 'show' ] ) {
				$template = '';

				if ( $this->get_current_category_id() ) {
					$category = nm_favourites()->category( $this->get_current_category_id(), $this->get_current_user_id() );
					$template .= $category->get_user_tags_template( $atts );
				} else {
					if ( !(new User)->is_valid() ) {
						$template = $this->get_login_notice();
					} else {
						$template .= nm_favourites()->category( '', $this->get_current_user_id() )->get_user_categories_template( $atts );
					}
				}
			}
		}

		return $template ?? '';
	}

	private function get_current_category_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( int ) wp_unslash( $_GET[ 'nm_favourites_category' ] ?? 0 );
	}

	private function get_current_user_id() {
		if ( isset( $_GET[ 'user_id' ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = sanitize_text_field( wp_unslash( $_GET[ 'user_id' ] ) );
		} else {
			$user_id = (new User())->get_id();
		}
		return $user_id;
	}

	private function get_login_notice() {
		return wpautop( nm_favourites()->settings()->get_option()[ 'guest_message' ] ?? '' );
	}

	// Delete user category via http on favourites category page
	public function delete_user_category() {
		$cat_id = $this->get_current_category_id();
		if ( $cat_id && 'delete' === sanitize_text_field( $_GET[ 'action' ] ?? [] ) ) {
			if ( check_admin_referer( "delete_cat_$cat_id" ) ) {
				$category = nm_favourites()->category( $cat_id );
				if ( $category->is_user_own() && $category->delete_with_tags() ) {
					wp_safe_redirect( remove_query_arg( [ 'nm_favourites_category', 'action', '_wpnonce' ] ) );
					exit;
				}
			}
		}
	}

}
