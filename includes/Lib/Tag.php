<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Lib\User;
use NM\Favourites\Lib\Category;
use NM\Favourites\Tables\Table;
use NM\Favourites\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class Tag {

	/**
	 * @var \NM\Favourites\Lib\User
	 */
	public $user;

	/**
	 * @var \NM\Favourites\Sub\Category | \NM\Favourites\Db\Category
	 */
	protected $category;
	protected $data = [
		'id' => 0,
		'user_id' => '',
		'object_id' => '',
		'category_id' => 0,
		'date_created' => '',
	];

	public function __construct( $category_id = '', $object_id = '', $user_id = null ) {
		$this->user = is_a( $user_id, User::class ) ? $user_id : new User( $user_id );

		$this->set_user_id( $this->user->get_id() );
		$this->set_object_id( $object_id );
		$this->set_category_id( is_a( $category_id, Category::class ) ? $category_id->get_id() : $category_id );
	}

	/**
	 * @return \NM\Favourites\Sub\Category | \NM\Favourites\Db\Category
	 */
	public function category() {
		if ( !$this->category ) {
			$this->category = nm_favourites()->category( $this->get_category_id(), $this->get_user_id() );
		}
		return $this->category;
	}

	public function set_data( $data ) {
		$this->data = array_intersect_key( ( array ) $data, $this->data );
	}

	public function set_id( $tag_id ) {
		$this->data[ 'id' ] = $tag_id;
	}

	public function set_user_id( $user_id ) {
		$this->data[ 'user_id' ] = $user_id;
	}

	public function set_object_id( $object_id ) {
		$this->data[ 'object_id' ] = $object_id;
	}

	public function set_category_id( $category_id ) {
		$this->data[ 'category_id' ] = $category_id;
	}

	public function get() {
		global $wpdb;

		if ( $this->get_user_id() && $this->get_category_id() && $this->get_object_id() ) {
			$cache_keys = [ $this->get_category_id(), $this->get_user_id(), $this->obj_cache_key(), 'get' ];
			$data = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

			if ( false === $data ) {
				$data = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM {$wpdb->prefix}nm_favourites_tags WHERE user_id = %s
				AND object_id = %s
				AND category_id = %d
				LIMIT 1",
						$this->get_user_id(),
						$this->get_object_id(),
						$this->get_category_id()
					), ARRAY_A );

				nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $data );
			}

			$this->data = !empty( $data ) ? $data : $this->data;
		}
	}

	public function get_by_id() {
		global $wpdb;
		$cache_keys = [ $this->get_category_id(), $this->get_id(), 'get_by_id' ];
		$data = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

		if ( false === $data ) {
			$data = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}nm_favourites_tags WHERE id = %d LIMIT 1",
					$this->get_id()
				),
				ARRAY_A );
			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $data );
		}
		$this->data = !empty( $data ) ? $data : $this->data;
	}

	public function get_object_count() {
		global $wpdb;

		if ( !$this->get_object_id() || !$this->get_category_id() ) {
			return 0;
		}

		$cache_keys = [ $this->get_category_id(), 'object_count', $this->obj_cache_key() ];
		$count = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

		if ( false === $count ) {
			if ( nm_favourites()->is_pro ) {
				$val = $wpdb->prepare( "
			SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags
				WHERE object_id = %s
				AND (category_id = %d OR category_id IN (SELECT id FROM {$wpdb->prefix}nm_favourites_categories WHERE parent = %d))
			",
					$this->get_object_id(),
					$this->get_category_id(),
					$this->get_category_id()
				);
			} else {
				$val = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags WHERE object_id = %s AND category_id = %d",
					$this->get_object_id(),
					$this->get_category_id()
				);
			}

			$count = ( int ) $wpdb->get_var( $val ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $count, );
		}

		return $count;
	}

	public function get_id() {
		return $this->data[ 'id' ];
	}

	/**
	 * @return string
	 */
	public function get_user_id() {
		return ( string ) $this->data[ 'user_id' ];
	}

	public function get_object_id() {
		return $this->data[ 'object_id' ];
	}

	public function get_category_id() {
		return ( int ) $this->data[ 'category_id' ];
	}

	public function get_date_created() {
		return $this->data[ 'date_created' ];
	}

	public function exists() {
		return !empty( $this->get_id() );
	}

	public function exists_in_categories() {
		global $wpdb;

		if ( !$this->get_object_id() || !$this->get_category_id() || !$this->get_user_id() ) {
			return false;
		}

		$cache_keys = [ $this->get_category_id(), $this->get_user_id(), $this->obj_cache_key(), 'exists_in_categories' ];
		$int = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

		if ( false === $int ) {
			$int = $wpdb->get_var( $wpdb->prepare( "
			SELECT EXISTS(
				SELECT * FROM {$wpdb->prefix}nm_favourites_tags
				WHERE user_id = %s
				AND object_id = %s
				AND category_id IN
				(SELECT %d UNION ALL
				SELECT id FROM {$wpdb->prefix}nm_favourites_categories
					WHERE parent = %d AND (user_id = 0 OR user_id = %s))
			)",
					$this->get_user_id(),
					$this->get_object_id(),
					$this->get_category_id(),
					$this->get_category_id(),
					$this->get_user_id()
				) );
			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $int );
		}

		return ( bool ) $int;
	}

	public function is_user_own() {
		return ( string ) $this->user->get_id() === $this->get_user_id();
	}

	public function create() {
		global $wpdb;
		if ( !$this->get_object_id() || !$this->get_category_id() ) {
			return false;
		}

		$this->data[ 'date_created' ] = current_time( 'mysql', 1 );
		$placeholder_1 = $this->data;
		$placeholder_2 = [ $this->get_user_id(), $this->get_object_id(), $this->get_category_id() ];

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$inserted = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}nm_favourites_tags (id, user_id, object_id, category_id, date_created)
						SELECT %d, %s, %s, %d, %s FROM DUAL
						WHERE NOT EXISTS (
						SELECT * FROM {$wpdb->prefix}nm_favourites_tags
							WHERE user_id = %d AND object_id = %s AND category_id = %d )",
				array_merge( $placeholder_1, $placeholder_2 )
			) );

		if ( $inserted ) {
			$this->set_id( $wpdb->insert_id );
			$this->delete_cache();
			$this->update_tag_count();
			do_action( 'nm_favourites_tag_created', $this );
		}
		return $this->get_id();
	}

	public function delete() {
		global $wpdb;

		$deleted = $wpdb->delete( "{$wpdb->prefix}nm_favourites_tags", [ 'id' => $this->get_id() ] );

		if ( $deleted ) {
			$this->delete_cache();
			$this->update_tag_count();
			do_action( 'nm_favourites_tag_deleted', $this );
			$this->set_id( 0 );
		}

		return $deleted ?? false;
	}

	public function delete_multiple( $ids ) {
		global $wpdb;
		$the_ids = is_array( $ids ) ? $ids : ( array ) $ids;
		$ids_placeholders = implode( ', ', array_fill( 0, count( $the_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}nm_favourites_tags WHERE id IN($ids_placeholders)",
				$the_ids
			) );
		if ( $deleted ) {
			nm_favourites_cache_delete_group( 'nm_favourites_tag' );
			nm_favourites_cache_delete( 'nm_favourites_category', 'user_categories' );
		}
		return $deleted;
	}

	public function update_tag_count() {
		$category = nm_favourites()->category( $this->get_category_id() );
		// Update tag count only for default categories, not user-created ones
		if ( $category->is_default() && in_array( $category->get_object_type(), nm_favourites_post_object_types() ) ) {
			update_post_meta( $this->get_object_id(), $category->get_count_key(), $this->get_object_count() );
		}
	}

	protected function get_user_tags_in_category_sql( $args = [] ) {
		global $wpdb;

		$limit_sql = !empty( $args[ 'limit' ] ) ? $wpdb->prepare( 'LIMIT %d', $args[ 'limit' ] ) : '';
		$offset_sql = !empty( $args[ 'offset' ] ) ? $wpdb->prepare( 'OFFSET %d', $args[ 'offset' ] ) : '';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}nm_favourites_tags WHERE user_id = %s AND category_id = %s
						ORDER BY date_created DESC $limit_sql $offset_sql
					",
				$this->get_user_id(),
				$this->get_category_id()
		);
		// phpcs:enable
	}

	public function get_user_tags_in_category( $args = [] ) {
		global $wpdb;

		if ( !$this->get_category_id() || !$this->get_user_id() ) {
			return [];
		}

		$index = md5( implode( '-', $args ) );
		$cache_keys = [ $this->get_category_id(), $this->get_user_id(), 'user_tags_in_category', $index ];
		$results = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

		if ( false === $results ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $this->get_user_tags_in_category_sql( $args ), ARRAY_A );
			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $results );
		}

		return array_map( function ( $res ) {
			$tag = nm_favourites()->tag();
			$tag->set_data( $res );
			return $tag;
		}, $results );
	}

	protected function get_user_tags_count_in_category_sql() {
		global $wpdb;
		return $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags WHERE user_id = %s AND category_id = %d",
				$this->get_user_id(),
				$this->get_category_id()
		);
	}

	public function get_user_tags_count_in_category() {
		global $wpdb;

		if ( !$this->get_category_id() || !$this->get_user_id() ) {
			return 0;
		}

		$cache_keys = [ $this->get_category_id(), $this->get_user_id(), 'user_tags_count_in_category' ];
		$res = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );
		if ( false === $res ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$res = $wpdb->get_var( $this->get_user_tags_count_in_category_sql() );
			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $res );
		}

		return ( int ) $res;
	}

	public function get_registered_users_only_sql() {
		return !nm_favourites()->settings()->get_option( 'enable_guests' ) ? " AND tags.user_id REGEXP '^[0-9]+$'" : '';
	}

	public function get_users_count() {
		global $wpdb;

		if ( !$this->get_category_id() || !$this->get_object_id() ) {
			return 0;
		}

		$registered_only = $this->get_registered_users_only_sql();
		$index = md5( $registered_only );
		$cache_keys = [ $this->get_category_id(), $this->obj_cache_key(), 'users_count', $index ];
		$results = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

		if ( false === $results ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags AS tags
				WHERE object_id = %s AND category_id = %d
				$registered_only
			",
					$this->get_object_id(),
					$this->get_category_id()
				) );
			// phpcs:enable

			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $results );
		}

		return ( int ) $results;
	}

	// Get users that have tagged this object in this category
	public function get_users( $args = [] ) {
		global $wpdb;

		if ( !$this->get_category_id() || !$this->get_object_id() ) {
			return [];
		}

		$registered_only = $this->get_registered_users_only_sql();
		$index = md5( $registered_only . implode( ',', $args ) );
		$cache_keys = [ $this->get_category_id(), $this->obj_cache_key(), 'users', $index ];
		$results = nm_favourites_cache_get( 'nm_favourites_tag', $cache_keys );

		if ( false === $results ) {
			$limit_sql = !empty( $args[ 'limit' ] ) ? $wpdb->prepare( 'LIMIT %d', $args[ 'limit' ] ) : '';
			$offset_sql = !empty( $args[ 'offset' ] ) ? $wpdb->prepare( 'OFFSET %d', $args[ 'offset' ] ) : '';

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT tags.user_id, users.user_nicename FROM {$wpdb->prefix}nm_favourites_tags AS tags
				LEFT JOIN $wpdb->users AS users ON tags.user_id = users.ID
				WHERE object_id = %s AND category_id = %d
				$registered_only
				ORDER BY tags.id DESC
				$limit_sql
				$offset_sql
			",
					$this->get_object_id(),
					$this->get_category_id()
				), ARRAY_A );
			// phpcs:enable

			nm_favourites_cache_set( 'nm_favourites_tag', $cache_keys, $results );
		}

		return $results;
	}

	public function get_users_template( $args = [] ) {
		$table = $this->get_users_table( $args );
		return $table->get();
	}

	public function get_users_table( $args ) {
		$data = [
			'name' => [
				'label' => nmgr()->is_pro ?
				__( 'Name', 'nm-favourites-pro' ) :
				__( 'Name', 'nm-favourites' ),
				'value' => function ( $table ) {
					$user = $table->get_row_object();
					return !empty( $user[ 'user_nicename' ] ) ? $user[ 'user_nicename' ] :
					(nm_favourites()->is_pro ? __( 'Guest', 'nm-favourites-pro' ) : __( 'Guest', 'nm-favourites' ));
				}
			],
		];

		$fields = new Fields();
		$fields->set_id( 'users' );
		$fields->set_data( $data );
		$fields->set_args( [ 'tag' => $this ] );

		$table = new Table();
		$table->set_id( 'users_table' );
		$table->set_args( $args );
		$table->set_data( $fields->get_data() );
		nm_favourites()->is_pro ? $table->set_items_per_page( 10 ) : '';
		$table->set_items_count( $this->get_users_count() );
		$table->setup();
		$table->set_rows_object( $this->get_users( $table->get_query_args() ) );

		$table_attributes = [
			'data-category_id' => $this->get_category_id(),
			'data-object_id' => $this->get_object_id(),
			'data-per_page' => $table->get_items_per_page(),
		];
		$table->set_table_attributes( $table_attributes );

		return $table;
	}

	public function obj_cache_key() {
		return md5( $this->get_object_id() );
	}

	public function delete_cache() {
		nm_favourites_cache_delete( 'nm_favourites_tag', [ $this->get_category_id(), 'object_count', $this->obj_cache_key() ] );
		nm_favourites_cache_delete( 'nm_favourites_tag', [ $this->get_category_id(), $this->obj_cache_key() ] );
		nm_favourites_cache_delete( 'nm_favourites_tag', [ $this->get_category_id(), $this->get_id() ] );
		nm_favourites_cache_delete( 'nm_favourites_tag', [ $this->get_category_id(), $this->get_user_id() ] );
		nm_favourites_cache_delete( 'nm_favourites_tag', [ $this->get_category_id(), $this->get_user_id(), $this->obj_cache_key() ] );
		/**
		 * Delete the user_categories cache in the 'nm_favourites_category' group here as that cache holds the count
		 * of the tags in each category for the user and so deleting a tag would need to reset the count.
		 */
		nm_favourites_cache_delete( 'nm_favourites_category', [ 'user_categories', $this->get_user_id() ] );
	}

}
