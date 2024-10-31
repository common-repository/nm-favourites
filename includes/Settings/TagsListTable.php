<?php

namespace NM\Favourites\Settings;

use NM\Favourites\Settings\CategoriesTags;

defined( 'ABSPATH' ) || exit;

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TagsListTable extends \WP_List_Table {

	public $button;

	public function get_columns() {
		$category_id = CategoriesTags::current_category_id();
		$this->button = nm_favourites()->button( $category_id );

		$base_columns = [
			'cb' => '<input type="checkbox" />',
			'object_id' => nm_favourites()->is_pro ? __( 'Object id', 'nm-favourites-pro' ) : __( 'Object id', 'nm-favourites' ),
			'count' => nm_favourites()->is_pro ? __( 'Count', 'nm-favourites-pro' ) : __( 'Count', 'nm-favourites' ),
		];

		$cols = $this->get_columns_for_object_type( $base_columns );

		$cols[ 'user' ] = nm_favourites()->is_pro ? __( 'User', 'nm-favourites-pro' ) : __( 'User', 'nm-favourites' );
		$cols[ 'date_created' ] = nm_favourites()->is_pro ? __( 'Date', 'nm-favourites-pro' ) : __( 'Date', 'nm-favourites' );

		// phpcs:disable WordPress.Security.NonceVerification
		$orderby = sanitize_text_field( wp_unslash( $_GET[ 'orderby' ] ?? [] ) );
		$user_id = sanitize_text_field( wp_unslash( $_GET[ 'user_id' ] ?? [] ) );
		$object_id = sanitize_text_field( wp_unslash( $_GET[ 'object_id' ] ?? [] ) );
		// phpcs:enable

		if ( 'count' === $orderby && !$user_id ) {
			unset( $cols[ 'user' ] );
		}

		if ( $object_id || $user_id ) {
			unset( $cols[ 'count' ] );
		}

		return $cols;
	}

	protected function get_columns_for_object_type( $cols ) {
		switch ( $this->button->get_object_type() ) {
			case 'post':
			case 'product':
				$img_text = nm_favourites()->is_pro ? __( 'Image', 'nm-favourites-pro' ) : __( 'Image', 'nm-favourites' );
				$cols = array_merge( $cols, array(
					'image' => "<span class='dashicons dashicons-format-image'><span style='display:none'>$img_text</span></span>",
					'title' => nm_favourites()->is_pro ? __( 'Title', 'nm-favourites-pro' ) : __( 'Title', 'nm-favourites' ),
					) );

				if ( nm_favourites()->is_pro && $this->button->category()->has_child() ) {
					$cols[ 'category_name' ] = __( 'Subcategory', 'nm-favourites-pro' );
				}
				break;
			case 'comment':
				$cols[ 'comment_author' ] = nm_favourites()->is_pro ?
					__( 'Author', 'nm-favourites-pro' ) :
					__( 'Author', 'nm-favourites' );
				$cols[ 'comment' ] = nm_favourites()->is_pro ?
					__( 'Comment', 'nm-favourites-pro' ) :
					__( 'Comment', 'nm-favourites' );
				$cols[ 'comment_response' ] = nm_favourites()->is_pro ?
					__( 'In response to', 'nm-favourites-pro' ) :
					__( 'In response to', 'nm-favourites' );
				break;
		}
		return $cols;
	}

	public function get_sortable_columns() {
		$cols = array(
			'date_created' => array( 'date_created', 'desc' ),
			'category_name' => array( 'category_name', false ),
			'user' => array( 'user', false ),
			'count' => array( 'count', 'desc' ),
		);
		return $cols;
	}

	public function column_cb( $item ) {
		return '<input type="checkbox" name="tag_id[]" value="' . $item[ 'id' ] . '" />';
	}

	public function column_object_id( $item ) {
		$id = $item[ 'id' ];

		$actions = array(
			'delete' => sprintf(
				'<a onclick="return showNotice.warn()" href="%s">%s</a>',
				wp_nonce_url( add_query_arg( [
				'act' => 'delete_tag',
				'tag_id' => $id,
					] ),
					"delete_tag_$id"
				),
				nm_favourites_delete_text()
			),
		);

		return '<a href="' . CategoriesTags::view_tags_for_object_url( $item[ 'object_id' ], $item[ 'category_id' ] ) . '">' .
			$item[ 'object_id' ] . '</a>' .
			$this->row_actions( $actions );
	}

	public function column_count( $item ) {
		echo ( int ) nm_favourites_format_number( $item[ 'count' ] );
	}

	public function column_title( $item ) {
		$post = $item[ 'post' ];

		if ( !$post ) {
			return;
		}

		$actions = array(
			'edit' => sprintf(
				'<a href="%s">%s</a>',
				get_edit_post_link( $post ),
				nm_favourites_edit_text()
			),
			'view' => sprintf(
				'<a href="%s">%s</a>',
				get_permalink( $post ),
				nm_favourites()->is_pro ? __( 'View', 'nm-favourites-pro' ) : __( 'View', 'nm-favourites' )
			),
		);

		return '<a href="' . CategoriesTags::view_tags_for_object_url( $item[ 'object_id' ], $item[ 'category_id' ] ) . '">' .
			get_the_title( $post ) . '</a>' .
			$this->row_actions( $actions );
	}

	public function column_image( $item ) {
		if ( !$item[ 'post' ] ) {
			return;
		}
		return get_the_post_thumbnail( $item[ 'post' ], [ 40, 40 ] );
	}

	public function column_category_name( $item ) {
		if ( ( int ) $item[ 'category_id' ] !== CategoriesTags::current_category_id() && !empty( $item[ 'parent' ] ) ) {
			$name = '&mdash; ' . $item[ 'category_name' ];
			return '<a href="' . CategoriesTags::view_tags_url( $item[ 'category_id' ] ) . '">' . $name . '</a>';
		}
	}

	public function column_date_created( $item ) {
		return mysql2date( get_option( 'date_format' ), $item[ 'date_created' ] );
	}

	public function column_user( $item ) {
		$url = add_query_arg( 'user_id', $item[ 'user_id' ], remove_query_arg( 'object_id' ) );

		if ( !empty( $item[ 'user' ] ) ) {
			$val = '<a href="' . $url . '">' . $item[ 'user' ] . '</a>';
		} else {
			$text = nm_favourites()->is_pro ? __( 'Guest', 'nm-favourites-pro' ) : __( 'Guest', 'nm-favourites' );
			$val = '<a style="color:#bababa" href="' . $url . '">' . $text . '</a>';
		}
		return $val;
	}

	public function column_comment( $item ) {
		echo wp_kses_post( comment_text( $item[ 'comment' ] ) );
	}

	public function column_comment_author( $item ) {
		$comment = $item[ 'comment' ];
		echo '<strong>';
		comment_author( $comment );
		echo '</strong><br />';
		comment_author_email( $comment );
	}

	public function column_comment_response( $item ) {
		$comment = $item[ 'comment' ];

		if ( !$comment ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );

		if ( !$post ) {
			return;
		}

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			echo "<a href='" . esc_url( get_edit_post_link( $post->ID ) ) . "' class='comments-edit-item-link'>" .
			esc_html( get_the_title( $post->ID ) ) . '</a>';
		} else {
			echo esc_html( get_the_title( $post->ID ) );
		}
	}

	public function get_bulk_actions() {
		return array(
			'delete_tags' => nm_favourites_delete_text()
		);
	}

	public function process_bulk_action() {
		if ( 'delete_tags' === $this->current_action() &&
			wp_verify_nonce( sanitize_key( wp_unslash( $_GET[ '_wpnonce' ] ?? null ) ), $this->get_nonce_action() ) ) {
			$ids = !empty( $_GET[ 'tag_id' ] ) ? array_map( 'absint', wp_unslash( $_GET[ 'tag_id' ] ) ) : array();
			if ( !empty( $ids ) ) {
				$tag = nm_favourites()->tag();
				if ( $tag->delete_multiple( $ids ) ) {
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
		return ( int ) $this->get_items_per_page( 'nm_favourites_tags_per_page', get_option( 'posts_per_page' ) );
	}

	protected function get_object_type_data( $results ) {
		switch ( $this->button->get_object_type() ) {
			case 'post':
			case 'product':
				$results = array_map( function ( $result ) {
					$result[ 'post' ] = get_post( $result[ 'object_id' ] );
					return $result;
				}, $results );
				break;
			case 'comment':
				$results = array_map( function ( $result ) {
					$result[ 'comment' ] = get_comment( $result[ 'object_id' ] );
					return $result;
				}, $results );
				break;
		}

		return $results;
	}

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$results = $this->read_db();
		$this->items = $this->get_object_type_data( $results[ 'results' ] );

		$this->set_pagination_args( array(
			'total_items' => $results[ 'found_rows' ],
			'per_page' => $this->per_page(),
			'total_pages' => ceil( $results[ 'found_rows' ] / $this->per_page() )
		) );
	}

	private function read_db() {
		global $wpdb;

		$per_page = $this->per_page();
		// phpcs:disable WordPress.Security.NonceVerification
		$paged = absint( wp_unslash( $_GET[ 'paged' ] ?? 0 ) );
		$orderby = sanitize_sql_orderby( wp_unslash( $_GET[ 'orderby' ] ?? '' ) );
		$order = sanitize_sql_orderby( wp_unslash( $_GET[ 'order' ] ?? '' ) );
		$cat_id = ( int ) wp_unslash( $_GET[ 'id' ] ?? 0 );
		$user_id = sanitize_text_field( wp_unslash( $_GET[ 'user_id' ] ?? [] ) );
		$object_id = sanitize_text_field( wp_unslash( $_GET[ 'object_id' ] ?? [] ) );
		// phpcs:enable

		$limit_sql = $per_page ? $wpdb->prepare( 'LIMIT %d', $per_page ) : '';

		$offset_sql = '';
		if ( isset( $paged ) && !empty( $per_page ) ) {
			$paged = max( 0, intval( $paged - 1 ) * $per_page );
			$offset_sql = $wpdb->prepare( 'OFFSET %d', $paged );
		}

		$extra_sql = '';
		$groupby_sql = '';
		$order_type = in_array( $order, [ 'asc', 'desc' ], true ) ? $order : 'desc';
		$orderby_sql = "tags.id $order_type";
		$user_sql = "(SELECT display_name FROM {$wpdb->users} WHERE id = tags.user_id) as user,";

		switch ( $orderby ) {
			case 'date_created':
				$orderby_sql = "tags.$orderby $order_type";
				break;
			case 'user':
			case 'category_name':
				$orderby_sql = "$orderby $order_type";
				break;
			case 'count':
				$orderby_sql = "$orderby $order_type";
				if ( empty( $user_id ) ) {
					$orderby_sql = "$orderby $order_type, tags.object_id desc";
					$groupby_sql = ' GROUP BY tags.object_id';
					$user_sql = '';
				}
				break;
		}

		$order_sql = " ORDER BY $orderby_sql";

		if ( $object_id ) {
			$extra_sql .= $wpdb->prepare( " AND tags.object_id = %s", $object_id );
		}

		if ( $user_id ) {
			$extra_sql .= $wpdb->prepare( " AND tags.user_id = %s", $user_id );
		}

		$where_sql = $wpdb->prepare( "
			WHERE (category_id = %d
				OR category_id IN (SELECT id FROM {$wpdb->prefix}nm_favourites_categories WHERE parent = %d))
			",
			$cat_id,
			$cat_id
		);

		$select_sql = "
			SELECT tags.*, categories.name AS category_name, categories.parent,
			$user_sql
				(SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags AS tc
				WHERE tc.object_id = tags.object_id
				AND (tc.category_id = tags.category_id
				OR tc.category_id IN (SELECT id FROM {$wpdb->prefix}nm_favourites_categories as cc WHERE parent = tags.category_id))
			) AS count
			FROM {$wpdb->prefix}nm_favourites_tags AS tags
				LEFT JOIN (SELECT id, name, parent FROM {$wpdb->prefix}nm_favourites_categories) AS categories
					ON categories.id = tags.category_id
			$where_sql $extra_sql $groupby_sql $order_sql $limit_sql $offset_sql
		";

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags AS tags $where_sql $extra_sql $groupby_sql";

		if ( 'count' === $orderby ) {
			$count_sql = "SELECT COUNT(*) FROM	($count_sql) as t";
		}

		$results = [];
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results[ 'results' ] = $wpdb->get_results( $select_sql, ARRAY_A );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results[ 'found_rows' ] = $wpdb->get_var( $count_sql );

		return $results;
	}

}
