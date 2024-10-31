<?php

namespace NM\Favourites\Lib;

use NM\Favourites\Lib\Display;
use NM\Favourites\Lib\Scripts;
use NM\Favourites\Lib\Admin;
use NM\Favourites\Lib\Shortcodes;
use NM\Favourites\Lib\User;
use NM\Favourites\Lib\Wizard;
use NM\Favourites\Settings\CategoriesTags;

defined( 'ABSPATH' ) || exit;

class Setup {

	/**
	 * @var \NM\Favourites\Settings\Props
	 */
	public $plugin_props;
	public $file;

	public function __construct( $filepath ) {
		$this->file = $filepath;

		spl_autoload_register( array( $this, 'autoload' ) );

		$this->plugin_props = new \NM\Favourites\Settings\Props( $filepath );

		$this->load();
	}

	protected function autoload( $class ) {
		$namespace = 'NM\\Favourites\\';

		if ( !class_exists( $class ) && false !== stripos( $class, $namespace ) ) {
			$path1 = str_replace( $namespace, trailingslashit( dirname( $this->file ) ) . 'includes/', $class );
			$path2 = str_replace( '\\', '/', $path1 );
			$path = $path2 . '.php';

			if ( file_exists( $path ) ) {
				include_once $path;
			}
		}
	}

	public function load() {
		is_admin() ? register_activation_hook( $this->file, array( $this, 'activate' ) ) : '';
		is_admin() ? register_uninstall_hook( $this->file, array( static::class, 'uninstall' ) ) : '';
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_install_and_run' ) );
	}

	public function activate() {
		if ( $this->plugin_props->is_pro ) {
			if ( class_exists( \NM_Favourites::class ) ) {
				deactivate_plugins( \NM_Favourites::$setup_class->plugin_props->basename );
			}
		} else {
			if ( class_exists( \NM_Favourites_Pro::class ) ) {
				deactivate_plugins( \NM_Favourites_Pro::$setup_class->plugin_props->basename );
			}
		}

		Wizard::init();
	}

	public function plugin_row_meta( $links, $file ) {
		if ( $file == $this->plugin_props->basename ) {
			$defaults = [
				'docs_url' => $this->plugin_props->is_pro ?
				__( 'Docs', 'nm-favourites-pro' ) :
				__( 'Docs', 'nm-favourites' ),
				'support_url' => $this->plugin_props->is_pro ?
				__( 'Support', 'nm-favourites-pro' ) :
				__( 'Support', 'nm-favourites' ),
				'review_url' => $this->plugin_props->is_pro ?
				__( 'Review', 'nm-favourites-pro' ) :
				__( 'Review', 'nm-favourites' ),
			];

			foreach ( $defaults as $url => $text ) {
				if ( !empty( $this->plugin_props->{$url} ) ) {
					$links[] = '<a target="_blank" href="' . $this->plugin_props->{$url} . '">' . $text . '</a>';
				}
			}

			if ( !$this->plugin_props->is_pro && !empty( $this->plugin_props->product_url ) ) {
				$links[] = '<a target="_blank" href="' . $this->plugin_props->product_url . '" style="color:#b71401;"><strong>' . __( 'Get PRO', 'nm-favourites' ) . '</strong></a>';
			}
		}
		return $links;
	}

	public function load_plugin_textdomain() {
		$domain = $this->plugin_props->slug;
		load_plugin_textdomain( $domain, false, plugin_basename( dirname( $this->file ) ) . '/languages' );
	}

	public function maybe_install_and_run() {
		include_once $this->plugin_props->path . 'includes/functions.php';

		// Installation action
		if ( version_compare(
				get_option( $this->plugin_props->base . '_version' ),
				$this->plugin_props->version,
				'<'
			) ) {
			add_action( 'init', array( $this, 'install_actions' ), -1 );
		}

		// Run plugin
		$this->run();
	}

	public function install_actions() {
		$this->create_tables();
		$this->add_default_settings();
		update_option( $this->plugin_props->base . '_version', $this->plugin_props->version );
	}

	public function create_tables() {
		global $wpdb;

		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		$tables = "
		  CREATE TABLE {$wpdb->prefix}nm_favourites_tags (
				id BIGINT(20) UNSIGNED NOT NULL auto_increment,
				user_id VARCHAR(40) NOT NULL,
				object_id VARCHAR(255) NOT NULL,
				category_id VARCHAR(255) NOT NULL,
				date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY object_id (object_id),
				KEY category_id (category_id)
			 ) $collate;
		  CREATE TABLE {$wpdb->prefix}nm_favourites_categories (
			 id BIGINT(20) UNSIGNED NOT NULL auto_increment,
			 name VARCHAR(255) NOT NULL,
			 slug VARCHAR(255) NOT NULL,
			 user_id VARCHAR(32) NULL,
			 description VARCHAR(400) NULL,
			 visibility VARCHAR(20) NULL,
			 parent BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			 meta TEXT NULL,
			 PRIMARY KEY  (id),
			 KEY slug (slug),
			 KEY user_id (user_id),
			 KEY parent (parent)
		  ) $collate;
		  ";
		// update schema with dbdelta
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $tables );
	}

	public function add_default_settings() {
		$existing_settings = get_option( 'nm_favourites_settings', [] );
		$db_version = get_option( nm_favourites()->base . '_version' );

		if ( !$existing_settings || !$db_version ) {
			$default_settings = nm_favourites()->settings()->get_default_field_values();

			if ( !$existing_settings ) {
				add_option( 'nm_favourites_settings', $default_settings );
			} else {
				/**
				 * If we have existing settings but we don't have the plugin version registered in the database
				 * it means we are moving from lite to pro version or vice versa, so we update plugin settings afresh.
				 */
				update_option( 'nm_favourites_settings', array_merge( $default_settings, $existing_settings ) );
			}
		}
	}

	public function run() {
		add_filter( 'plugin_action_links_' . $this->plugin_props->basename, array( $this, 'plugin_action_links' ) );

		Wizard::run();
		Scripts::run();
		Admin::run();
		Display::run();
		$this->plugin_props->settings()->run();
		$this->plugin_props->ajax()->run();
		(new User)->run();
		(new Shortcodes)->run();
	}

	public function plugin_action_links( $links ) {
		$settings = $this->plugin_props->settings();
		$categories_tags = new CategoriesTags();
		return array_merge(
			[
				'<a href="' . $settings->get_page_url() . '">' . $settings->get_heading_text() . '</a>',
				'<a href="' . $categories_tags->url() . '">' . $categories_tags->heading_text() . '</a>',
			],
			$links
		);
	}

	public static function uninstall() {
		global $wpdb;

		$plugin_props = class_exists( \NM_Favourites::class ) ?
			\NM_Favourites::$setup_class->plugin_props :
			\NM_Favourites_Pro::$setup_class->plugin_props;

		$lite_installed = file_exists( WP_PLUGIN_DIR . '/nm-favourites/nm-favourites.php' );
		$pro_installed = file_exists( WP_PLUGIN_DIR . '/nm-favourites-pro/nm-favourites-pro.php' );

		if ( !$plugin_props->settings()->get_option( 'delete_data' ) || ($lite_installed && $pro_installed) ) {
			return;
		}

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nm_favourites_categories" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}nm_favourites_tags" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '%%nm_favourites\_%%';" );
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '%%nm_favourites\_%%';" );
		$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '%%nm_favourites\_%%';" );

		wp_cache_flush();
	}

}
