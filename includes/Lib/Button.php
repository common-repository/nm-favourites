<?php

namespace NM\Favourites\Lib;

defined( 'ABSPATH' ) || exit;

class Button {

	/**
	 * @var \NM\Favourites\Sub\Tag | \NM\Favourites\Lib\Tag
	 */
	protected $tag;

	/**
	 * @var \NM\Favourites\Sub\Category | \NM\Favourites\Db\Category
	 */
	protected $category;
	protected $id;
	protected $settings = [];
	protected $is_enabled = true;
	protected $object_id;
	protected $plugin_settings = [];
	protected $text_template;
	protected $icon_template;
	protected $count_template;
	protected $attributes;
	protected $preview = false;
	protected $response = [];

	/**
	 * @param int|string|\NM\Favourites\Lib\Category $button_id The id, slugr or object of the category for the button.
	 * @param int|string|boolean $object_id The id of the object to be tagged, typically the post id.
	 * Default is null, meaning that the instance is not associated with any object id.
	 * If value is exactly boolean and true, the class attempts to get the object id from the global context.
	 */
	public function __construct( $button_id, $object_id = null ) {
		$this->category = nm_favourites()->category( $button_id );
		$this->id = $this->category->get_id();
		$this->plugin_settings = nm_favourites()->settings()->get_option();
		$this->settings = $this->category->get_meta();
		$this->set_display( 'display_1' );

		if ( !$object_id ) {
			$this->tag = nm_favourites()->tag( $this->id );
		} else {
			$this->object_id = true === $object_id ? $this->get_default_object_id() : $object_id;
			$this->tag = nm_favourites()->tag( $this->id, $this->object_id );
			$this->tag->get();
		}
	}

	public function get_id() {
		return $this->id;
	}

	public function get_object_id() {
		return $this->object_id;
	}

	public function get_default_object_id() {
		if ( in_array( $this->get_object_type(), nm_favourites_post_object_types() ) ) {
			$id = get_the_ID();
		} else {
			switch ( $this->get_object_type() ) {
				case 'comment':
					$id = get_comment_ID();
					break;
			}
		}
		return $id ?? null;
	}

	public function get_object_type() {
		return $this->category->get_object_type();
	}

	public function settings() {
		return $this->settings;
	}

	public function tag() {
		return $this->tag;
	}

	public function category() {
		return $this->category;
	}

	public function set_preview( $bool ) {
		$this->preview = $bool;
	}

	protected function set_display( $display_key ) {
		$this->settings = array_merge( $this->settings, ($this->settings[ 'display' ][ $display_key ] ?? [] ) );
		$this->text_template = null;
		$this->count_template = null;
		$this->icon_template = null;
		$this->attributes = null;
	}

	public function is_object_enabled() {
		// disable button for homepage
		$enabled = ($this->object_id && $this->object_id !== ( int ) get_option( 'page_on_front' ));

		// disable button for favourites page
		$page_id = ( int ) ($this->plugin_settings[ 'page_id' ] ?? 0);
		if ( $page_id && is_page( $page_id ) ) {
			$enabled = false;
		}

		// Disable button on woocommerce my account page
		if ( $enabled && function_exists( 'wc' ) && function_exists( 'is_account_page' ) && is_account_page() ) {
			$enabled = false;
		}

		return $enabled;
	}

	public function is_enabled() {
		if ( !$this->id ||
			($this->settings && empty( $this->settings[ 'enabled' ] ) ) ||
			false === $this->is_object_enabled() ) {
			$this->is_enabled = false;
		}

		$this->is_enabled = ($this->preview && $this->settings) ? true :
			apply_filters( 'nm_favourites_enable_button', $this->is_enabled, $this );

		return $this->is_enabled;
	}

	public function clicked() {
		if ( $this->is_enabled() ) {
			if ( $this->tag->user->is_valid() ) {
				$this->default_click_action();
				$this->show_response();
			} else {
				$guest_message = $this->plugin_settings[ 'guest_message' ] ?? '';
				if ( $guest_message ) {
					$modal = new Modal();
					$modal->set_opacity( 0.3 );
					$modal->set_option( 'minWidth', 500 );
					$modal->set_id( 'nm-favourites-guest-message-dialog' );
					$modal->set_content( wpautop( $guest_message ) );
					$this->response[ 'show_template' ] = $modal->get();
					$this->show_response();
				}
			}
		}
	}

	protected function default_click_action() {
		$this->toggle_tag();
	}

	public function toggle_tag() {
		if ( $this->tag->exists() ) {
			$this->delete_tag();
		} else {
			$this->create_tag();
		}
	}

	public function create_tag() {
		if ( $this->tag->is_user_own() && $this->tag->create() ) {
			$this->response = array_merge_recursive( $this->response, $this->get_replace_template_response() );

			$toggle_with = $this->settings[ 'toggle_with' ] ?? [];
			if ( !empty( $toggle_with ) ) {
				foreach ( $toggle_with as $category_id ) {
					$button = nm_favourites()->button( $category_id, $this->get_object_id() );
					$button->delete_tag();
					$this->response = array_merge_recursive( $this->response, $button->get_response() );
				}
			}
		}
	}

	public function delete_tag() {
		if ( $this->tag->is_user_own() && $this->tag->delete() ) {
			$this->response = $this->get_replace_template_response();
		}
	}

	public function get_replace_template_response() {
		return [
			'replace_templates' => [
				$this->get_selector() => $this->get(),
			],
		];
	}

	public function set_response( $response ) {
		$this->response = $response;
	}

	public function get_response() {
		return apply_filters( 'nm_favourites_button_response', $this->response, $this );
	}

	public function show_response() {
		wp_send_json( $this->get_response() );
	}

	public function is_favourite() {
		return ( bool ) $this->tag->get_id();
	}

	public function state() {
		return $this->is_favourite() ? 'after' : 'before';
	}

	protected function get_selector() {
		$attr = $this->get_attributes();
		$id = $attr[ 'container' ][ 'data-id' ];
		$object_id = $attr[ 'container' ][ 'data-object_id' ];
		return ".nm-favourites-btn[data-id='$id'][data-object_id='$object_id']";
	}

	public function get_attributes() {
		if ( $this->attributes ) {
			return $this->attributes;
		}

		$this->attributes = [
			'container' => [
				'tag' => 'span',
				'class' => array_filter( [
					'nm-favourites-btn',
					$this->category->get_slug(),
					$this->preview ? 'nm-favourites-preview' : '',
					$this->is_favourite() ? 'tagged' : '',
					empty( $this->settings[ 'custom_button' ] ) && $this->icon_template() &&
					!$this->text_template() && !$this->count_template() ? 'icon-only' : '',
				] ),
				'data-id' => $this->id,
				'data-object_id' => $this->object_id,
				'data-nm_favourites_post_action' => 'button_clicked',
				'data-autopost' => $this->preview ? false : true,
			],
			'button' => [
				'tag' => $this->settings[ 'html_tag' ] ?? 'span',
				'class' => [
					'nm-favourites-btn-content',
				],
				'title' => $this->is_favourite() ?
				($this->settings[ 'title_attr_after' ] ?? '') :
				($this->settings[ 'title_attr_before' ] ?? ''),
			],
		];

		if ( 'link' === ($this->settings[ 'html_tag' ] ?? '') ) {
			$this->attributes[ 'button' ][ 'href' ] = '#';
		}

		if ( $this->preview ) {
			if ( 'button' === $this->attributes[ 'button' ][ 'tag' ] ) {
				$this->attributes[ 'button' ][ 'disabled' ] = true;
				$this->attributes[ 'button' ][ 'class' ][] = 'button';
			}
		}

		$this->attributes = apply_filters( 'nm_favourites_button_attributes', $this->attributes, $this );
		return $this->attributes;
	}

	public function get() {
		if ( !$this->is_enabled() ) {
			return;
		}

		$attr = $this->get_attributes()[ 'container' ];
		$tag = $attr[ 'tag' ];
		unset( $attr[ 'tag' ] );
		$formattted_attr = nm_favourites_format_attributes( $attr );
		return "<$tag $formattted_attr>" . $this->get_content() . "</$tag>";
	}

	public function show() {
		echo wp_kses( $this->get(), nm_favourites()->settings()->allowed_post_tags() );
	}

	public function text() {
		return $this->settings[ "text_{$this->state()}" ] ?? '';
	}

	public function text_template( $show = true ) {
		$this->text_template = in_array( $show, [ false, null ], true ) ? $show : $this->text_template;
		if ( is_null( $this->text_template ) ) {
			$text = $this->text();
			$this->text_template = $text ? '<span class="nm-favourites-btn-text nm-favourites-item">' . $text . '</span>' : '';
		}
		return $this->text_template;
	}

	public function icon( $state = 'before' ) {
		$icon_type = $this->settings[ 'icon' ] ?? '';
		$icon_name = 'before' === $state ? $icon_type . '-empty' : $icon_type;
		return nm_favourites_get_iconfile( $icon_name );
	}

	public function icon_template( $show = true ) {
		$this->icon_template = in_array( $show, [ false, null ], true ) ? $show : $this->icon_template;
		if ( is_null( $this->icon_template ) ) {
			$icon = $this->icon( $this->state() );
			$this->icon_template = $icon ? '<span class="nm-favourites-btn-icon nm-favourites-item">' . $icon . '</span>' : '';
		}
		return $this->icon_template;
	}

	public function count() {
		return ( int ) apply_filters( 'nm_favourites_button_count', $this->tag->get_object_count(), $this );
	}

	public function count_template( $show = true ) {
		$this->count_template = in_array( $show, [ false, null ], true ) ? $show : $this->count_template;
		if ( is_null( $this->count_template ) ) {
			if ( !empty( $this->settings[ 'show_count' ] ) ) {
				$this->count_template = '<span class="nm-favourites-btn-count nm-favourites-item">' . nm_favourites_format_number( $this->count() ) . '</span>';
			}
		}
		return $this->count_template;
	}

	protected function get_content() {
		if ( !empty( $this->settings[ 'custom_button' ] ) ) {
			if ( !$this->is_favourite() && !empty( $this->settings[ 'custom_button_before' ] ) ) {
				$btn = str_replace(
					[ '{text}', '{icon}', '{count}' ],
					[ $this->text_template(), $this->icon_template(), $this->count_template() ],
					$this->settings[ 'custom_button_before' ]
				);
			} elseif ( $this->is_favourite() && !empty( $this->settings[ 'custom_button_after' ] ) ) {
				$btn = str_replace(
					[ '{text}', '{icon}', '{count}' ],
					[ $this->text_template(), $this->icon_template(), $this->count_template() ],
					$this->settings[ 'custom_button_after' ]
				);
			}
		} else {
			$attr = $this->get_attributes()[ 'button' ];
			$tag = $attr[ 'tag' ];
			unset( $attr[ 'tag' ] );

			$btn = "<$tag " . nm_favourites_format_attributes( $attr ) . '>' .
				$this->icon_template() . $this->text_template() . $this->count_template() .
				"</$tag>";
		}

		return $btn ?? '';
	}

	public function get_count_key() {
		return $this->category->get_count_key();
	}

}
