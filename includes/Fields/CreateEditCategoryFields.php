<?php

namespace NM\Favourites\Fields;

use NM\Favourites\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class CreateEditCategoryFields extends Fields {

	protected $id = 'create_edit_category';
	protected $category;

	public function __construct( $category = null ) {
		$this->category = $category;
		$this->set_data( $this->data() );
	}

	protected function data() {
		$data = [
			array(
				'name' => 'id',
				'type' => 'hidden',
				'value' => $this->category->get_id(),
			),
			array(
				'name' => 'name',
				'label' => nm_favourites()->is_pro ? __( 'Name', 'nm-favourites-pro' ) : __( 'Name', 'nm-favourites' ),
				'type' => 'text',
				'custom_attributes' => [
					'required' => 1,
					'maxlength' => 255,
				],
				'value' => $this->category->get_name(),
			),
			array(
				'name' => 'description',
				'label' => nm_favourites()->is_pro ? __( 'Description', 'nm-favourites-pro' ) : __( 'Description', 'nm-favourites' ),
				'type' => 'textarea',
				'custom_attributes' => [
					'maxlength' => 400,
				],
				'value' => $this->category->get_description(),
			),
			array(
				'name' => 'visibility',
				'type' => 'hidden',
				'value' => 'public',
			),
			array(
				'name' => 'parent',
				'type' => 'hidden',
				'value' => $this->category->get_parent(),
			),
		];

		return $data;
	}

}
