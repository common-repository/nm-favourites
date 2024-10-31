<?php

namespace NM\Favourites\Fields;

use NM\Favourites\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class Fields {

	// Argumenst used to compose the fields
	protected $args;
	protected $id;
	protected $data = []; // Processed data

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}

	public function set_args( $args ) {
		$this->args = $args;
	}

	public function get_args() {
		return $this->args;
	}

	public function set_data( $data ) {
		$this->data = apply_filters( "nm_favourites_fields_{$this->get_id()}", $data, $this );
	}

	public function get_data() {
		return $this->data;
	}

	public function filter_showing() {
		$this->data = self::get_elements_to_show( $this->data );
	}

	public function filter_showing_in_settings() {
		$this->data = self::get_elements_to_show_in_settings( $this->data );
	}

	public function filter_by_order() {
		if ( !empty( array_column( $this->data, 'order' ) ) ) {
			uasort( $this->data, [ __CLASS__, 'order_sort' ] );
		}
	}

	public function set_values( $function ) {
		$this->set_element( 'value', $function );
	}

	public function set_element( $key, $value ) {
		foreach ( $this->data as $k => $v ) {
			if ( !array_key_exists( $key, $v ) ) {
				$this->data[ $k ][ $key ] = $value;
			}
		}
	}

	public function order() {
		$i = 1;
		foreach ( $this->data as $key => $value ) {
			if ( !isset( $value[ 'order' ] ) ) {
				$this->data[ $key ][ 'order' ] = $i++;
			}
		}
	}

	public static function sort_by_order( &$data ) {
		if ( !empty( array_column( $data, 'order' ) ) ) {
			uasort( $data, [ __CLASS__, 'order_sort' ] );
		}
	}

	public static function order_sort( $a, $b ) {
		$a[ 'order' ] = $a[ 'order' ] ?? 0;
		$b[ 'order' ] = $b[ 'order' ] ?? 0;
		return ( $a[ 'order' ] < $b[ 'order' ] ) ? -1 : 1;
	}

	public static function get_elements_to_show( $data ) {
		foreach ( $data as $key => $args ) {
			$show = array_key_exists( 'show', $args ) ? $args[ 'show' ] : true;
			if ( false === ( bool ) $show ) {
				unset( $data[ $key ] );
			}
		}
		return $data;
	}

	public static function get_elements_to_show_in_settings( $data ) {
		foreach ( $data as $key => $args ) {
			$show_in_settings = array_key_exists( 'show_in_settings', $args ) ? $args[ 'show_in_settings' ] : false;
			if ( Settings::is_settings_screen() && false === ( bool ) $show_in_settings ) {
				unset( $data[ $key ] );
			}
		}
		return $data;
	}

}
