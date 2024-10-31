<?php

namespace NM\Favourites\Lib;

defined( 'ABSPATH' ) || exit;

class Scripts {

	public static function run() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
	}

	public static function enqueue_frontend_scripts() {
		wp_enqueue_style( 'nm-favourites-frontend' );
		wp_enqueue_script( 'nm-favourites-frontend' );
	}

	public static function enqueue_admin_scripts() {
		if ( nm_favourites()->settings()->is_current_screen() ) {
			self::admin_scripts();
		}
	}

	public static function admin_scripts() {
		wp_enqueue_style( 'nm-favourites-frontend' );
		wp_enqueue_style( 'nm-favourites-select2' );
		wp_enqueue_script( 'nm-favourites-select2' );
		wp_enqueue_script( 'nm-favourites-admin' );
	}

	private static function version() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? gmdate( 'H:i:s' ) : nm_favourites()->version;
	}

	public static function register_admin_scripts() {
		wp_register_style( 'nm-favourites-select2',
			nm_favourites()->url . '/assets/css/vendor/select2.min.css',
			[],
			self::version(),
		);

		wp_register_script( 'nm-favourites-select2',
			nm_favourites()->url . '/assets/js/vendor/select2.min.js',
			[ 'jquery' ],
			self::version(),
			true
		);

		wp_register_script( 'nm-favourites-admin',
			nm_favourites()->url . '/assets/js/admin.min.js',
			[ 'jquery' ],
			self::version(),
			true
		);

		self::add_global_variables_inline_script( 'nm-favourites-admin' );
	}

	public static function register_frontend_scripts() {
		$style_dep = [];
		$script_dep = [ 'jquery' ];

		if ( nm_favourites()->is_pro ) {
			$style_dep[] = 'wp-jquery-ui-dialog';
			$script_dep[] = 'jquery-ui-dialog';
		}


		wp_register_style( 'nm-favourites-frontend', nm_favourites()->url . 'assets/css/frontend.min.css', $style_dep, self::version() );
		wp_register_script( 'nm-favourites-frontend', nm_favourites()->url . 'assets/js/frontend.min.js', $script_dep, self::version(), true );
		self::add_global_variables_inline_script( 'nm-favourites-frontend' );
	}

	protected static function add_global_variables_inline_script( $handle ) {
		$data = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'nm_favourites' ),
		];
		wp_add_inline_script( $handle, 'var nm_favourites_vars = ' . wp_json_encode( $data ), 'before' );
	}

}
