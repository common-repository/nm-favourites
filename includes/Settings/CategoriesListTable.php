<?php

namespace NM\Favourites\Settings;

use NM\Favourites\Settings\CategoriesTags;

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CategoriesListTable extends \WP_List_Table {

	public function get_columns() {
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'name' => nm_favourites()->is_pro ? __( 'Name', 'nm-favourites-pro' ) : __( 'Name', 'nm-favourites' ),
			'parent_name' => __( 'Parent', 'nm-favourites-pro' ),
			'category_button' => nm_favourites()->is_pro ? __( 'Button', 'nm-favourites-pro' ) : __( 'Button', 'nm-favourites' ),
			'object_type' => nm_favourites()->is_pro ?
			__( 'Object type', 'nm-favourites-pro' ) :
			__( 'Object type', 'nm-favourites' ),
			'post_types' => nm_favourites()->is_pro ?
			__( 'Post types', 'nm-favourites-pro' ) :
			__( 'Post types', 'nm-favourites' ),
			'count' => nm_favourites()->is_pro ? __( 'Count', 'nm-favourites-pro' ) : __( 'Count', 'nm-favourites' ),
			'description' => nm_favourites()->is_pro ?
			__( 'Description', 'nm-favourites-pro' ) : __( 'Description', 'nm-favourites' ),
			'visibility' => __( 'Visibility', 'nm-favourites-pro' ),
			'user' => __( 'Created by', 'nm-favourites-pro' ),
		);

		if ( !nm_favourites()->is_pro ) {
			unset( $columns[ 'parent_name' ] );
			unset( $columns[ 'visibility' ] );
			unset( $columns[ 'user' ] );
		}

		return apply_filters( 'nm_favourites_categories_columns', $columns );
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'name' => array( 'name', false ),
			'count' => array( 'count', false ),
			'visibility' => array( 'visibility', false ),
			'parent_name' => array( 'parent_name', false ),
		);
		return apply_filters( 'nm_favourites_categories_sortable_columns', $sortable_columns );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'count':
				echo ( int ) nm_favourites_format_number( $item[ $column_name ] );
				break;
			case 'description':
				echo wp_kses_post( $item[ $column_name ] );
				break;

			default:
				do_action( 'nm_favourites_categories_column', $column_name, $item );
				break;
		}
	}

	public function column_cb( $item ) {
		echo '<input type="checkbox" name="id[]" value="' . ( int ) $item[ 'id' ] . '" />';
	}

	public function column_name( $item ) {
		$edit_url = CategoriesTags::create_edit_category_url( $item[ 'id' ] );

		$actions = array(
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				CategoriesTags::create_edit_category_url( $item[ 'id' ] ),
				nm_favourites_edit_text()
			),
			'delete' => sprintf(
				'<a onclick="return showNotice.warn()" href="%s">%s</a>',
				CategoriesTags::delete_category_url( $item[ 'id' ] ),
				nm_favourites_delete_text()
			),
			'tags' => sprintf(
				'<a href="%s">%s</a>',
				CategoriesTags::view_tags_url( $item[ 'id' ] ),
				nm_favourites()->is_pro ? __( 'Tags', 'nm-favourites-pro' ) : __( 'Tags', 'nm-favourites' )
			),
		);

		if ( nm_favourites()->is_pro && $item[ 'category' ]->is_parent() ) {
			$actions[ 'subcategories' ] = sprintf(
				'<a href="%s">%s</a>',
				CategoriesTags::view_subcategories_url( $item[ 'id' ] ),
				__( 'Subcategories', 'nm-favourites-pro' )
			);
		}

		$actions[ 'slug' ] = '<code>' . $item[ 'slug' ] . '</code>';

		// phpcs:ignore WordPress.Security.NonceVerification
		$name = (empty( sanitize_text_field( $_GET[ 'orderby' ] ?? [] ) ) &&
			!empty( $item[ 'parent' ] ) ? '&mdash; ' : '') . $item[ 'name' ];

		echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $name ) . '</a>' .
		wp_kses( $this->row_actions( $actions ), nm_favourites()->settings()->allowed_post_tags() );
	}

	public function column_category_button( $item ) {
		$category_id = $item[ 'category' ]->get_parent() ? $item[ 'category' ]->get_parent() : $item[ 'id' ];
		$button = nm_favourites()->button( $category_id );
		$button->set_preview( true );
		echo wp_kses( $button->get(), nm_favourites()->settings()->allowed_post_tags() );
	}

	public function column_object_type( $item ) {
		echo esc_html( $item[ 'category' ]->get_object_type() );
	}

	public function column_post_types( $item ) {
		$parent_category = $item[ 'category' ]->get_parent_category();
		$category = $parent_category ? $parent_category : $item[ 'category' ];
		$post_types = $category->get_meta( 'post_types' );
		$types = [];

		if ( !empty( $post_types ) ) {
			$registered_post_types = get_post_types( [
				'public' => true,
				'show_ui' => true
				], 'objects' );

			foreach ( $post_types as $post_type ) {
				if ( !empty( $registered_post_types[ $post_type ] ) ) {
					$types[] = $registered_post_types[ $post_type ]->label;
				} else {
					$types[] = $post_type;
				}
			}

			echo esc_html( implode( ', ', $types ) );
		}
	}

	public function column_visibility( $item ) {
		echo esc_html( $item[ 'category' ]->get_visibility_label() );
	}

	public function column_parent_name( $item ) {
		echo '<a href="' . esc_url( CategoriesTags::view_tags_url( $item[ 'parent' ] ) ) . '">' .
		esc_html( $item[ 'parent_name' ] ) . '</a>';
	}

	public function column_user( $item ) {
		$category = $item[ 'category' ];
		$url = add_query_arg( 'user_id', $item[ 'user_id' ] );

		if ( !empty( $item[ 'user' ] ) ) {
			$val = '<a href="' . $url . '">' . $item[ 'user' ] . '</a>';
		} else {
			if ( $category->is_default() ) {
				$text = nm_favourites()->is_pro ? __( 'Admin', 'nm-favourites-pro' ) : __( 'Admin', 'nm-favourites' );
				$val = "<span style='color:inherit'>$text</span>";
			} else {
				$text = nm_favourites()->is_pro ? __( 'Guest', 'nm-favourites-pro' ) : __( 'Guest', 'nm-favourites' );
				$val = '<a style="color:#bababa" href="' . $url . '">' . $text . '</a>';
			}
		}
		echo wp_kses_post( $val );
	}

	public function get_bulk_actions() {
		return array(
			'delete' => nm_favourites_delete_text()
		);
	}

	public function process_bulk_action() {
		if ( 'delete' === $this->current_action() &&
			wp_verify_nonce( sanitize_key( wp_unslash( $_GET[ '_wpnonce' ] ?? null ) ), $this->get_nonce_action() ) ) {
			$ids = !empty( $_GET[ 'id' ] ) ? array_map( 'absint', wp_unslash( $_GET[ 'id' ] ) ) : array();
			if ( !empty( $ids ) ) {
				if ( nm_favourites()->category()->delete_multiple_with_tags( $ids ) ) {
					wp_safe_redirect( add_query_arg( 'updated', 1, wp_get_referer() ) );
					exit;
				}
			}
		}
	}

	public function get_nonce_action() {
		return 'bulk-' . $this->_args[ 'plural' ]; // this nonce is created in the core WP_List_Table class
	}

	public function per_page() {
		return ( int ) $this->get_items_per_page( 'nm_favourites_categories_per_page', get_option( 'posts_per_page' ) );
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$results = $this->read_db();
		$this->items = array_map( function ( $result ) {
			$category = nm_favourites()->category();
			$category->set_data( $result );
			$result[ 'category' ] = $category;
			return $result;
		}, $results[ 'results' ] );

		$this->set_pagination_args( array(
			'total_items' => $results[ 'found_rows' ],
			'per_page' => $this->per_page(),
			'total_pages' => ceil( $results[ 'found_rows' ] / $this->per_page() )
		) );
	}

	private function read_db() {
		global $wpdb;

		$table_name = "{$wpdb->prefix}nm_favourites_categories";

		$per_page = $this->per_page();
		// phpcs:disable WordPress.Security.NonceVerification
		$paged = absint( wp_unslash( $_GET[ 'paged' ] ?? 0 ) );
		$orderby = sanitize_sql_orderby( wp_unslash( $_GET[ 'orderby' ] ?? '' ) );
		$order = sanitize_sql_orderby( wp_unslash( $_GET[ 'order' ] ?? '' ) );
		$user_id = sanitize_text_field( wp_unslash( $_GET[ 'user_id' ] ?? [] ) );
		$s = sanitize_text_field( wp_unslash( $_GET[ 's' ] ?? [] ) );
		$id = absint( wp_unslash( $_GET[ 'id' ] ?? 0 ) );
		$act = sanitize_text_field( wp_unslash( $_GET[ 'act' ] ?? [] ) );
		// phpcs:enable

		$limit_sql = $per_page ? $wpdb->prepare( 'LIMIT %d', $per_page ) : '';

		$offset_sql = '';
		if ( isset( $paged ) && !empty( $per_page ) ) {
			$paged = max( 0, intval( $paged - 1 ) * $per_page );
			$offset_sql = $wpdb->prepare( 'OFFSET %d', $paged );
		}

		$orderby_sql = "$table_name.id";
		switch ( $orderby ) {
			case 'name':
			case 'visibility':
				$orderby_sql = "$table_name.$orderby";
				break;
			case 'count':
			case 'parent':
			case 'user':
				$orderby_sql = $orderby;
				break;
		}

		$order_type = in_array( $order, [ 'asc', 'desc' ], true ) ? $order : 'desc';
		$order_sql = " ORDER BY $orderby_sql $order_type";
		$extra_sql = $tags_count_sql = '';

		if ( $s ) {
			$extra_sql .= $wpdb->prepare( " AND {$wpdb->prefix}nm_favourites_categories.name LIKE %s ",
				'%' . $wpdb->esc_like( $s ) . '%'
			);
		}

		if ( 'view_subcategories' === $act ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$extra_sql .= $wpdb->prepare( " AND $table_name.parent = %d", $id );
		}

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$extra_sql .= $wpdb->prepare( " AND $table_name.user_id = 0 OR $table_name.user_id = %s", $user_id );
			$tags_count_sql .= $wpdb->prepare( " AND user_id = %s", $user_id );
		}

		$partial_select_sql = "$table_name WHERE 1=1 $extra_sql";

		$results = [];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results[ 'results' ] = $wpdb->get_results( "
			SELECT $table_name.*,
			(SELECT name FROM $table_name table_copy WHERE table_copy.id = $table_name.parent) as parent_name,
			(SELECT user_nicename FROM {$wpdb->users} WHERE id = $table_name.user_id) as user,
			(SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags
						WHERE 1=1
						AND (category_id = $table_name.id
						OR category_id IN (SELECT id FROM $table_name table_copy2 WHERE table_copy2.parent = $table_name.id))
						$tags_count_sql
					) AS count
			FROM $partial_select_sql
				$order_sql $limit_sql $offset_sql
				",
			ARRAY_A
		);
		$results[ 'found_rows' ] = $wpdb->get_var( "SELECT COUNT(*) FROM $partial_select_sql" );
		// phpcs:enable

		return $results;
	}

}
