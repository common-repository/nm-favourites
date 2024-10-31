<?php

namespace NM\Favourites\Lib;

defined( 'ABSPATH' ) || exit;

class Ajax {

	public function run() {
		add_action( 'wp_ajax_nm_favourites_post_action', array( $this, 'ajax_action' ) );
		add_action( 'wp_ajax_nopriv_nm_favourites_post_action', array( $this, 'ajax_action' ) );
	}

	public function ajax_action() {
		check_ajax_referer( 'nm_favourites' );

		$action = !empty( $_POST[ 'nm_favourites_post_action' ] ) ?
			sanitize_text_field( wp_unslash( $_POST[ 'nm_favourites_post_action' ] ) ) : '';

		if ( $action ) {
			if ( is_callable( [ $this, $action ] ) ) {
				$this->$action();
			} else {
				do_action( 'nm_favourites_post_action', $action );
			}
		}

		wp_die();
	}

	protected function button_clicked() {
		check_ajax_referer( 'nm_favourites' );
		$button_id = sanitize_text_field( wp_unslash( $_POST[ 'id' ] ?? [] ) );
		$object_id = sanitize_text_field( wp_unslash( $_POST[ 'object_id' ] ?? [] ) );
		$button = nm_favourites()->button( $button_id, $object_id );
		$button->clicked();
	}

	protected function delete_tag() {
		check_ajax_referer( 'nm_favourites' );
		$tag_id = ( int ) wp_unslash( $_POST[ 'tag_id' ] );
		$category_id = ( int ) wp_unslash( $_POST[ 'category_id' ] );
		$tag = nm_favourites()->tag( $category_id );
		$tag->set_id( $tag_id );
		$tag->get_by_id();
		if ( $tag->is_user_own() && $tag->delete() ) {
			$category = nm_favourites()->category( $category_id );

			wp_send_json( [
				'success' => true,
				'replace_templates' => [
					'#nm_favourites_tags_count' => $category->get_user_tags_count_template(),
					"#nm_favourites_tag_$tag_id" => '', // delete tag row in table
				],
			] );
		}
	}

	protected function object_type_selected() {
		check_ajax_referer( 'nm_favourites' );
		$object_type = sanitize_text_field( wp_unslash( $_POST[ 'object_type' ] ) );
		$display = 'display_1';
		$fields_class = nm_favourites()->category_button_fields( '', $object_type, $display );
		$field = [];

		// We're only replacing templates in display_1 section for now.
		foreach ( $fields_class->get_appearance_data() as $field_data ) {
			// Get the field representing the button position
			if ( false !== strpos( ($field_data[ 'name' ] ?? '' ), '[position]' ) ) {
				$field = $field_data;
				break;
			}
		}

		if ( !empty( $field ) ) {
			ob_start();
			nm_favourites()->settings()->output_field( $field );
			$html = ob_get_clean();

			wp_send_json( [
				'replace_templates' => [
					quotemeta( "#$display #{$field[ 'id' ]}" ) => $html,
				],
			] );
		}
	}

}
