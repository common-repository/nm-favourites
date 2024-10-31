<?php

namespace NM\Favourites\Fields;

use NM\Favourites\Fields\Fields;

defined( 'ABSPATH' ) || exit;

class GeneralTabSettingsFields extends Fields {

	protected $id = 'general_settings';

	public function __construct() {
		$this->set_data( $this->data() );
	}

	protected function data() {
		$data = [
			[
				'id' => 'page_id',
				'label' => nm_favourites()->is_pro ?
				__( 'Page for users to view their favourites', 'nm-favourites-pro' ) :
				__( 'Page for users to view their favourites', 'nm-favourites' ),
				'type' => 'select_page',
				'default' => '',
				'desc' => nm_favourites()->is_pro ?
				__( 'should contain the shortcode <code>[nm_favourites]</code>', 'nm-favourites-pro' ) :
				__( 'should contain the shortcode <code>[nm_favourites]</code>', 'nm-favourites' ),
			],
			[
				'label' => nm_favourites()->is_pro ?
				__( 'Allow users not logged in to tag objects', 'nm-favourites-pro' ) :
				__( 'Allow users not logged in to tag objects', 'nm-favourites' ),
				'id' => 'enable_guests',
				'default' => 1,
				'type' => 'checkbox',
			],
			[
				'label' => nm_favourites()->is_pro ?
				__( 'Content to show users not logged in if not allowed to tag objects', 'nm-favourites-pro' ) :
				__( 'Content to show users not logged in if not allowed to tag objects', 'nm-favourites' ),
				'id' => 'guest_message',
				'type' => 'editor',
			],
			[
				'label' => nm_favourites()->is_pro ?
				__( 'Delete data on uninstall', 'nm-favourites-pro' ) :
				__( 'Delete data on uninstall', 'nm-favourites' ),
				'id' => 'delete_data',
				'default' => '',
				'type' => 'checkbox',
			],
		];
		return $data;
	}

}
