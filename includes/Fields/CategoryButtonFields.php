<?php

namespace NM\Favourites\Fields;

use NM\Favourites\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class CategoryButtonFields extends Fields {

	protected $id = 'category_button';
	protected $category;
	protected $settings;
	public $object_type;
	public $display_name;

	public function __construct( $category = null, $object_type = 'post', $display_name = 'display_1' ) {
		$this->category = $category ? nm_favourites()->category( $category ) : null;
		$this->display_name = $display_name;
		$this->settings = $this->category ? $this->category->get_meta() : [];
		$this->object_type = $this->settings[ 'object_type' ] ?? $object_type;
	}

	protected function data() {
		return array_merge( $this->get_core_data(), $this->get_appearance_data() );
	}

	public function get_data() {
		if ( !$this->data ) {
			$this->set_data( $this->data() );
		}
		return parent::get_data();
	}

	public function get_core_data() {
		$data = [
			array(
				'id' => 'enabled',
				'name' => 'enabled',
				'label' => nm_favourites()->is_pro ?
				__( 'Enabled', 'nm-favourites-pro' ) :
				__( 'Enabled', 'nm-favourites' ),
				'type' => 'checkbox',
				'value' => '',
			),
			array(
				'id' => 'toggle_with',
				'name' => 'toggle_with',
				'label' => nm_favourites()->is_pro ?
				__( 'Toggle with', 'nm-favourites-pro' ) :
				__( 'Toggle with', 'nm-favourites' ),
				'type' => 'select',
				'options' => $this->parent_categories_options_for_select_html(),
				'custom_attributes' => [
					'class' => 'nm-favourites-select2',
					'multiple' => true,
				],
				'desc' => nm_favourites()->is_pro ?
				__( 'If an object is tagged to this category, remove the object tag from the above categories.', 'nm-favourites-pro' ) :
				__( 'If an object is tagged to this category, remove the object tag from the above categories.', 'nm-favourites' ),
			),
			array(
				'id' => 'object_type',
				'name' => 'object_type',
				'label' => nm_favourites()->is_pro ? __( 'Object type', 'nm-favourites-pro' ) : __( 'Object type', 'nm-favourites' ),
				'type' => 'select',
				'options' => $this->get_object_type_options(),
				'value' => 'post',
			),
			array(
				'id' => 'post_types',
				'name' => 'post_types',
				'label' => nm_favourites()->is_pro ?
				__( 'Post types to show the button', 'nm-favourites-pro' ) :
				__( 'Post types to show the button', 'nm-favourites' ),
				'type' => 'select',
				'value' => [],
				'options' => $this->post_types(),
				'custom_attributes' => [
					'class' => 'nm-favourites-select2',
					'multiple' => 'multiple',
					'data-placeholder' => $this->search_text(),
				],
			),
		];

		return $this->set_values( $data );
	}

	protected function appearance_data() {
		$data = [
			array(
				'id' => 'position',
				'label' => nm_favourites()->is_pro ?
				__( 'Button position', 'nm-favourites-pro' ) :
				__( 'Button position', 'nm-favourites' ),
				'type' => 'select',
				'name' => 'position',
				'options' => $this->button_positions(),
				'value' => 'before_content',
			),
			array(
				'id' => 'icon',
				'name' => 'icon',
				'label' => nm_favourites()->is_pro ?
				__( 'Button icon', 'nm-favourites-pro' ) :
				__( 'Button icon', 'nm-favourites' ),
				'type' => 'select',
				'value' => 'star',
				'options' => [
					'heart' => nm_favourites()->is_pro ?
					__( 'Heart', 'nm-favourites-pro' ) :
					__( 'Heart', 'nm-favourites' ),
					'star' => nm_favourites()->is_pro ?
					__( 'Star', 'nm-favourites-pro' ) :
					__( 'Star', 'nm-favourites' ),
					'bookmark' => nm_favourites()->is_pro ?
					__( 'Bookmark', 'nm-favourites-pro' ) :
					__( 'Bookmark', 'nm-favourites' ),
					'like' => nm_favourites()->is_pro ?
					__( 'Like', 'nm-favourites-pro' ) :
					__( 'Like', 'nm-favourites' ),
					'dislike' => nm_favourites()->is_pro ?
					__( 'Dislike', 'nm-favourites-pro' ) :
					__( 'Dislike', 'nm-favourites' ),
					'' => nm_favourites()->is_pro ?
					__( 'None', 'nm-favourites-pro' ) :
					__( 'None', 'nm-favourites' ),
				],
			),
			array(
				'id' => 'text_before',
				'name' => 'text_before',
				'label' => nm_favourites()->is_pro ?
				__( 'Button text before object is tagged', 'nm-favourites-pro' ) :
				__( 'Button text before object is tagged', 'nm-favourites' ),
				'type' => 'text',
				'value' => nm_favourites()->is_pro ?
				__( 'Favourite', 'nm-favourites-pro' ) :
				__( 'Favourite', 'nm-favourites' ),
			),
			array(
				'id' => 'text_after',
				'name' => 'text_after',
				'label' => nm_favourites()->is_pro ?
				__( 'Button text after object is tagged', 'nm-favourites-pro' ) :
				__( 'Button text after object is tagged', 'nm-favourites' ),
				'type' => 'text',
				'value' => nm_favourites()->is_pro ?
				__( 'Favourite', 'nm-favourites-pro' ) :
				__( 'Favourite', 'nm-favourites' ),
			),
			array(
				'id' => 'title_attr_before',
				'name' => 'title_attr_before',
				'label' => nm_favourites()->is_pro ?
				__( 'Button title attribute before object is tagged', 'nm-favourites-pro' ) :
				__( 'Button title attribute before object is tagged', 'nm-favourites' ),
				'type' => 'text',
				'value' => nm_favourites()->is_pro ?
				__( 'Favourite', 'nm-favourites-pro' ) :
				__( 'Favourite', 'nm-favourites' ),
			),
			array(
				'id' => 'title_attr_after',
				'name' => 'title_attr_after',
				'label' => nm_favourites()->is_pro ?
				__( 'Button title attribute after object is tagged', 'nm-favourites-pro' ) :
				__( 'Button title attribute after object is tagged', 'nm-favourites' ),
				'type' => 'text',
				'value' => nm_favourites()->is_pro ?
				__( 'Favourite', 'nm-favourites-pro' ) :
				__( 'Favourite', 'nm-favourites' ),
			),
			array(
				'id' => 'show_count',
				'name' => 'show_count',
				'value' => 1,
				'type' => 'checkbox',
				'label' => nm_favourites()->is_pro ?
				__( 'Show the number of times the object has been tagged by users', 'nm-favourites-pro' ) :
				__( 'Show the number of times the object has been tagged by users', 'nm-favourites' ),
			),
			array(
				'id' => 'order',
				'name' => 'order',
				'type' => 'number',
				'label' => nm_favourites()->is_pro ?
				__( 'Order', 'nm-favourites-pro' ) :
				__( 'Order', 'nm-favourites' ),
				'desc' => nm_favourites()->is_pro ?
				__( 'Sets the priority for this category when multiple categories appear in a row.', 'nm-favourites-pro' ) :
				__( 'Sets the priority for this category when multiple categories appear in a row.', 'nm-favourites' ),
			),
			array(
				'id' => 'html_tag',
				'name' => 'html_tag',
				'type' => 'select',
				'label' => nm_favourites()->is_pro ?
				__( 'Button appearance', 'nm-favourites-pro' ) :
				__( 'Button appearance', 'nm-favourites' ),
				'options' => [
					'span' => nm_favourites()->is_pro ? __( 'Normal', 'nm-favourites-pro' ) : __( 'Normal', 'nm-favourites' ),
					'button' => nm_favourites()->is_pro ? __( 'Button', 'nm-favourites-pro' ) : __( 'Button', 'nm-favourites' ),
					'a' => nm_favourites()->is_pro ? __( 'Link', 'nm-favourites-pro' ) : __( 'Link', 'nm-favourites' ),
				],
			),
		];

		return $data;
	}

	public function get_appearance_data() {
		$data = $this->appearance_data();

		if ( $this->display_name ) {
			foreach ( $data as $key => $args ) {
				if ( !empty( $args[ 'name' ] ) ) {
					$data[ $key ][ 'id' ] = "display[$this->display_name][{$args[ 'id' ]}]";
					$data[ $key ][ 'name' ] = "display[$this->display_name][{$args[ 'name' ]}]";

					if ( isset( $this->settings[ 'display' ][ $this->display_name ][ $args[ 'name' ] ] ) ) {
						$data[ $key ][ 'value' ] = $this->settings[ 'display' ][ $this->display_name ][ $args[ 'name' ] ];
					}
				}
			}
		}

		return $data;
	}

	public function set_values( $data ) {
		foreach ( $data as $key => $args ) {
			if ( !empty( $args[ 'name' ] ) && array_key_exists( $args[ 'name' ], $this->settings ) ) {
				$data[ $key ][ 'value' ] = $this->settings[ $args[ 'name' ] ];
			}
		}
		return $data;
	}

	protected function post_types() {
		$types = get_post_types( [
			'public' => true,
			'show_ui' => true
			], 'objects' );

		$post_types = [];

		foreach ( $types as $key => $data ) {
			$post_types[ $key ] = $data->label;
		}
		return $post_types;
	}

	protected function search_text() {
		return nm_favourites()->is_pro ?
			__( 'Search&hellip;', 'nm-favourites-pro' ) :
			__( 'Search&hellip;', 'nm-favourites' );
	}

	protected function get_object_types() {
		$types = [
			'post' => [
				'label' => nm_favourites()->is_pro ? __( 'Post', 'nm-favourites-pro' ) : __( 'Post', 'nm-favourites' ),
				'positions' => [
					'display_1' => [
						'before_content' => nm_favourites()->is_pro ?
						__( 'Before content', 'nm-favourites-pro' ) :
						__( 'Before content', 'nm-favourites' ),
						'after_content' => nm_favourites()->is_pro ?
						__( 'After content', 'nm-favourites-pro' ) :
						__( 'After content', 'nm-favourites' ),
					]
				],
			],
			'comment' => [
				'label' => nm_favourites()->is_pro ? __( 'Comment', 'nm-favourites-pro' ) : __( 'Comment', 'nm-favourites' ),
				'positions' => [
					'display_1' => [
						'after_comment' => __( 'After comment', 'nm-favourites-pro' ),
						'before_comment' => __( 'Before comment', 'nm-favourites-pro' ),
						'comment_reply_link' => __( 'Before reply link', 'nm-favourites-pro' ),
					]
				],
			],
		];

		if ( class_exists( \WooCommerce::class ) && post_type_exists( 'product' ) ) {
			$types[ 'product' ] = [
				'label' => nm_favourites()->is_pro ? __( 'Product', 'nm-favourites-pro' ) : __( 'Product', 'nm-favourites' ),
				'positions' => [
					'display_1' => [
						35 => nm_favourites()->is_pro ?
						__( 'After add to cart button', 'nm-favourites-pro' ) :
						__( 'After add to cart button', 'nm-favourites' ),
						1 => nm_favourites()->is_pro ?
						__( 'Before title', 'nm-favourites-pro' ) :
						__( 'Before title', 'nm-favourites' ),
						6 => nm_favourites()->is_pro ?
						__( 'After title', 'nm-favourites-pro' ) :
						__( 'After title', 'nm-favourites' ),
						15 => nm_favourites()->is_pro ?
						__( 'After price', 'nm-favourites-pro' ) :
						__( 'After price', 'nm-favourites' ),
						25 => nm_favourites()->is_pro ?
						__( 'After excerpt', 'nm-favourites-pro' ) :
						__( 'After excerpt', 'nm-favourites' ),
						45 => nm_favourites()->is_pro ?
						__( 'After meta information', 'nm-favourites-pro' ) :
						__( 'After meta information', 'nm-favourites' ),
						'woocommerce_before_single_product_summary' => nm_favourites()->is_pro ?
						__( 'Before thumbnail', 'nm-favourites-pro' ) :
						__( 'Before thumbnail', 'nm-favourites' ),
					]
				],
			];
		}

		return $types;
	}

	protected function get_object_type_options() {
		$options = [];
		foreach ( $this->get_object_types() as $type => $data ) {
			$options[ $type ] = $data[ 'label' ] ?? '';
		}
		return $options;
	}

	public function button_positions() {
		$positions = $this->get_object_types()[ $this->object_type ][ 'positions' ][ $this->display_name ] ?? [];
		if ( !$positions ) {
			$positions = $this->get_object_types()[ 'post' ][ 'positions' ][ $this->display_name ] ?? [];
		}
		$positions[ '' ] = nm_favourites()->is_pro ? __( 'None', 'nm-favourites-pro' ) : __( 'None', 'nm-favourites' );
		return $positions;
	}

	private function parent_categories_options_for_select_html() {
		$options = [];
		$category_id = !empty( $this->category ) ? $this->category->get_id() : null;
		foreach ( $this->get_parent_categories() as $data ) {
			if ( ( int ) $data[ 'id' ] !== $category_id ) {
				$options[ $data[ 'id' ] ] = $data[ 'name' ];
			}
		}
		return $options;
	}

	public function get_parent_categories() {
		global $wpdb;

		$cache_keys = [ 'user_categories', 0, 'parent' ];
		$results = nm_favourites_cache_get( 'nm_favourites_category', $cache_keys );

		if ( false === $results ) {
			$results = $wpdb->get_results( "
			SELECT id, name
			FROM {$wpdb->prefix}nm_favourites_categories
				WHERE user_id = 0 AND parent = 0
			", ARRAY_A );

			nm_favourites_cache_set( 'nm_favourites_category', $cache_keys, $results );
		}

		return $results;
	}

}
