<?php

namespace NM\Favourites\Settings;

use NM\Favourites\Settings\PluginSettings;
use NM\Favourites\Settings\CategoriesTags;

defined( 'ABSPATH' ) || exit;

class Settings extends PluginSettings {

	public $option_name = 'nm_favourites_settings';

	public function run() {
		parent::run();
		(new CategoriesTags)->run();
	}

	/**
	 * Set the page name explicitly so that it would be the same for both lite and pro versions
	 */
	public function page_name() {
		return nm_favourites()->is_pro ?
			__( 'NM Favourites', 'nm-favourites-pro' ) :
			__( 'NM Favourites', 'nm-favourites' );
	}

	public function menu_page() {
		$args = parent::menu_page();
		$args[ 'icon_url' ] = 'dashicons-star-filled';
		$args[ 'page_title' ] = $this->get_heading_text() . ' &ndash; ' . $this->page_name();
		return $args;
	}

	protected function get_tabs() {
		$tabs = [
			'general' => array(
				'tab_title' => nm_favourites()->is_pro ?
				__( 'General', 'nm-favourites-pro' ) :
				__( 'General', 'nm-favourites' ),
				'sections' => [
					'general' => [
						'fields' => (new \NM\Favourites\Fields\GeneralTabSettingsFields())->get_data()
					],
				],
			),
		];

		return $tabs;
	}

	public function get_sidebar() {
		$sidebar = parent::get_sidebar();

		ob_start();
		?>
		<h3>
			<?php
			nm_favourites()->is_pro ? esc_html_e( 'Shortcodes', 'nm-favourites-pro' ) : esc_html_e( 'Shortcodes', 'nm-favourites' );
			?>
		</h3>
		<p>
			<strong><code>[nm_favourites id=like]</code></strong>
			<br>
			<?php
			nm_favourites()->is_pro ?
					esc_html_e( 'Show the button for tagging the current object in the like category.', 'nm-favourites-pro' ) :
					esc_html_e( 'Show the button for tagging the current object in the like category.', 'nm-favourites' );
			?>
		</p>
		<p>
			<strong><code>[nm_favourites id=like object_id=1]</code></strong>
			<br>
			<?php
			nm_favourites()->is_pro ?
					esc_html_e( 'Show the button for tagging the object with id 1 in the like category.', 'nm-favourites-pro' ) :
					esc_html_e( 'Show the button for tagging the object with id 1 in the like category.', 'nm-favourites' );
			?>
		</p>
		<p>
			<strong><code>[nm_favourites id=like object_id=1 show=users per_page=10]</code></strong>
			<br>
			<?php
			nm_favourites()->is_pro ?
					esc_html_e( 'Show the users that have tagged the object with id 1 in the like category with 10 items per page.', 'nm-favourites-pro' ) :
					esc_html_e( 'Show the users that have tagged the object with id 1 in the like category with 10 items per page.', 'nm-favourites' );
			?>
		</p>
		<p>
			<strong><code>[nm_favourites id=like show=tags per_page=10]</code></strong>
			<br>
			<?php
			nm_favourites()->is_pro ?
					esc_html_e( 'Show the tagged objects in the like category with 10 items per page.', 'nm-favourites-pro' ) :
					esc_html_e( 'Show the tagged objects in the like category with 10 items per page.', 'nm-favourites' );
			?>
		</p>
		<p>
			<strong><code>[nm_favourites id=like show=most_tagged limit=10]</code></strong>
			<br>
			<span>
				<?php
				nm_favourites()->is_pro ?
						esc_html_e( 'Show the 10 most tagged objects in the like category.', 'nm-favourites-pro' ) :
						esc_html_e( 'Show the 10 most tagged objects in the like category.', 'nm-favourites' );
				?>
			</span>
		</p>
		<p>
			<strong><code>[nm_favourites]</code></strong>
			<br>
			<strong><code>[nm_favourites show=categories per_page=10]</code></strong>
			<br>
			<span>
				<?php
				nm_favourites()->is_pro ?
						esc_html_e( 'Show all the categories that the current user can tag objects to.', 'nm-favourites-pro' ) :
						esc_html_e( 'Show all the categories that the current user can tag objects to.', 'nm-favourites' );
				?>
			</span>
		</p>
		<?php
		$sidebar .= ob_get_clean();
		return $sidebar;
	}

}
