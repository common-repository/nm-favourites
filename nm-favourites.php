<?php

/**
 * Plugin Name: NM Favourites
 * Plugin URI: https://wordpress.org/plugins/nm-favourites
 * Description: Add favourite, bookmark, like, dislike, wishlist and all kinds of buttons to posts, pages, custom post types, comments, taxonomies, woocommerce products, and other object types. Allow users to create multiple private or public collections. <a href="https://nmerimedia.com/product-category/plugins/" target="_blank">See more plugins&hellip;</a>
 * Author: Nmeri Media
 * Author URI: https://nmerimedia.com
 * License: GPL V3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html\v1
 * Version: 1.2.1
 * Domain Path: /languages/
 * Review URI: https://wordpress.org/support/plugin/nm-favourites/reviews?rate=5#new-post
 * Docs URI: https://docs.nmerimedia.com/doc/nm-favourites/
 * Product URI: https://nmerimedia.com/product/nm-favourites-pro/
 * Support URI: https://nmerimedia.com/contact/
 * Requires at least: 4.7.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;

class NM_Favourites {

	/**
	 * @var NM\Favourites\Lib\Setup
	 */
	public static $setup_class;

	public static function run() {
		if ( !class_exists( NM\Favourites\Lib\Setup::class ) ) {
			include_once 'includes/Lib/Setup.php';
		}
		self::$setup_class = new NM\Favourites\Lib\Setup( __FILE__ );
	}

}

NM_Favourites::run();

