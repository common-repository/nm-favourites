<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Tables\ObjectCountInCategoriesTable;

defined( 'ABSPATH' ) || exit;

class Admin {

	public static function run() {
		if ( !is_admin() ) {
			return;
		}

		$button_settings = get_option( 'nm_favourites_button_settings', [] );

		foreach ( $button_settings as $button ) {
			if ( in_array( ($button[ 'object_type' ] ?? '' ), nm_favourites_post_object_types() ) ) {
				foreach ( ($button[ 'post_types' ] ?? [] ) as $post_type ) {
					add_filter( "manage_{$post_type}_posts_columns", [ __CLASS__, 'posts_columns' ] );
					add_filter( "manage_edit-{$post_type}_sortable_columns", [ __CLASS__, 'posts_columns' ] );
					add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'column_content' ], 10, 2 );
					add_action( "add_meta_boxes_{$post_type}", [ __CLASS__, 'post_metabox' ] );
				}
			}

			if ( 'comment' === ($button[ 'object_type' ] ?? '') ) {
				add_filter( "manage_edit-comments_columns", [ __CLASS__, 'comments_columns' ] );
				add_action( "manage_comments_custom_column", [ __CLASS__, 'column_content' ], 10, 2 );
			}
		}

		add_filter( 'request', [ __CLASS__, 'orderby_favourites_count' ] );
	}

	public static function post_metabox( $post ) {
		if ( $post->ID !== ( int ) get_option( 'page_on_front' ) ) {
			add_meta_box(
				'nm_favourites_data',
				nm_favourites()->name,
				array( __CLASS__, 'metabox' ),
				get_post_type( $post ),
				'side'
			);
		}
	}

	public static function metabox( $post ) {
		$post_type = get_post_type( $post );
		$buttons = [];

		$settings = get_option( 'nm_favourites_button_settings', [] );
		foreach ( $settings as $button_id => $button_setting ) {
			if ( in_array( ($button_setting[ 'object_type' ] ?? '' ), nm_favourites_post_object_types() ) &&
				in_array( $post_type, $button_setting[ 'post_types' ] ?? [] ) ) {
				$button = nm_favourites()->button( $button_id, $post->ID );
				if ( $button->get_id() ) {
					if ( false === $button->is_object_enabled() ) {
						continue;
					}

					$buttons[] = $button;

					if ( method_exists( $button->category(), 'get_default_children' ) ) {
						$children = $button->category()->get_default_children();
						if ( !empty( $children ) ) {
							foreach ( $children as $child ) {
								$child_button = nm_favourites()->button( $child, $post->ID );
								$buttons[] = $child_button;
							}
						}
					}
				}
			}
		}

		$table = new ObjectCountInCategoriesTable( $post->ID, $buttons );
		$table->styles();
		$table->show();
	}

	public static function posts_columns( $columns ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = sanitize_text_field( wp_unslash( $_GET[ 'post_type' ] ?? 'post' ) );
		$button_settings = get_option( 'nm_favourites_button_settings', [] );
		foreach ( $button_settings as $button_id => $button ) {
			if ( in_array( ($button[ 'object_type' ] ?? '' ), nm_favourites_post_object_types() ) &&
				in_array( $post_type, ($button[ 'post_types' ] ?? [] ) ) ) {
				if ( doing_filter( "manage_{$post_type}_posts_columns" ) ) {
					$columns[ "nm_favourites_{$button[ 'category_slug' ]}" ] = $button[ 'category_name' ];
				} else {
					$columns[ "nm_favourites_{$button[ 'category_slug' ]}" ] = [ "nm_favourites_count_$button_id", 'desc' ];
				}
			}
		}
		return $columns;
	}

	public static function comments_columns( $columns ) {
		$button_settings = get_option( 'nm_favourites_button_settings', [] );
		foreach ( $button_settings as $button_id => $button ) {
			if ( 'comment' === ($button[ 'object_type' ] ?? '' ) ) {
				$columns[ "nm_favourites_{$button[ 'category_slug' ]}" ] = $button[ 'category_name' ];
			}
		}
		return $columns;
	}

	public static function column_content( $column_name, $object_id ) {
		$button_settings = get_option( 'nm_favourites_button_settings', [] );
		foreach ( $button_settings as $button_id => $button ) {
			if ( "nm_favourites_{$button[ 'category_slug' ]}" === $column_name ) {
				$button_object = nm_favourites()->button( $button_id, $object_id );
				echo ( int ) $button_object->tag()->get_object_count();
			}
		}
	}

	public static function orderby_favourites_count( $vars ) {
		if ( false !== strpos( ($vars[ 'orderby' ] ?? '' ), 'nm_favourites_count_' ) ) {
			add_filter( 'posts_join', [ __CLASS__, 'sql_join' ] );
			add_filter( 'posts_orderby', [ __CLASS__, 'sql_orderby' ] );
		}
		return $vars;
	}

	public static function sql_join( $join ) {
		global $wpdb;

		$id = ( int ) str_replace( 'nm_favourites_count_', '',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				sanitize_sql_orderby( wp_unslash( $_GET[ 'orderby' ] ?? '' ) ) );

		if ( $id ) {
			$join .= " LEFT JOIN
			(SELECT COUNT(*) AS count, object_id, category_id FROM {$wpdb->prefix}nm_favourites_tags GROUP BY object_id) AS n
				ON ({$wpdb->posts}.ID = n.object_id AND $id = n.category_id)";
		}
		return $join;
	}

	public static function sql_orderby() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return "n.count " . (sanitize_sql_orderby( wp_unslash( $_GET[ 'order' ] ?? 'desc' ) ) );
	}

}
