<?php

defined( 'ABSPATH' ) || exit;

function nm_favourites() {
	if ( class_exists( NM_Favourites::class ) ) {
		return NM_Favourites::$setup_class->plugin_props;
	} elseif ( class_exists( NM_Favourites_Pro::class ) ) {
		return NM_Favourites_Pro::$setup_class->plugin_props;
	}
}

function nm_favourites_format_attributes( $attributes ) {
	$attr = array();

	if ( !empty( $attributes ) ) {
		foreach ( ( array ) $attributes as $key => $value ) {
			$val = is_array( $value ) ? implode( ' ', $value ) : $value;
			$attr[] = $key . '="' . $val . '"';
		}
	}
	return implode( ' ', $attr );
}

function nm_favourites_format_number( $number ) {
	$suffix = [ '', 'K', 'M', 'B' ];
	for ( $i = 0; $i < count( $suffix ); $i++ ) {
		$divide = $number / pow( 1000, $i );
		if ( $divide < 1000 ) {
			$number = round( $divide, 1 ) . $suffix[ $i ];
			break;
		}
	}
	return $number;
}

function nm_favourites_get_iconfile( $icon_name ) {
	$file = nm_favourites()->path . 'assets/svg/' . "{$icon_name}.svg";
	if ( file_exists( $file ) ) {
		ob_start();
		include $file;
		return ob_get_clean();
	}
}

function nm_favourites_confirm_text() {
	return nm_favourites()->is_pro ?
		__( 'Are you sure you want to continue?', 'nm-favourites-pro' ) :
		__( 'Are you sure you want to continue?', 'nm-favourites' );
}

function nm_favourites_add_new_text() {
	return nm_favourites()->is_pro ?
		__( 'Add new', 'nm-favourites-pro' ) :
		__( 'Add new', 'nm-favourites' );
}

function nm_favourites_edit_text() {
	return nm_favourites()->is_pro ?
		__( 'Edit', 'nm-favourites-pro' ) :
		__( 'Edit', 'nm-favourites' );
}

function nm_favourites_delete_text() {
	return nm_favourites()->is_pro ?
		__( 'Delete', 'nm-favourites-pro' ) :
		__( 'Delete', 'nm-favourites' );
}

function nm_favourites_post_object_types() {
	return [ 'post', 'product' ];
}

function nm_favourites_cache_delete_group( $group ) {
	wp_cache_delete( $group );
}

function nm_favourites_cache_get_group( $group ) {
	$data = wp_cache_get( $group );
	return is_array( $data ) ? $data : [];
}

function nm_favourites_cache_get( $group, $cache_keys ) {
	$group_data = nm_favourites_cache_get_group( $group );
	$cache_keys_array = ( array ) $cache_keys;

	foreach ( $cache_keys_array as $key ) {
		if ( is_array( $group_data ) && array_key_exists( $key, $group_data ) ) {
			$group_data = &$group_data[ $key ];
		} else {
			return false;
		}
	}
	return $group_data;
}

function nm_favourites_cache_set( $group, $cache_keys, $value ) {
	$group_data = nm_favourites_cache_get_group( $group );
	$reference = &$group_data;

	foreach ( ( array ) $cache_keys as $key ) {
		if ( !array_key_exists( $key, $reference ) ) {
			$reference[ $key ] = [];
		}
		$reference = &$reference[ $key ];
	}

	if ( true ) {
		$reference = $value;
	}

	unset( $reference );
	wp_cache_set( $group, $group_data );
}

function nm_favourites_cache_delete( $group, $cache_keys ) {
	$group_data = nm_favourites_cache_get_group( $group );
	$ref = &$group_data;
	$cache_keys_array = ( array ) $cache_keys;
	$toUnset = null;

	foreach ( $cache_keys_array as $key ) {
		if ( is_array( $ref ) && array_key_exists( $key, $ref ) ) {
			$toUnset = &$ref;
			$ref = &$ref[ $key ];
		} else {
			break;
		}
	}
	unset( $toUnset[ end( $cache_keys_array ) ] );
	wp_cache_set( $group, $group_data );
}
