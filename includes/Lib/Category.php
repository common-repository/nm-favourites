<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Tables\UserCategoriesTable;
use NM\Favourites\Lib\User;

defined( 'ABSPATH' ) || exit;

class Category {

	/**
	 * @var User
	 */
	protected $user;

	/**
	 *
	 * @var \NM\Favourites\Sub\Tag | \NM\Favourites\Lib\Tag
	 */
	protected $tag;
	protected $default = true;
	protected $data = [
		'id' => 0,
		'name' => null,
		'slug' => null,
		'user_id' => 0,
		'description' => null,
		'visibility' => 'private',
		'parent' => 0,
		'meta' => null,
	];
	protected $extra_data = [];

	public function __construct( $id_or_slug = 0, $user_id = null ) {
		$this->user = new User( $user_id );

		if ( $id_or_slug ) {
			if ( $id_or_slug instanceof self ) {
				$this->set_data( $id_or_slug->get_data() );
			} elseif ( is_numeric( $id_or_slug ) ) {
				$this->set_id( $id_or_slug );
				$this->get();
			} else {
				$this->get_from_slug( $id_or_slug );
			}
		}
	}

	/**
	 * @return array|null
	 */
	public function get() {
		global $wpdb;

		$cache_keys = [ $this->get_id(), 'get' ];
		$data = nm_favourites_cache_get( 'nm_favourites_category', $cache_keys );

		if ( false === $data ) {
			$data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nm_favourites_categories WHERE id = %d",
					$this->get_id()
				), ARRAY_A );
			nm_favourites_cache_set( 'nm_favourites_category', $cache_keys, $data );
		}

		$this->data = !empty( $data ) ? $data : $this->data;
		empty( $data ) ? $this->set_id( 0 ) : null;
	}

	public function get_from_slug( $slug ) {
		global $wpdb;

		$cache_keys = [ 'get_from_slug', $slug ];
		$data = nm_favourites_cache_get( 'nm_favourites_category', $cache_keys );

		if ( false === $data ) {
			$data = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}nm_favourites_categories WHERE slug = %s LIMIT 1",
					$slug
				), ARRAY_A );

			nm_favourites_cache_set( 'nm_favourites_category', $cache_keys, $data );
		}

		$this->data = !empty( $data ) ? $data : $this->data;
		empty( $data ) ? $this->set_id( 0 ) : nm_favourites_cache_set( 'nm_favourites_category', [ $this->get_id(), 'get' ], $data );
	}

	public function tag() {
		if ( !$this->tag ) {
			$this->tag = nm_favourites()->tag( $this, '', $this->user );
		}
		return $this->tag;
	}

	public function user() {
		return $this->user;
	}

	public function get_data() {
		return $this->data;
	}

	public function set_data( $data ) {
		$this->data = array_intersect_key( ( array ) $data, $this->data );
	}

	public function set_id( $id ) {
		$this->data[ 'id' ] = ( int ) $id;
	}

	public function set_name( $name ) {
		$original_name = $this->get_name();
		$trimmed_name = substr( $name, 0, 250 ); // Name should not be more than 250 characters

		if ( $original_name !== $trimmed_name ) {
			$this->data[ 'name' ] = $trimmed_name;
			$this->set_slug_from_name();
		}
	}

	protected function set_slug_from_name() {
		$this->data[ 'slug' ] = $this->create_unique_slug_from_name();
	}

	public function set_description( $description ) {
		// description should not be more than 400 characters.
		$this->data[ 'description' ] = substr( $description, 0, 400 );
	}

	public function set_meta( $meta ) {
		$this->data[ 'meta' ] = maybe_serialize( $meta );
	}

	public function set_default( $bool ) {
		$this->default = $bool;
	}

	public function get_id() {
		return ( int ) $this->data[ 'id' ];
	}

	public function get_name() {
		return $this->data[ 'name' ];
	}

	public function get_slug() {
		return $this->data[ 'slug' ];
	}

	public function get_user_id() {
		return ( string ) $this->data[ 'user_id' ];
	}

	public function get_description() {
		return $this->data[ 'description' ];
	}

	public function get_parent() {
		return ( int ) $this->data[ 'parent' ];
	}

	/**
	 * @return \NM\Favourites\Sub\Category | \NM\Favourites\Db\Category
	 */
	public function get_parent_category() {
		return $this->get_parent() ? nm_favourites()->category( $this->get_parent(), $this->user->get_id() ) : '';
	}

	public function get_meta( $key = null ) {
		$unserialized = maybe_unserialize( $this->data[ 'meta' ] );
		$meta = is_array( $unserialized ) ? $unserialized : [];
		return $key ? (array_key_exists( $key, $meta ) ? $meta[ $key ] : null) : $meta;
	}

	public function get_visibility() {
		return $this->data[ 'visibility' ];
	}

	public function get_visibility_icon() {
		return nm_favourites_get_iconfile( $this->get_visibility() );
	}

	public function get_visibility_label() {
		return $this->visibility_options()[ $this->get_visibility() ][ 'label' ] ?? '';
	}

	public function get_visibility_template( $echo = false ) {
		!$echo ? ob_start() : '';
		$title = nm_favourites()->is_pro ? __( 'Visibility', 'nm-favourites-pro' ) : __( 'Visibility', 'nm-favourites' );
		?>
		<div class="cat-visibility" title="<?php echo esc_attr( $title ); ?>">
			<span class="cat-icon">
		<?php echo wp_kses( $this->get_visibility_icon(), nm_favourites()->settings()->allowed_post_tags() ); ?>
			</span>
			<span class="cat-label">
		<?php echo esc_html( $this->get_visibility_label() ); ?>
			</span>
		</div>
		<?php
		return !$echo ? ob_get_clean() : '';
	}

	public function visibility_options() {
		return [
			'private' => [
				'label' => __( 'Private', 'nm-favourites-pro' ),
				'description' => __( 'Only you can view', 'nm-favourites-pro' ),
			],
			'public' => [
				'label' => __( 'Public', 'nm-favourites-pro' ),
				'description' => __( 'Anyone can view', 'nm-favourites-pro' ),
			],
		];
	}

	public function visibility_options_for_select_html() {
		$options = [];
		foreach ( $this->visibility_options() as $option => $data ) {
			$options[ $option ] = $data[ 'label' ];
			$options[ "{$option}_desc" ] = $data[ 'description' ];
		}
		return $options;
	}

	protected function create_unique_slug_from_name() {
		global $wpdb;
		$slug = sanitize_title( $this->data[ 'name' ] );
		$sql = "SELECT slug FROM {$wpdb->prefix}nm_favourites_categories WHERE slug = %s LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$slug_in_db = $wpdb->get_var( $wpdb->prepare( $sql, $slug ) );

		if ( $slug_in_db ) {
			$suffix = 2;
			do {
				$length = 255 - ( strlen( $suffix ) + 1 );
				$new_slug = substr( $slug, 0, $length ) . "-$suffix";
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$slug_in_db = $wpdb->get_var( $wpdb->prepare( $sql, $new_slug ) );
				++$suffix;
			} while ( $slug_in_db );
			$slug = $new_slug;
		}

		return $slug;
	}

	public function create() {
		global $wpdb;
		$this->data[ 'user_id' ] = $this->default ? 0 : $this->user->get_id();

		if ( $this->insert() ) {
			$this->set_id( $wpdb->insert_id );

			if ( $this->is_default_parent() ) {
				$this->save_button_settings();
			}

			$this->delete_cache();
		}

		return $this->get_id();
	}

	/**
	 * @return int|false The number of rows inserted, or false on error.
	 */
	protected function insert() {
		global $wpdb;
		return $wpdb->insert( "{$wpdb->prefix}nm_favourites_categories", $this->data );
	}

	/**
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update() {
		global $wpdb;

		$updated = $wpdb->update( "{$wpdb->prefix}nm_favourites_categories", $this->data,
			array( 'id' => $this->get_id() )
		);

		if ( $updated ) {
			$this->delete_cache();

			if ( $this->is_default_parent() ) {
				$this->save_button_settings();
			}
		}
		return $updated ?? false;
	}

	public function save() {
		if ( $this->get_id() ) {
			$id = $this->update() ? $this->get_id() : false;
		} else {
			$id = $this->create();
		}
		return $id;
	}

	protected function save_button_settings() {
		$meta = $this->get_meta();

		if ( !empty( $meta ) ) {
			// Always save the category data with the button settings
			$full_data = array_merge( [
				'category_name' => $this->get_name(),
				'category_slug' => $this->get_slug(),
				], $meta );

			$button_settings = get_option( 'nm_favourites_button_settings', [] );
			$button_settings[ $this->get_id() ] = $full_data;
			update_option( 'nm_favourites_button_settings', $button_settings );
		}
	}

	/**
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete() {
		global $wpdb;
		if ( $this->get_id() ) {
			$deleted = $wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}nm_favourites_categories WHERE id = %d OR parent = %d",
					$this->get_id(),
					$this->get_id()
				) );

			if ( $deleted ) {
				$this->cleanup( $this->get_id() );
			}
		}

		return $deleted ?? false;
	}

	/**
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete_with_tags() {
		global $wpdb;
		if ( $this->get_id() ) {
			$deleted = $wpdb->query( $wpdb->prepare(
					"DELETE category, tags
					FROM {$wpdb->prefix}nm_favourites_categories category
						LEFT JOIN {$wpdb->prefix}nm_favourites_tags tags ON category.id = tags.category_id
							WHERE category.id = %d OR category.parent = %d",
					$this->get_id(),
					$this->get_id()
				) );

			if ( $deleted ) {
				$this->cleanup( $this->get_id() );
			}
		}

		return $deleted ?? false;
	}

	/**
	 * Should only be used in admin area
	 * Does not check if user can manage
	 */
	public function delete_multiple( $ids ) {
		global $wpdb;
		$the_ids = is_array( $ids ) ? $ids : ( array ) $ids;
		$ids_placeholders = implode( ', ', array_fill( 0, count( $the_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}nm_favourites_categories WHERE id IN($ids_placeholders)",
				$the_ids
			) );

		if ( $deleted ) {
			$this->cleanup( $the_ids );
		}

		return $deleted;
	}

	/**
	 * Should only be used in admin area
	 * Does not check if user can manage
	 */
	public function delete_multiple_with_tags( $ids ) {
		global $wpdb;
		$the_ids = is_array( $ids ) ? $ids : ( array ) $ids;
		$ids_placeholders = implode( ', ', array_fill( 0, count( $the_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE category, tags
					FROM {$wpdb->prefix}nm_favourites_categories category
						LEFT JOIN {$wpdb->prefix}nm_favourites_tags tags ON category.id = tags.category_id
							WHERE category.id IN ($ids_placeholders) OR category.parent IN ($ids_placeholders)",
				array_merge( $the_ids, $the_ids )
			) );
		// phpcs:enable

		if ( $deleted ) {
			$this->cleanup( $the_ids );
		}

		return $deleted;
	}

	public function delete_button_settings( $id ) {
		$settings = $copy = get_option( 'nm_favourites_button_settings', [] );

		foreach ( ( array ) $id as $the_id ) {
			unset( $settings[ $the_id ] );
		}

		if ( $copy !== $settings ) {
			update_option( 'nm_favourites_button_settings', $settings );
		}
	}

	public function delete_tag_count( $id ) {
		foreach ( ( array ) $id as $the_id ) {
			/**
			 * We don't need to check if the category object type is associated with 'post'
			 * as the category id is unique and even if we delete it in the post metadata
			 * where it doesn't exist, there wouldn't be any problem.
			 */
			delete_metadata( 'post', 0, "nm_favourites_count_$the_id", '', true );
		}
	}

	public function cleanup( $id ) {
		$this->delete_cache( $id );
		$this->delete_button_settings( $id );
		$this->delete_tag_count( $id );
	}

	public function is_user_own() {
		return $this->user->get_id() === $this->get_user_id();
	}

	public function is_default() {
		return 0 === ( int ) $this->get_user_id();
	}

	public function is_parent() {
		return 0 === $this->get_parent();
	}

	public function is_default_parent() {
		return $this->is_default() && $this->is_parent();
	}

	public function get_user_tags_count_template( $echo = false ) {
		!$echo ? ob_start() : '';
		$count = $this->tag()->get_user_tags_count_in_category();
		?>
		<div id="nm_favourites_tags_count" style="margin-top:20px;">
		<?php
		printf(
			/* translators: %d: item count */
			nm_favourites()->is_pro ? esc_html( _nx( '%d item', '%d items', $count, 'wishlist item count', 'nm-favourites-pro' ) ) : esc_html( _nx( '%d item', '%d items', $count, 'wishlist item count', 'nm-favourites' ) ),
			absint( $count )
		);
		?>
		</div>
			<?php
			return !$echo ? ob_get_clean() : '';
		}

		public function get_header_template( $echo = false ) {
			!$echo ? ob_start() : '';
			?>
		<div id="nm_favourites_cat_info">
			<!-- title -->
			<h3 class="cat-title"><?php echo esc_html( $this->get_name() ); ?></h3>

			<!-- description -->
			<div class="cat-description">
		<?php
		echo $this->get_description() ?
			wp_kses_post( wpautop( $this->get_description() ) ) :
			'<p id="nm_favourites_no_desc" style="color:darkgrey;">' . ( nm_favourites()->is_pro ?
				esc_html__( 'No description', 'nm-favourites-pro' ) :
				esc_html__( 'No description', 'nm-favourites' )
			) . '</p>';
		?>
			</div>

		<?php
		$this->get_visibility_template( true );
		?>
		</div>
			<?php
			return !$echo ? ob_get_clean() : '';
		}

		public function get_user_tags_template( $args = [] ) {
			if ( !$this->user_can_view() ) {
				return $this->get_visibility_template();
			}

			ob_start();
			?>
		<div class="nm-favourites-category <?php echo esc_attr( $this->get_slug() ); ?>">
			<div class="nm-favourites-menu">
		<?php
		if ( $this->user->is_same() ) :
			?>
					<a class='nm_favourites_item back_to_categories' href='<?php echo esc_url( $this->get_url( true ) ); ?>'>
						&larr;
			<?php
			nm_favourites()->is_pro ?
					esc_html_e( 'Categories', 'nm-favourites-pro' ) :
					esc_html_e( 'Categories', 'nm-favourites' );
			?>
					</a>

			<?php
			if ( $this->is_user_own() ) :
				?>
						<a href="#" class="nm_favourites_item edit_cat"><?php echo esc_html( __( 'Edit', 'nm-favourites-pro' ) ); ?></a>
						<a href="#" class="nm_favourites_item delete_cat nm_favourites_shortcode_1"
							 data-nm_favourites_post_action="delete_category"
							 data-confirm="<?php echo esc_attr( nm_favourites_confirm_text() ); ?>"
							 data-category_id="<?php echo esc_attr( $this->get_id() ); ?>">
				<?php echo esc_html( nm_favourites_delete_text() ); ?>
						</a>
								 <?php
							 endif;
						 endif;
						 ?>
			</div>

		<?php
		$this->get_header_template( true );
		$this->update_form();
		$this->get_user_tags_count_template( true );
		$table = nm_favourites()->user_tags_table( $this, $args );
		$table->show();

		if ( 'public' === $this->get_visibility() ) {
			$this->get_share_template( true );
		}
		?>

		</div>
		<?php
		return ob_get_clean();
	}

	protected function update_form() {

	}

	public function get_share_template( $echo = false ) {
		return $echo;
	}

	public function get_user_categories_count() {
		global $wpdb;
		$cache_keys = [ 'user_categories_count', 'admin' ];
		$results = nm_favourites_cache_get( 'nm_favourites_category', $cache_keys );

		if ( false === $results ) {
			$results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_categories WHERE user_id = 0" );
			nm_favourites_cache_set( 'nm_favourites_category', $cache_keys, $results );
		}

		return $results;
	}

	protected function get_user_categories_sql( $args = [] ) {
		global $wpdb;

		$limit_sql = !empty( $args[ 'limit' ] ) ? $wpdb->prepare( 'LIMIT %d', $args[ 'limit' ] ) : '';
		$offset_sql = !empty( $args[ 'offset' ] ) ? $wpdb->prepare( 'OFFSET %d', $args[ 'offset' ] ) : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->prepare(
				"SELECT categories.*,
					(SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags WHERE user_id = %d AND category_id = categories.id) AS count
					FROM {$wpdb->prefix}nm_favourites_categories AS categories
					WHERE categories.user_id = 0
						$limit_sql
						$offset_sql
					",
				$this->user->get_id()
		);
		// phpcs:enable
	}

	public function get_user_categories( $args = [] ) {
		global $wpdb;
		$cache_keys = [ 'user_categories', $this->user->get_id(), md5( implode( '-', $args ) ) ];
		$results = nm_favourites_cache_get( 'nm_favourites_category', $cache_keys );

		if ( false === $results ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $this->get_user_categories_sql( $args ), ARRAY_A );

			if ( empty( $args[ 'limit' ] ) && method_exists( $this, 'arrange_categories_by_parent_child' ) ) {
				$results = $this->arrange_categories_by_parent_child( $results );
			}

			nm_favourites_cache_set( 'nm_favourites_category', $cache_keys, $results );
		}

		return array_map( function ( $res ) {
			$category = nm_favourites()->category();
			$category->set_data( $res );
			$category->extra_data[ 'count' ] = $res[ 'count' ] ?? 0;
			return $category;
		}, $results );
	}

	public function get_user_categories_template( $args = [] ) {
		$title = nm_favourites()->is_pro ? __( 'Categories', 'nm-favourites-pro' ) : __( 'Categories', 'nm-favourites' );
		$template = '<div class="nm-favourites-categories">';
		$template .= '<h3 class="cat-title">' . $title . '</h3>';

		if ( !$this->user->is_same() ) {
			// Default visibility is private so this template should return the private visibility template html
			$template .= nm_favourites()->category()->get_visibility_template();
		} else {
			$template .= (new UserCategoriesTable( $this, $args ))->get();
		}

		$template .= '</div>';
		return $template;
	}

	public function user_can_view() {
		return 'private' === $this->get_visibility() ? $this->user->is_same() : true;
	}

	public function get_url( $favourites_page = false ) {
		$page_id = nm_favourites()->settings()->get_option( 'page_id' );
		$url = $page_id ? get_permalink( $page_id ) : false;

		if ( !$favourites_page ) {
			$args = [
				'nm_favourites_category' => $this->get_id(),
				'user_id' => $this->user->get_id(),
			];
		}
		return add_query_arg( ($args ?? [] ), $url );
	}

	public function get_object_type() {
		return $this->get_meta( 'object_type' );
	}

	public function get_count_key() {
		return "nm_favourites_count_{$this->get_id()}";
	}

	public function get_extra_data( $key ) {
		return $this->extra_data[ $key ] ?? null;
	}

	/**
	 * Get most tagged objects in this category
	 */
	public function get_most_tagged( $args = [] ) {
		global $wpdb;

		if ( !$this->get_id() ) {
			return [];
		}

		$cache_keys = [ $this->get_id(), 'most_tagged', md5( implode( '-', $args ) ) ];
		$results = nm_favourites_cache_get( 'nm_favourites_category', $cache_keys );

		if ( false === $results ) {
			$limit = !empty( $args[ 'limit' ] ) ? ( int ) $args[ 'limit' ] : 10;

			$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT tags.object_id,
			(SELECT COUNT(*) FROM {$wpdb->prefix}nm_favourites_tags
				WHERE object_id = tags.object_id
				AND (category_id = %d
				OR category_id IN (SELECT id FROM {$wpdb->prefix}nm_favourites_categories WHERE parent = %d))
			) AS count
			FROM {$wpdb->prefix}nm_favourites_tags as tags
				WHERE (tags.category_id = %d
				OR tags.category_id IN (SELECT id FROM {$wpdb->prefix}nm_favourites_categories WHERE parent = %d))
			GROUP BY tags.object_id
			ORDER BY count DESC
			LIMIT %d
			",
					$this->get_id(),
					$this->get_id(),
					$this->get_id(),
					$this->get_id(),
					$limit
				), ARRAY_A );

			nm_favourites_cache_set( 'nm_favourites_category', $cache_keys, $results );
		}

		return $results;
	}

	public function get_most_tagged_template( $args = [] ) {
		$tags = $this->get_most_tagged( $args );

		if ( empty( $tags ) ) {
			return;
		}

		$template = '<ul>';
		foreach ( $tags as $tag ) {
			$post = get_post( $tag[ 'object_id' ] );
			if ( !empty( $post->ID ) ) {
				$template .= '<li>';
				$template .= '<a href="' . get_permalink( $post ) . '">' . get_the_title( $post ) . '</a>';
				$template .= '<span class="nm_favourites_tag_count"> (' . $tag[ 'count' ] . ')</span>';
				$template .= '</li>';
			}
		}
		$template .= '</ul>';
		return $template;
	}

	public function delete_cache( $id = null ) {
		nm_favourites_cache_delete( 'nm_favourites_category', 'user_categories_count' );
		nm_favourites_cache_delete( 'nm_favourites_category', 'user_categories' );
		nm_favourites_cache_delete( 'nm_favourites_category', 'get_from_slug' );

		$ids = !$id ? [ $this->get_id() ] : ( array ) $id;
		foreach ( $ids as $the_id ) {
			nm_favourites_cache_delete( 'nm_favourites_category', $the_id );
			/**
			 * We don't need to do this but for good cleanup and to lighten the cache weight,
			 * delete all the tag cache associated with this category.
			 */
			nm_favourites_cache_delete( 'nm_favourites_tag', $the_id );
		}
	}

}
