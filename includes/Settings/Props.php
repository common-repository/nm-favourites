<?php

namespace NM\Favourites\Settings;

use NM\Favourites\Settings\PluginProps;

defined( 'ABSPATH' ) || exit;

class Props extends PluginProps {

	protected $settings;

	/**
	 * @param int|string|\NM\Favourites\Lib\Category $button_id The id, slugr or object of the category for the button.
	 * @param int|string|boolean $object_id The id of the object to be tagged, typically the post id.
	 * Default is null, meaning that the instance is not associated with any object id.
	 * If value is exactly boolean and true, the class attempts to get the object id from the global context.
	 * @return \NM\Favourites\Sub\Button | \NM\Favourites\Lib\Button
	 */
	public function button( $button_id, $object_id = null ) {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Button::class ) ?
			new \NM\Favourites\Sub\Button( $button_id, $object_id ) :
			new \NM\Favourites\Lib\Button( $button_id, $object_id );
	}

	/**
	 * @return \NM\Favourites\Settings\Settings
	 */
	public function settings() {
		if ( !$this->settings ) {
			$this->settings = new \NM\Favourites\Settings\Settings( $this );
		}
		return $this->settings;
	}

	public function ajax() {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Ajax::class ) ? new \NM\Favourites\Sub\Ajax() : new \NM\Favourites\Lib\Ajax();
	}

	/**
	 * @return \NM\Favourites\Sub\Tag | \NM\Favourites\Lib\Tag
	 */
	public function tag( $category_id = null, $object_id = null, $user_id = null ) {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Tag::class ) ?
			new \NM\Favourites\Sub\Tag( $category_id, $object_id, $user_id ) :
			new \NM\Favourites\Lib\Tag( $category_id, $object_id, $user_id );
	}

	/**
	 * @return \NM\Favourites\Sub\Category | \NM\Favourites\Lib\Category
	 */
	public function category( $id_or_slug = 0, $user_id = null ) {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Category::class ) ?
			new \NM\Favourites\Sub\Category( $id_or_slug, $user_id ) :
			new \NM\Favourites\Lib\Category( $id_or_slug, $user_id );
	}

	public function create_edit_category_fields( $category_id = null ) {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Fields\CreateEditCategoryFields::class ) ?
			new \NM\Favourites\Sub\Fields\CreateEditCategoryFields( $category_id ) :
			new \NM\Favourites\Fields\CreateEditCategoryFields( $category_id );
	}

	/**
	 * @return \NM\Favourites\Sub\Fields\CategoryButtonFields | \NM\Favourites\Fields\CategoryButtonFields
	 */
	public function category_button_fields( $category = null, $object_type = 'post', $display_name = 'display_1' ) {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Fields\CategoryButtonFields::class ) ?
			new \NM\Favourites\Sub\Fields\CategoryButtonFields( $category, $object_type, $display_name ) :
			new \NM\Favourites\Fields\CategoryButtonFields( $category, $object_type, $display_name );
	}

	public function user_tags_table( $category, $args = [] ) {
		return $this->is_pro && class_exists( \NM\Favourites\Sub\Tables\UserTagsTable::class ) ?
			new \NM\Favourites\Sub\Tables\UserTagsTable( $category, $args ) :
			new \NM\Favourites\Tables\UserTagsTable( $category, $args );
	}

}
