<?php

namespace NM\Favourites\Lib;

defined( 'ABSPATH' ) || exit;

class User {

	protected $id;

	public function __construct( $id = null ) {
		if ( is_null( $id ) ) {
			$user_id = get_current_user_id();
			$this->id = $user_id ? $user_id : ($this->is_guest_enabled() ? $this->get_guest_id() : null);
		} else {
			$this->id = $id;
		}
	}

	public function run() {
		/**
		 * Set cookies on init priority 1 so that they may be available for all code that runs onward
		 * such as shortcodes which which run on standard init priority 10
		 */
		add_action( 'init', array( $this, 'maybe_set_cookies' ), 1 );
	}

	public function get_id() {
		return ( string ) $this->id;
	}

	public function is_valid() {
		return !empty( $this->id ) && (is_numeric( $this->id ) || $this->is_guest_enabled());
	}

	/**
	 * Whether the current user is the same as the logged in user
	 */
	public function is_same() {
		$current_user = $this->get_id();
		$logged_in_user = (new self)->get_id();
		return $current_user && ($current_user === $logged_in_user);
	}

	public function is_guest() {
		return !$this->id || ($this->id && !is_numeric( $this->id ));
	}

	public function is_guest_enabled() {
		return !empty( nm_favourites()->settings()->get_option( 'enable_guests' ) );
	}

	/**
	 * @return string
	 */
	public function get_guest_id() {
		return isset( $_COOKIE[ 'nm_favourites_user_id' ] ) ?
			sanitize_key( wp_unslash( $_COOKIE[ 'nm_favourites_user_id' ] ) ) :
			null;
	}

	public function maybe_set_cookies() {
		if ( !$this->is_valid() && $this->is_guest() && $this->is_guest_enabled() ) {
			if ( empty( $_COOKIE[ 'nm_favourites_user_id' ] ) ) {
				require_once ABSPATH . 'wp-includes/class-phpass.php';
				$hasher = new \PasswordHash( 8, false );
				$user_id = md5( $hasher->get_random_bytes( 32 ) );
				if ( $this->set_cookie( 'nm_favourites_user_id', $user_id ) ) {
					$_COOKIE[ 'nm_favourites_user_id' ] = $user_id;
				}
			}
		}
	}

	protected function set_cookie( $key, $value ) {
		if ( !headers_sent() ) {
			// 2147483647 is maximum expiry age possible (2038)
			return setcookie( $key, $value, 2147483647, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, false, false );
		}
	}

}
