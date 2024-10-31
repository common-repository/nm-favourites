<?php

namespace NM\Favourites\Settings;

use NM\Favourites\Sub\License;
use NM\Favourites\Setup\ReadmeParser;
use NM\Favourites\Settings\PluginProps;

defined( 'ABSPATH' ) || exit;

abstract class PluginSettings {

	/**
	 * The current tab we are on
	 * @var string
	 */
	protected $current_tab;

	/**
	 * The current section we are on
	 * @var string
	 */
	protected $current_section;

	/**
	 * Name of options in the database table
	 * (also used as the options_group value in the 'register_setting' function)
	 * @var string
	 */
	public $option_name;

	/**
	 * Whether this page is a woocommerce screen, so that we can enqueue woocommerce scripts
	 * @var boolean
	 */
	public $is_woocommerce_screen = false;
	public $plugin_props;
	protected $tabs;

	/**
	 * Set up settings menu and page for this plugin
	 * @param PluginProps $plugin_props The plugin properties object
	 */
	public function __construct( PluginProps $plugin_props ) {
		$this->plugin_props = $plugin_props;

		if ( !$this->option_name && !empty( $this->plugin_props->base ) ) {
			$this->option_name = $this->plugin_props->base . '_settings';
		}
	}

	public function run() {
		add_filter( 'woocommerce_screen_ids', array( $this, 'add_woocommerce_screen_id' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_head', array( $this, 'style' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'show_saved_settings_errors' ) );

		if ( $this->option_name ) {
			add_filter( "sanitize_option_{$this->option_name}", [ $this, 'sanitize' ] );
			add_filter( 'pre_update_option_' . $this->option_name, array( $this, 'pre_update_option' ), 10, 2 );
			add_action( 'update_option_' . $this->option_name, array( $this, 'update_option' ), 10, 2 );
		}

		if ( $this->plugin_props->is_licensed && class_exists( License::class ) ) {
			(new License( $this ))->run();
		}

		if ( $this->is_current_page() ) {
			$this->current_tab = $this->get_current_tab();
			$this->current_section = $this->get_current_section();
		}
	}

	/**
	 * The standard nonce action used in the options page, inserted with settings_fields().
	 * Use with check_admin_referer() to verify the nonce
	 * @return string
	 */
	protected function nonce_action() {
		return $this->option_name . '-options';
	}

	/**
	 * Add the screen id of the current plugin settings page to the
	 * array of woocommerce screen ids
	 */
	public function add_woocommerce_screen_id( $screen_ids ) {
		if ( $this->is_woocommerce_screen && $this->is_current_screen() ) {
			$screen_ids[] = $this->get_screen_id();
		}
		return $screen_ids;
	}

	/**
	 * Get the screen id of the current plugin settings page
	 * @return string
	 */
	public function get_screen_id() {
		return 'toplevel_page_' . $this->page_slug();
	}

	/**
	 * Check if the current screen being viewed is for this settings page
	 * @return boolean
	 */
	public function is_current_screen() {
		return self::get_current_screen_id() === $this->get_screen_id();
	}

	private function get_current_screen_id() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			return $screen ? $screen->id : null;
		}
	}

	public function is_current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return $this->page_slug() === sanitize_text_field( wp_unslash( $_GET[ 'page' ] ?? [] ) );
	}

	public function style() {
		if ( !$this->is_current_screen() ) {
			return;
		}

		$this->checkbox_styles();
		?>
		<style>
			.wrap.nmerimedia-settings table.form-table:last-of-type {
				margin-bottom: 3.125em;
			}

			.wrap.nmerimedia-settings ~ h2.heading:not(:first-of-type) {
				margin-top: 3.75em;
			}

			.wrap.nmerimedia-settings label .nm-desc {
				display: inline;
			}

			.wrap.nmerimedia-settings .nm-desc:not(label .nm-desc) {
				margin-top: 8px;
			}

			.nmerimedia-btn-group > .nmerimedia-btn input[type=checkbox],
			.nmerimedia-btn-group > .nmerimedia-btn input[type=radio] {
				position: absolute;
				clip: rect(0, 0, 0, 0);
				pointer-events: none;
			}

			.nmerimedia-btn-group	input + label {
				font-weight: normal;
				margin: 0;
				cursor: pointer;
				text-align: center;
				display: inline-flex;
				flex-flow: column;
				padding: 0.5em 0.75em;
				transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
			}

			.nmerimedia-btn-group.nmerimedia-inline input + label {
				align-items: center;
			}

			.nmerimedia-btn-group input + label:hover,
			.nmerimedia-btn-group	input:checked + label,
			.nmerimedia-btn-group	input:focus + label {
				background-color: rgba(0, 124, 186, 0.08);
				border-color: #007cba;
				box-shadow: 0 0 0 1px #007cba;
				outline: 2px solid transparent;
			}

			.nmerimedia-btn-group	input + label.icon {
				display: inline-block;
				padding: 0;
				line-height: 1 !important;
				background-color: transparent;
			}

			.nmerimedia-btn-group	input:checked + label.icon .nmerimedia-icon:not(.checked) {
				display: none;
			}

			.nmerimedia-btn-group	input:not(:checked) + label.icon .nmerimedia-icon.checked {
				display: none;
			}

			.nmerimedia-input-group:not(.nmerimedia-inline) > * {
				margin-bottom: .375em;
			}

			.nmerimedia-input-group.nmerimedia-inline {
				display: flex;
				align-items: flex-end;
			}

			.nmerimedia-input-group.nmerimedia-inline > *:not(:last-child) {
				margin-right: 1.875em;
			}

			.nmerimedia-settings-error {
				color: red;
			}

			.nmerimedia-pro-version-text {
				color: indianred;
			}

			.wrap.nmerimedia-settings .main {
				flex-grow: 1;
			}

			.wrap.nmerimedia-settings .is-pro {
				opacity: .4;
				pointer-events: none;
			}

			.wrap.nmerimedia-settings .sidebar {
				padding: 0 15px;
				border-left: 1px solid #dedede;
			}

			.nmerimedia-settings select {
				min-width: 400px;
			}

			@media (min-width: 783px) {
				.wrap.nmerimedia-settings .container {
					display: flex;
					width: 100%;
				}

				.wrap.nmerimedia-settings .main {
					padding-right: 15px;
				}

				.wrap.nmerimedia-settings .sidebar {
					width: 255px;
					min-width: 255px;
				}
			}
		</style>
		<?php
	}

	/**
	 * Parameters to use with add_menu_page for the parent menu
	 * if the settings page would be a submenu
	 *
	 * @return array
	 */
	public function menu_page() {
		return array(
			'page_title' => $this->page_name(),
			'menu_title' => $this->page_name(),
			'capability' => 'manage_options',
			'menu_slug' => $this->page_slug(),
			'function' => array( $this, 'menu_page_content' ),
			'icon_url' => 'data:image/svg+xml;base64,' . base64_encode( $this->nmerimedia_svg() ),
			'position' => 50
		);
	}

	public function page_name() {
		return $this->plugin_props->name;
	}

	/**
	 * Slug of settings page
	 * @return string
	 */
	public function page_slug() {
		return sanitize_title( $this->page_name() );
	}

	/**
	 * Get the url of the plugin settings page
	 * @return string
	 */
	public function get_page_url() {
		$slug = $this->page_slug();
		return $slug ? menu_page_url( $slug, false ) : '';
	}

	private function tabs() {
		if ( !$this->tabs ) {
			$this->tabs = $this->get_tabs();

			/**
			 * array_reverse() is used to make sure tabs are show in order of declaration
			 * in a situation where the 'order' key is not used to sort them.
			 */
			$this->tabs = array_reverse( $this->tabs );
			uasort( $this->tabs, function ( $a, $b ) {
				$a[ 'order' ] = $a[ 'order' ] ?? 0;
				$b[ 'order' ] = $b[ 'order' ] ?? 0;
				return ( $a[ 'order' ] < $b[ 'order' ] ) ? -1 : 1;
			} );
		}
		return $this->tabs;
	}

	/**
	 * Get all the settings tabs registered for the plugin
	 *
	 * This returns an array of arrays where each array key represents the slug for the
	 * settings tab and the array value is an array containing keys:
	 * - tab_title {string}  The title of the tab
	 * - sections_title {string} The title to show for all the tab sections
	 * - show_sections {boolean} whether to show the sections all at once (using do_settings_sections)
	 * - sections {array} Sections to show on the tab where each array key represents the section slug and
	 * 		                the array value represents the section title.
	 *
	 * @return array
	 */
	protected function get_tabs() {
		return array();
	}

	/**
	 * Actions to perform before an option is updated.
	 * Use this only if you have set the property $option_name and you are saving options.
	 */
	public function pre_update_option( $new_value, $old_value ) {
		return $new_value;
	}

	/**
	 * Actions to perform before an option is updated.
	 * Use this only if you have set the property $option_name and you are saving options.
	 */
	public function update_option( $old_value, $new_value ) {

	}

	/**
	 * Get the saved settings option from the database
	 *
	 * @param string {optional} The field key to get from the options array
	 * @param mixed  {optional} The value to set for the field key if it doesn't exist
	 * @return mixed The field key value or the entire option value if no field key is specified.
	 */
	public function get_option( $field_key = '', $default_value = null ) {
		$option = get_option( $this->option_name, array() );
		$options = is_array( $option ) ? $option : (!$option ? array() : array( $option ));
		if ( $field_key ) {
			return array_key_exists( $field_key, $options ) ? $options[ $field_key ] : $default_value;
		}
		return $options;
	}

	public function add_menu_page() {
		add_menu_page(
			$this->menu_page()[ 'page_title' ],
			$this->menu_page()[ 'menu_title' ],
			$this->menu_page()[ 'capability' ],
			$this->menu_page()[ 'menu_slug' ],
			$this->menu_page()[ 'function' ],
			$this->menu_page()[ 'icon_url' ],
			$this->menu_page()[ 'position' ]
		);

		add_submenu_page(
			$this->menu_page()[ 'menu_slug' ],
			$this->menu_page()[ 'page_title' ],
			$this->get_heading_text(),
			$this->menu_page()[ 'capability' ],
			$this->menu_page()[ 'menu_slug' ],
			$this->menu_page()[ 'function' ],
		);

		if ( !empty( $this->submenu_pages() ) ) {
			foreach ( $this->submenu_pages() as $submenu_page ) {
				add_submenu_page(
					$this->menu_page()[ 'menu_slug' ],
					$submenu_page[ 'page_title' ],
					$submenu_page[ 'menu_title' ],
					$this->menu_page()[ 'capability' ],
					$submenu_page[ 'menu_slug' ],
					$submenu_page[ 'content' ] ?? '',
				);
			}
		}
	}

	/**
	 * Submenu pages that should be connected to the parent page
	 * This should be an array of arrays with each internal array containing only the
	 * page_title, menu_title, and menu_slug keys and values.
	 * Other parameters for the submenu page would be supplied automatically from the parent menu page params
	 * @return array
	 */
	protected function submenu_pages() {
		return [];
	}

	/**
	 * The key used to save the settings errors in the database
	 * @return string.
	 */
	public function get_settings_errors_key() {
		if ( $this->option_name ) {
			return $this->option_name . '_errors';
		}
	}

	/**
	 * The main heading of the settings page.
	 * (This comes before the settings tabs)
	 * @return string
	 */
	public function get_heading_text() {
		return $this->plugin_props->is_pro ?
			__( 'Settings', 'nm-favourites-pro' ) :
			__( 'Settings', 'nm-favourites' );
		;
	}

	/**
	 * Check if we are on a setting page but not on a registered settings tab or section.
	 * This is typically used when we want to create an additional settings page based off a tab section.
	 * In this case we add the 'custom' query string to the browser url so that we use it to display the
	 * custom contents of our page instead of the typical tab or section content.
	 *
	 * The content of the custom page is displayed using the function custom_page_content() that is extended
	 * in the child class of this settings class.
	 *
	 * @return boolean
	 */
	protected function is_custom_page() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return !empty( $_GET[ 'custom' ] );
	}

	/**
	 * Outputs the template (tabs, tab sections) for the menu page content
	 * Override this if you want to set a custom menu page content
	 */
	public function menu_page_content() {
		$tabs = $this->tabs();
		?>
		<div class="wrap nmerimedia-settings <?php echo esc_attr( $this->page_slug() ) . ' ' . esc_attr( $this->current_tab ); ?>">
			<?php if ( !empty( $this->get_heading_text() ) ) : ?>
				<h1><?php echo esc_html( $this->get_heading_text() ); ?></h1>
			<?php endif; ?>
			<form method="post" action="options.php" enctype="multipart/form-data">
				<div class="container">
					<div class="main">
						<?php if ( 1 < count( $tabs ) ) : ?>
							<nav class="nav-tab-wrapper">
								<?php
								foreach ( $tabs as $slug => $tab_args ) {
									$tab_title = isset( $tab_args[ 'tab_title' ] ) ? $tab_args[ 'tab_title' ] : $slug;
									$tab_url = add_query_arg( array(
										'page' => $this->page_slug(),
										'tab' => esc_attr( $slug )
										),
										remove_query_arg( 'page', $this->get_page_url() )
									);

									echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab ' .
									( $this->current_tab === $slug ? 'nav-tab-active ' : '' ) . esc_attr( $slug ) . '">' .
									wp_kses_post( apply_filters( $this->page_slug() . '_tab_title', $tab_title, $slug, $this ) ) .
									'</a>';
								}
								?>
							</nav>
						<?php endif; ?>

						<?php
						$tab_args = isset( $tabs[ $this->current_tab ] ) ? $tabs[ $this->current_tab ] : [];

						$sections_title = isset( $tab_args[ 'sections_title' ] ) ? $tab_args[ 'sections_title' ] : null;

						// hack to keep settings_errors() above section titles and section submenu
						printf( '<h1 style=%s>%s</h1>',
							empty( $sections_title ) ? 'display:none;' : '',
							esc_html( $sections_title )
						);

						$nav_key = !empty( $this->current_section ) ? $this->current_section : $this->current_tab;

						do_action( $this->page_slug() . '_after_tab_title', $this );

						$current_tab_sections = $tabs[ $this->current_tab ][ 'sections' ] ?? [];
						?>
						<ul class="subsubsub">

							<?php
							$section_keys = array_keys( $current_tab_sections );

							foreach ( $current_tab_sections as $key => $section ) {
								$section_url = add_query_arg( array(
									'page' => $this->page_slug(),
									'tab' => esc_attr( $this->current_tab ),
									'section' => sanitize_title( $key )
									),
									remove_query_arg( [ 'page', 'tab', 'section' ], $this->get_page_url() )
								);
								$label = apply_filters( $this->page_slug() . '_tab_section_title',
									($section[ 'title' ] ?? '' ),
									$key,
									$this
								);
								echo '<li><a href="' . esc_url( $section_url ) . '" class="' . ( $this->current_section == $key ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $section_keys ) == $key ? '' : '|' ) . ' </li>';
							}
							?>

						</ul><br class="clear" />
						<?php
						settings_errors();
						settings_fields( $this->option_name );

						do_action( $this->page_slug() . '_before_content', $this );

						$section_args = $tabs[ $this->current_tab ][ $this->current_section ] ?? $tabs[ $this->current_tab ] ?? [];

						if ( $this->is_custom_page() ) {
							$this->custom_page_content();
						} elseif ( !empty( $section_args[ 'content' ] ) && is_callable( $section_args[ 'content' ] ) ) {
							call_user_func( $section_args[ 'content' ], $this );
						} elseif ( false !== ($tab_args[ 'show_sections' ] ?? true) ) {
							do_settings_sections( $nav_key );
						}

						do_action( $this->page_slug() . '_after_content', $this );

						if ( !isset( $section_args[ 'submit_button' ] ) || !empty( $section_args[ 'submit_button' ] ) ) {
							submit_button();
						}
						?>
					</div><!--- .main -->
					<?php
					$sidebar = $this->get_sidebar();
					if ( !empty( $sidebar ) ) :
						?>
						<div class="sidebar">
							<?php echo wp_kses_post( $sidebar ); ?>
						</div>
					<?php endif; ?>
				</div><!-- .container --->
			</form>
		</div>
		<?php
	}

	protected function custom_page_content() {

	}

	public function get_sidebar_links() {
		$links = [
			'docs' => $this->plugin_props->docs_url,
			'review' => $this->plugin_props->review_url,
			'support' => $this->plugin_props->support_url,
			'product' => $this->plugin_props->is_pro ? '' : $this->plugin_props->product_url,
		];

		$links_html = '';

		foreach ( $links as $key => $value ) {
			if ( $value ) {
				switch ( $key ) {
					case 'docs':
						$text = $this->plugin_props->is_pro ?
							__( 'Docs', 'nm-favourites-pro' ) :
							__( 'Docs', 'nm-favourites' );
						break;
					case 'review':
						$text = $this->plugin_props->is_pro ?
							__( 'Review', 'nm-favourites-pro' ) :
							__( 'Review', 'nm-favourites' );
						break;
					case 'support':
						$text = $this->plugin_props->is_pro ?
							__( 'Support', 'nm-favourites-pro' ) :
							__( 'Support', 'nm-favourites' );
						break;
					case 'product':
						$text = __( 'Get PRO', 'nm-favourites' );
						break;
				}
				$links_html .= '<li><a href="' . $value . '">' . $text . '</a></li>';
			}
		}

		return !empty( $links_html ) ? "<ul>$links_html</ul>" : '';
	}

	public function get_sidebar() {
		return $this->get_sidebar_links();
	}

	/**
	 * Get the current settings tab being viewed
	 *
	 * @param array $request The associative array used to determine the tab, typically $_GET or HTTP_REFERER
	 * @return string
	 */
	public function get_current_tab( $request = array() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = $request[ 'page' ] ?? sanitize_text_field( wp_unslash( $_GET[ 'page' ] ?? [] ) );
		if ( $page && $this->page_slug() === $page ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = $request[ 'tab' ] ?? sanitize_text_field( $_GET[ 'tab' ] ?? [] );
			if ( !empty( $tab ) ) {
				return sanitize_title( wp_unslash( $tab ) );
			} else {
				return array_key_first( $this->tabs() );
			}
		}
	}

	/**
	 * Get the current settings section being viewed
	 *
	 * @param array $request The associative array used to determine the section, typically
	 * $_GET or HTTP_REFERER
	 * @return string
	 */
	public function get_current_section( $request = array() ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$page = $request[ 'page' ] ?? sanitize_text_field( wp_unslash( $_GET[ 'page' ] ?? [] ) );
		if ( $page && $this->page_slug() === $page ) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$raw_section = $request[ 'section' ] ?? sanitize_text_field( wp_unslash( $_GET[ 'section' ] ?? [] ) );
			$section = $raw_section ? sanitize_title( wp_unslash( $raw_section ) ) : '';
			$tab = $this->get_current_tab( $request );

			if ( $tab && !$section && !empty( $this->tabs()[ $tab ][ 'sections' ] ) ) {
				$section = array_key_first( $this->tabs()[ $tab ][ 'sections' ] );
			}
			return $section;
		}
	}

	public function get_fields() {
		$fields = array();

		foreach ( $this->tabs() as $tab ) {
			if ( !empty( $tab[ 'sections' ] ) ) {
				foreach ( $tab[ 'sections' ] as $section ) {
					if ( !empty( $section[ 'fields' ] ) ) {
						$fields = array_merge( $fields, $section[ 'fields' ] );
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Get the default values for all plugin options
	 *
	 * @return array
	 */
	public function get_default_field_values() {
		return $this->get_default_values_for_fields( $this->get_fields() );
	}

	public function get_default_values_for_fields( $fields ) {
		$fields_vals = array();

		foreach ( $fields as $value ) {
			// Key to use to save the value
			$option_key = $this->get_field_key( $value );
			if ( $option_key ) {
				if ( isset( $value[ 'option_group' ] ) && $value[ 'option_group' ] ) {
					$fields_vals[ $option_key ][] = isset( $value[ 'default' ] ) ? $value[ 'default' ] : '';
				} else {
					$fields_vals[ $option_key ] = isset( $value[ 'default' ] ) ? $value[ 'default' ] : '';
				}

				if ( is_array( $fields_vals[ $option_key ] ) ) {
					$fields_vals[ $option_key ] = array_filter( $fields_vals[ $option_key ] );
				}
			}
		}

		return $fields_vals;
	}

	/**
	 * Save the default values for all plugin options in the database
	 * (This function should typically only be called on plugin installation or activation).
	 */
	public function save_default_values() {
		$defaults = $this->get_default_field_values();
		$existing_settings = $this->get_option();

		if ( $existing_settings ) {
			$defaults = apply_filters(
				$this->option_name . '_save_default_values',
				array_merge( $defaults, $existing_settings ),
				$this
			);

			delete_option( $this->option_name );
		}

		add_option( $this->option_name, $defaults );
	}

	/**
	 * Get all the sections that are in a settings tab
	 *
	 * @param string $tab The tab (Default is current tab)
	 * @return array
	 */
	public function get_tab_sections( $tab = null ) {
		$current_tab = $tab ?? $this->current_tab;
		return $this->tabs()[ $current_tab ][ 'sections' ] ?? [];
	}

	public function register_settings() {
		if ( !$this->option_name ) {
			return;
		}

		register_setting( $this->option_name, $this->option_name );

		$sections = $this->get_tab_sections();
		if ( !$sections ) {
			return;
		}

		foreach ( $sections as $key => $section ) {
			$this->add_settings_section_and_fields( $key, $section );
		}
	}

	public function add_settings_section_and_fields( $section_key, $section_data ) {
		$section_title = $section_data[ 'title' ] ?? '';
		unset( $section_data[ 'title' ] );

		add_settings_section(
			$section_key,
			/**
			 * We are supposed to show the section title here with $section['title']
			 * but we are hiding it by displaying an empty string because both the tab title
			 * and the submenu title in the navigation can help us tell what the section title is.
			 */
			!empty( $section_data[ 'show_title' ] ) ? $section_title : '',
			array( $this, 'settings_section_description' ),
			$section_key,
			$section_data
		);

		if ( !isset( $section_data[ 'fields' ] ) ) {
			return;
		}

		foreach ( $section_data[ 'fields' ] as $key2 => $args2 ) {
			if ( !($args2[ 'show' ] ?? true) ) {
				unset( $section_data[ 'fields' ] [ $key2 ] );
			}
		}

		foreach ( $section_data[ 'fields' ] as $field ) {
			if ( isset( $field[ 'show_in_group' ] ) && $field[ 'show_in_group' ] ) {
				continue;
			}

			$class = in_array( ($field[ 'type' ] ?? '' ), [ 'heading', 'hidden' ] ) ? [ 'hidden' ] : [];

			if ( !empty( $field[ 'class' ] ) ) {
				$class = array_merge( $class, ( array ) $field[ 'class' ] );
			}

			if ( $this->is_pro_field( $field ) ) {
				$class[] = 'is-pro';
			}

			add_settings_field(
				$field[ 'id' ] ?? ($field[ 'name' ] ?? uniqid()),
				$this->get_formatted_settings_field_label( $field ),
				array( $this, 'do_field' ),
				$section_key,
				$section_key,
				array(
					'class' => implode( ' ', $class ),
					'field' => $field,
					'fields' => $section_data[ 'fields' ]
				)
			);
		}
	}

	/**
	 * Echo content at the top of the section, between the heading and field
	 */
	public function settings_section_description( $section ) {
		if ( !empty( $section[ 'description' ] ) ) {
			echo "<div class='section-description'>" . wp_kses_post( $section[ 'description' ] ) . '</div>';
		}
	}

	public function is_pro_field( $field ) {
		return ($field[ 'pro' ] ?? false) && !$this->plugin_props->is_pro;
	}

	public function get_pro_version_text( $with_html = true ) {
		$text = __( 'PRO', 'nm-favourites' );
		return $with_html ? '<span class="nmerimedia-pro-version-text">(' . $text . ')</span>' : $text;
	}

	/**
	 * Format the label of a settings field before display
	 * This function is used to add error notification colors to the field label
	 * in situations where the field involved has an error
	 *
	 * @since 2.0.0
	 * @param type $field
	 */
	public function get_formatted_settings_field_label( $field ) {
		if ( !isset( $field[ 'label' ] ) ) {
			return '';
		}

		$label = $field[ 'label' ];

		if ( $this->is_pro_field( $field ) ) {
			$label = $label . ' ' . $this->get_pro_version_text();
		}

		if ( isset( $field[ 'error_codes' ] ) ) {
			$title = '';
			foreach ( $field[ 'error_codes' ] as $code ) {
				if ( $this->has_settings_error_code( $code ) ) {
					$title .= $this->get_error_message_by_code( $code );
				}
			}

			if ( !empty( $title ) ) {
				$label = '<span class="nmerimedia-settings-error" title="' . $title . '">' . $label . '</span>';
			}
		}

		if ( isset( $field[ 'desc_tip' ] ) ) {
			$label .= ' ' . $this->help_tip( $field[ 'desc_tip' ] );
		}

		return $label;
	}

	protected function help_tip( $title ) {
		return '<span class="nmerimedia-help" title="' . $title . '"> &#9432;</span>';
	}

	/**
	 * Check if particular settings error codes exists if we have errors after saving settings
	 *
	 * @since 2.0.0
	 * @param string|array $code Error code or array of error codes
	 * @return boolean
	 */
	public function has_settings_error_code( $code ) {
		foreach ( get_settings_errors( $this->page_slug() ) as $error ) {
			if ( in_array( $error[ 'code' ], ( array ) $code, true ) ) {
				return true;
			}
		}
		return false;
	}

	public function get_error_message_by_code( $code ) {
		$message = '';
		$codes_to_messages = $this->get_error_codes_to_messages();
		foreach ( ( array ) $code as $c ) {
			$message .= ($codes_to_messages[ $c ] ?? '') . '&#10;';
		}
		return trim( $message );
	}

	public function get_field_key( $field ) {
		return isset( $field[ 'option_group' ] ) ? $field[ 'option_group' ] : (isset( $field[ 'id' ] ) ? $field[ 'id' ] : '');
	}

	/**
	 * Get the name attribute of a form field based on the arguments supplied to the field
	 *
	 * @param array $field Arguments supplied to the field
	 */
	public function get_field_name( $field ) {
		if ( isset( $field[ 'name' ] ) ) {
			$name = $field[ 'name' ];
		} else {
			$key = $this->get_field_key( $field );
			$name = $this->option_name . "[$key]";
		}

		$id = $field[ 'id' ] ?? '';
		$name = !empty( $field[ 'option_group' ] ) ? $name . "[$id]" : $name;
		return $name;
	}

	/**
	 * Get the parts of the name used to save the field in the database
	 * This is used for fields whose values are saved in a multidimensional array
	 * e.g. 'settings[post_type][post]' returns [ 'post_type', 'post']
	 * Here, 'settings' is plugin $option_name and it is removed from the returned array
	 *
	 * @param $field_name string The field name e.g. settings[post_type][post]
	 * @return array
	 */
	public function get_field_name_parts( $field_name ) {
		$parts = preg_split( "/[\[\]]+/", $field_name, -1, PREG_SPLIT_NO_EMPTY );
		unset( $parts[ 0 ] ); // Remove plugin settings option name
		return $parts;
	}

	/**
	 * Get the value saved for a field in the database
	 *
	 * @param array $field Arguments supplied to the field
	 */
	public function get_field_value( $field ) {
		$field_name = $this->get_field_name( $field );
		return $field[ 'value' ] ?? $this->get_field_value_from_name( $field_name, ( $field[ 'default' ] ?? '' ) );
	}

	public function get_field_value_from_name( $field_name, $default_value = null ) {
		$parts = $this->get_field_name_parts( $field_name );
		$values = $this->get_option();

		foreach ( $parts as $index => $k ) {
			if ( is_array( $values ) && array_key_exists( $k, $values ) ) {
				$values = &$values[ $k ];
			} else {
				break;
			}
			$value = array_key_last( $parts ) === $index ? $values : $default_value;
		}

		return $value ?? $default_value;
	}

	/**
	 * Adds html checked attribute to a field if it should be checked
	 * Should be used for checkboxes, returns empty string otherwise.
	 *
	 * @param array $field Arguments supplied to the field
	 */
	public function checked( $field, $echo = false ) {
		$stored_value = $this->get_field_value( $field );
		$input_value = !empty( $field[ 'input_value' ] ) ? $field[ 'input_value' ] : 1;
		$result = $stored_value === $input_value ? " checked='checked'" : '';

		if ( $echo ) {
			echo esc_attr( $result );
		}
		return $result;
	}

	/**
	 * Adds html selected attribute to a select option if it should be selected
	 * Should be used for select option inputs, returns empty string otherwise.
	 *
	 * @param array $option_value The registered value for the option element
	 * @param array $field Arguments supplied to the field
	 */
	public function selected( $option_value, $field, $echo = false ) {
		$stored_value = ( array ) $this->get_field_value( $field );

		if ( in_array( $option_value, $stored_value ) ) {
			$result = " selected='selected'";
		} else {
			$result = '';
		}

		if ( $echo ) {
			echo esc_html( $result );
		}
		return $result;
	}

	public function do_field( $settings ) {
		$this->output_field( $settings[ 'field' ] );
	}

	public function output_field( $field ) {
		// Ensure necessary fields are set
		$field_id = isset( $field[ 'id' ] ) ? esc_attr( $field[ 'id' ] ) : '';
		$field_type = isset( $field[ 'type' ] ) ? esc_attr( $field[ 'type' ] ) : '';
		$field_desc = isset( $field[ 'desc' ] ) ? '<div class="nm-desc">' . $field[ 'desc' ] . '</div>' : '';
		$field_placeholder = isset( $field[ 'placeholder' ] ) ? esc_attr( $field[ 'placeholder' ] ) : '';
		$field_css = isset( $field[ 'css' ] ) ? esc_attr( $field[ 'css' ] ) : '';
		$field_name = esc_attr( $this->get_field_name( $field ) );
		$field_value = $this->get_field_value( $field );
		$inline_class = isset( $field[ 'inline' ] ) && true === $field[ 'inline' ] ? 'nmerimedia-inline' : '';
		$field_options = isset( $field[ 'options' ] ) ? $field[ 'options' ] : array();
		$custom_attributes = array();

		if ( isset( $field[ 'custom_attributes' ] ) && is_array( $field[ 'custom_attributes' ] ) ) {
			foreach ( $field[ 'custom_attributes' ] as $attribute => $attribute_value ) {
				if ( false === $attribute_value ) {
					unset( $field[ 'custom_attributes' ][ $attribute ] );
					break;
				}
				$custom_attributes[] = $attribute . '="' . $attribute_value . '"';
			}
		}
		$field_custom_attributes = implode( ' ', $custom_attributes );

		if ( isset( $field[ 'show_in_group' ] ) && $field[ 'show_in_group' ] ) {
			return;
		}

		switch ( $field_type ) {
			case 'heading':
				echo '</td></tr></tbody></table>';
				echo isset( $field[ 'label' ] ) && !empty( $field[ 'label' ] ) ? "<h2 class='heading'>" .
					esc_html( $field[ 'label' ] ) . '</h2>' : '';
				echo (!empty( $field_desc )) ? wp_kses_post( $field_desc ) : '';
				echo '<table class="form-table" role="presentation"><tbody><tr class="hidden"><th></th><td>';
				break;

			case 'text':
			case 'password':
			case 'number':
			case 'hidden':
				printf( "<input type='%s' id='%s' name='%s' size='40' value='%s' placeholder='%s' %s />",
					esc_attr( $field_type ),
					esc_attr( $field_id ),
					esc_attr( $field_name ),
					esc_attr( $field_value ),
					esc_attr( $field_placeholder ),
					wp_kses( $field_custom_attributes, [] )
				);
				break;

			case 'textarea':
				printf( "<textarea name='%s' cols='45' rows='4' placeholder='%s' %s>%s</textarea>",
					esc_attr( $field_name ),
					esc_attr( $field_placeholder ),
					wp_kses( $field_custom_attributes, [] ),
					wp_kses_post( $field_value )
				);
				break;

			case 'editor':
				wp_editor( $field_value, $field_id, [
					'textarea_rows' => 4,
					'textarea_name' => $field_name,
				] );
				break;

			case 'checkbox':
				$custom_attr = $field[ 'custom_attributes' ] ?? [];
				$input_class = $custom_attr[ 'class' ] ?? [];

				if ( !empty( $input_class ) ) {
					unset( $custom_attr[ 'class' ] );
				}

				$args = [
					'input_name' => esc_attr( $field_name ),
					'input_class' => $input_class,
					'checked' => esc_attr( $this->checked( $field ) ),
					'input_attributes' => $custom_attr,
					'label_text' => !empty( $field_desc ) ? wp_kses_post( $field_desc ) : '',
				];
				$this->checkbox( $args );
				break;

			case 'radio':
				?>
				<div class="nmerimedia-input-group <?php echo esc_attr( $inline_class ); ?>">
					<?php
					foreach ( $field[ 'options' ] as $key => $val ) :
						$checked = checked( $key, $field_value, false );
						?>
						<div><label><input <?php echo esc_attr( $checked ) . ' ' . wp_kses( $field_custom_attributes, [] ); ?>
									value="<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( $field_name ); ?>"
									type="radio"/><?php echo wp_kses_post( $val ); ?></label></div>
						<?php endforeach; ?>
				</div>
				<?php
				break;

			case 'radio_with_image':
				?>
				<div class="nmerimedia-btn-group nmerimedia-input-group <?php echo esc_attr( $inline_class ); ?>">
					<?php
					foreach ( $field[ 'options' ] as $key => $args ) :
						$checked = checked( $key, $field_value, false );
						$option_id = "{$field_id}-{$key}";
						$title = $args[ 'label_title' ] ?? '';
						?>
						<div class="nmerimedia-btn <?php echo $this->is_pro_field( $args ) ? 'is-pro' : ''; ?>">
							<input <?php echo esc_attr( $checked ); ?>
								id="<?php echo esc_attr( $option_id ); ?>"
								type="radio"
								value="<?php echo esc_attr( $key ); ?>"
								name="<?php echo esc_attr( $field_name ); ?>">
								<label for="<?php echo esc_attr( $option_id ); ?>"
											 title="<?php echo esc_attr( $title ); ?>"
											 class="nmerimedia-tip">
												 <?php
												 echo isset( $args[ 'image' ] ) ? wp_kses_post( $args[ 'image' ] ) : '';
												 echo isset( $args[ 'label' ] ) ? wp_kses_post( $args[ 'label' ] ) : '';
												 ?>
								</label>
						</div>
					<?php endforeach; ?>
				</div>
				<?php
				break;

			case 'select':
				if ( !empty( $field[ 'custom_attributes' ][ 'multiple' ] ) ) {
					$field_name = $field_name . '[]';
					echo '<input type="hidden" value="" name="' . esc_attr( $field_name ) . '">';
				}
				printf( "<select name='%s' id='%s' %s>",
					esc_attr( $field_name ),
					esc_attr( $field_id ),
					wp_kses( $field_custom_attributes, [] )
				);
				foreach ( $field_options as $key => $val ) {
					printf( "<option value='%s' %s %s>%s</option>",
						esc_attr( $key ),
						esc_attr( $this->selected( $key, $field ) ),
						in_array( $key, ($field[ 'disabled_options' ] ?? [] ) ) ? 'disabled' : '',
						esc_html( $val )
					);
				}
				echo '</select>';
				break;

			case 'select_page':
				$args = array(
					'name' => esc_attr( $field_name ),
					'id' => esc_attr( $field_id ),
					'show_option_none' => $this->none_text(),
					'class' => '', // @todo Add class here from field custom attributes array if present
					'selected' => absint( $field_value ),
				);

				if ( isset( $field[ 'args' ] ) ) {
					$args = wp_parse_args( $field[ 'args' ], $args );
				}

				add_filter( 'wp_dropdown_pages', function ( $html ) use ( $field_placeholder, $field_css ) {
					return str_replace(
					' id=',
					" data-placeholder='" . esc_attr( $field_placeholder ) . "' style='" . esc_attr( $field_css ) . "' id=",
					$html
					);
				} );

				wp_dropdown_pages( $args );
				break;

			case 'custom':
				if ( !empty( $field[ 'content' ] ) ) {
					if ( is_callable( $field[ 'content' ] ) ) {
						$field[ 'content' ]();
					} else {
						echo wp_kses( $field[ 'content' ], $this->allowed_post_tags() );
					}
				}

				break;
		}

		// These fields should not have description
		$exclude_fields = array( 'checkbox', 'heading' );
		if ( $field_desc && !in_array( $field_type, $exclude_fields ) ) {
			echo wp_kses_post( $field_desc );
		}
	}

	public function sanitize( $posted_data ) {
		$referer = array();
		parse_str( wp_parse_url( wp_get_referer(), PHP_URL_QUERY ), $referer );

		// We're only dealing with fields posted from a particular tab or section
		$fields = $this->get_current_section_fields( $referer );

		foreach ( $fields as $field ) {
			if ( !array_key_exists( 'type', $field ) ) {
				continue;
			}

			$data = $this->get_field_name_parts( $this->get_field_name( $field ) );
			$key = &$posted_data;

			foreach ( $data as $index => $k ) {
				if ( array_key_exists( $k, $key ) ) {
					$key = &$key[ $k ];
				} else {
					break;
				}

				if ( array_key_last( $data ) === $index ) {
					switch ( $field[ 'type' ] ) {
						case 'text':
							$key = sanitize_text_field( $key );
							break;
						case 'textarea':
							$key = sanitize_textarea_field( $key );
							break;
						case 'editor':
							$key = wp_kses( $key, $this->allowed_post_tags() );
							break;
						case 'select':
							if ( !empty( $field[ 'custom_attributes' ][ 'multiple' ] ) ) {
								/**
								 * Always cast value of multiple select as array so that even when no value is
								 * submitted as in the case with hidden input for the select element,
								 * we can have an empty array.
								 */
								$cast_to_array = array_filter( ( array ) $key );
								$key = array_map( 'sanitize_text_field', $cast_to_array );
							} else {
								$key = sanitize_text_field( $key );
							}
							break;
						case 'checkbox':
							$key = ( int ) $key;
							break;
					}
				}
			}
		}

		if ( get_settings_errors( $this->page_slug() ) ) {
			add_settings_error( $this->page_slug(), 'settings-saved', $this->changes_saved_text(), 'success' );
		}

		$current_section_error_codes = $this->get_current_section_error_codes( $referer );

		foreach ( $current_section_error_codes as $k => $code ) {
			if ( $this->has_settings_error_code( $code ) ) {
				unset( $current_section_error_codes[ $k ] );
			}
		}

		$this->delete_settings_errors( $current_section_error_codes );

		$options = array_merge( $this->get_option(), $posted_data );
		return $options;
	}

	/**
	 * Get the fields for the current settings section being viewed
	 *
	 * @param array $request The associative array used to determine the tab, typically $_GET or HTTP_REFERER
	 * @return array
	 */
	public function get_current_section_fields( $request = array() ) {
		$tab = $this->get_current_tab( $request );
		$section = $this->get_current_section( $request );
		$tab_sections = $this->get_tab_sections( $tab );
		return $tab_sections[ $section ][ 'fields' ] ?? [];
	}

	public function changes_saved_text() {
		return $this->plugin_props->is_pro ?
			__( 'Updated', 'nm-favourites-pro' ) :
			__( 'Updated', 'nm-favourites' );
	}

	/**
	 * Get the error codes available for all the fields in a settings section
	 * @param array $request The associative array used to determine the tab, typically $_GET or HTTP_REFERER
	 */
	public function get_current_section_error_codes( $request = array() ) {
		$error_codes = array();

		foreach ( $this->get_current_section_fields( $request ) as $field ) {
			if ( isset( $field[ 'error_codes' ] ) ) {
				$error_codes = array_merge( $error_codes, $field[ 'error_codes' ] );
			}
		}
		return array_unique( $error_codes );
	}

	/**
	 * Save a settings error to the database and also register it to be
	 * shown to the user using 'add_settings_error()'.
	 *
	 * Typically used when sanitizing the option before save.
	 *
	 * @param string $code Settings error code
	 * @param string $message Settings error message to display to user
	 * @param string $type Type of error. Default warning.
	 */
	public function save_settings_error( $code, $message, $type = 'warning' ) {
		add_settings_error( $this->page_slug(), $code, $message, $type );
		$saved = get_option( $this->get_settings_errors_key(), array() );
		$saved[ $code ] = array( 'type' => $type );
		update_option( $this->get_settings_errors_key(), $saved );
	}

	/**
	 * Delete settings errors saved to the database
	 *
	 * @param array $codes Codes for settings errors to delete
	 */
	public function delete_settings_errors( $codes ) {
		if ( !empty( $codes ) ) {
			$saved = get_option( $this->get_settings_errors_key(), array() );
			foreach ( $codes as $code ) {
				if ( isset( $saved[ $code ] ) ) {
					unset( $saved[ $code ] );
				}
			}
			update_option( $this->get_settings_errors_key(), $saved );
		}
	}

	/**
	 * Get the settings errors that have been saved to the database
	 * @return array
	 */
	public function get_saved_settings_errors() {
		return get_option( $this->get_settings_errors_key(), array() );
	}

	public function show_saved_settings_errors() {
		if ( !$this->is_current_page() ) {
			return;
		}

		$error_codes = $this->get_current_section_error_codes();
		if ( !empty( $error_codes ) ) {
			$saved_settings_errors = $this->get_saved_settings_errors();

			foreach ( $error_codes as $code ) {
				if ( !$this->has_settings_error_code( $code ) && array_key_exists( $code, $saved_settings_errors ) ) {
					$error = $saved_settings_errors[ $code ];
					$error_msg = $this->get_error_message_by_code( $code );
					add_settings_error( $this->page_slug(), $code, $error_msg, $error[ 'type' ] );
				}
			}
		}
	}

	public function get_error_codes_to_messages() {
		return [];
	}

	/**
	 * Get the call to action for buying the pro version of the plugin
	 *
	 * Displays the first five pro features of the plugin from the readme.txt file
	 * with a buy link
	 * @return string
	 */
	public function get_buy_pro_notice() {
		$readme = new ReadmeParser( $this->plugin_props->path . 'readme.txt' );
		$features = $readme ? $readme->get_pro_version_features( true ) : '';
		if ( empty( $features ) ) {
			return;
		}

		$img_path = $this->plugin_props->path . '/assets/img/logo.png';
		$img_url = $this->plugin_props->url . '/assets/img/logo.png';
		$image = file_exists( $img_path ) ? "<img style='width:32px;height:auto;' src='$img_url'>" : '';

		$message = '<table><tbody><tr><td>' . $image . '</td><td><strong>' . $this->plugin_props->name . '</strong></td></tr></tbody></table><br>';
		$message .= __( 'You are using the free version of the plugin. Get the pro version to enable these features and more:', 'nm-favourites' );
		$message .= '<ol>';

		// Get first five features
		foreach ( array_slice( $features, 0, 5 ) as $feature ) {
			$message .= '<li>' . $feature . '</li>';
		}

		$message .= '</ol>';

		if ( $this->plugin_props->product_url ) {
			$message .= '<a class="button button-primary" target="_blank" href="' . $this->plugin_props->product_url . '">' . __( 'Get PRO', 'nm-favourites' ) . '</a><br><br>';
		}

		return $message;
	}

	public function checkbox_styles( $sel = '' ) {
		?>
		<style>
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox {
				/** bar */
				--nmerimedia_cb-bar-height: 20px;
				--nmerimedia_cb-bar-width: 44px;
				--nmerimedia_cb-bar-color: #ddd;

				/** knob */
				--nmerimedia_cb-knob-size: 22px;
				--nmerimedia_cb-knob-color: #fff;

				/** switch */
				--nmerimedia_cb-switch-offset: calc(var(--nmerimedia_cb-knob-size) - var(--nmerimedia_cb-bar-height));
				--nmerimedia_cb-switch-width: calc(var(--nmerimedia_cb-bar-width) + var(--nmerimedia_cb-switch-offset));
				--nmerimedia_cb-transition-duration: 200ms;
				--nmerimedia_cb-switch-transition: all var(--nmerimedia_cb-transition-duration) ease-in-out;
				--nmerimedia_cb-switch-theme-rgb: 34, 113, 177;
				--nmerimedia_cb-switch-theme-color: rgb(var(--nmerimedia_cb-switch-theme-rgb));
				--nmerimedia_cb-switch-box-shadow: 0 0 var(--nmerimedia_cb-switch-offset) rgba(var(--nmerimedia_cb-switch-border-rgb), .5);
				--nmerimedia_cb-switch-margin: 8px;
				--nmerimedia_cb-switch-margin-top: 10px;
				--nmerimedia_cb-switch-margin-right: 13px;
				--nmerimedia_cb-switch-border-rgb: 17, 17, 17;

				position: relative;
				display: inline-flex !important;
				align-items: center;
				box-sizing: border-box;
				min-width: var(--nmerimedia_cb-bar-width);
				min-height: var(--nmerimedia_cb-bar-height);
				user-select: none;
			}

			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox.disabled,
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox.readonly {
				opacity: .4;
			}

			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > input {
				position: absolute;
				width: 0 !important;
				height: 0 !important;
				opacity: 0 !important;
			}

			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > label {
				--nmerimedia_cb-knob-x: calc((var(--nmerimedia_cb-bar-height) - var(--nmerimedia_cb-bar-width)) / 2);

				position: relative;
				display: inline-flex !important;
				align-items: center;
				justify-content: center;
				box-sizing: border-box;
				width: var(--nmerimedia_cb-bar-width);
				height: var(--nmerimedia_cb-bar-height);
				margin-top: var(--nmerimedia_cb-switch-margin-top) !important;
				margin-bottom: var(--nmerimedia_cb-switch-margin-top) !important;
				margin-right: var(--nmerimedia_cb-switch-margin-right) !important;
				margin-left: 0 !important;
				user-select: none;
			}

			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox.label-before > label {
				margin-left: var(--nmerimedia_cb-switch-margin-right) !important;
			}

			/* checked */
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > :checked + label {
				--nmerimedia_cb-knob-x: calc((var(--nmerimedia_cb-bar-width) - var(--nmerimedia_cb-bar-height)) / 2);
			}

			/* bar */
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > label::before {
				position: absolute;
				top: 0;
				left: 0;
				box-sizing: border-box;
				width: var(--nmerimedia_cb-bar-width);
				height: var(--nmerimedia_cb-bar-height);
				background: var(--nmerimedia_cb-bar-color);
				border: 1px solid rgba(var(--nmerimedia_cb-switch-border-rgb), .2);
				border-radius: var(--nmerimedia_cb-bar-height);
				opacity: 0.5;
				transition: var(--nmerimedia_cb-switch-transition);
				content: "";
			}

			/* checked bar */
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > :checked + label::before {
				background: var(--nmerimedia_cb-switch-theme-color);
				border-color: var(--nmerimedia_cb-switch-theme-color);
			}

			/* knob */
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > label::after {
				box-sizing: border-box;
				width: var(--nmerimedia_cb-knob-size);
				height: var(--nmerimedia_cb-knob-size);
				background: var(--nmerimedia_cb-knob-color);
				border-radius: 50%;
				box-shadow: var(--nmerimedia_cb-switch-box-shadow);
				transform: translateX(var(--nmerimedia_cb-knob-x));
				transition: var(--nmerimedia_cb-switch-transition);
				content: "";
			}

			/* checked knob */
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox > :checked + label::after {
				background: var(--nmerimedia_cb-switch-theme-color);
			}

			/* hover & focus knob */
			<?php echo esc_attr( $sel ); ?> .nmerimedia-checkbox:not(.disabled):not(.readonly):hover > label::after,
			<?php echo esc_attr( $sel ); ?>  .nmerimedia-checkbox > :checked + label::after {
				box-shadow: var(--nmerimedia_cb-switch-box-shadow), 0 0 0 calc(var(--nmerimedia_cb-switch-margin-top) - 2px) rgba(var(--nmerimedia_cb-switch-theme-rgb), 0.2);
			}
		</style>
		<?php
	}

	/**
	 * Get the checkbox switch used by the plugin
	 * @param array $args Arguments supplied to create the checkbox switch:
	 * - input_type = {string} The input type, 'checkbox' or 'radio'. Default 'checkbox'
	 * - input_id - {string, required} The input id
	 * - input_name - {string} The input name
	 * - input_value - {mixed} The input value
	 * - input_class - {array} The input class
	 * - input_attributes = {array} Attributes to go into the input element
	 * - label_class - {array} The label class
	 * - label_attributes - {array} Attributes to go into the label element
	 * - label_text - {string} The label text
	 * - label_before - {boolean} Whether the label text should be before the switch. Default false.
	 * - checked - {boolean} Whether the checkbox should be checked or not.
	 * - show_hidden_input - {mixed} Whether to show a hidden input for the checkbox. Default false.
	 * 												If true, show hidden input value is 0 else, value of 'show_hidden_input' is used.
	 * @return string
	 */
	public function checkbox( $args ) {
		$defaults = array(
			'input_type' => 'checkbox',
			'input_id' => '',
			'input_name' => '',
			'input_value' => 1,
			'input_class' => array(),
			'input_attributes' => array(),
			'label_class' => array(),
			'label_attributes' => array(),
			'label_text' => '',
			'label_before' => false,
			'checked' => '',
			'show_hidden_input' => true,
			'return' => false,
		);

		$params = wp_parse_args( $args, $defaults );

		if ( !empty( $params[ 'input_attributes' ][ 'disabled' ] ) ) {
			$params[ 'label_class' ][] = 'disabled';
		}

		if ( $params[ 'label_before' ] ) {
			$params[ 'label_class' ][] = 'label-before';
		}

		if ( !empty( $params[ 'input_attributes' ][ 'readonly' ] ) ) {
			$params[ 'label_class' ][] = 'readonly';
		}

		$params[ 'input_id' ] = empty( $params[ 'input_id' ] ) ? $params[ 'input_name' ] : $params[ 'input_id' ];

		$label_class = implode( ' ', ( array ) $params[ 'label_class' ] );
		$input_class = implode( ' ', ( array ) $params[ 'input_class' ] );
		$input_attributes = nm_favourites_format_attributes( $params[ 'input_attributes' ] );
		$label_attributes = nm_favourites_format_attributes( $params[ 'label_attributes' ] );
		$checked = $params[ 'checked' ] ? ' checked ' : '';
		$label = '<span>' . $params[ 'label_text' ] . '</span>';

		$params[ 'return' ] ? ob_start() : '';

		echo '<label class="nmerimedia-checkbox ' . esc_attr( $label_class ) . '"' . esc_attr( $label_attributes ) . '>';

		if ( $params[ 'show_hidden_input' ] ) {
			$hidden_input_val = (true === $params[ 'show_hidden_input' ]) ? 0 : $params[ 'show_hidden_input' ];
			echo '<input type="hidden" value="' . esc_attr( $hidden_input_val ) . '" name="' .
			esc_attr( $params[ 'input_name' ] ) . '">';
		}

		echo ($params[ 'label_before' ] ? wp_kses_post( $label ) : '') .
		'<input type="' . esc_attr( $params[ 'input_type' ] ) . '" id="' . esc_attr( $params[ 'input_id' ] ) . '"
                value="' . esc_attr( $params[ 'input_value' ] ) . '"
								class="' . esc_attr( $input_class ) . '"
                name="' . esc_attr( $params[ 'input_name' ] ) . '"' .
		esc_attr( $checked ) .
		wp_kses_post( $input_attributes ) . '/>' .
		'<label for="' . esc_attr( $params[ 'input_id' ] ) . '"></label>' .
		(!$params[ 'label_before' ] ? wp_kses_post( $label ) : '') .
		'</label>';

		return $params[ 'return' ] ? ob_get_clean() : '';
	}

	public function allowed_svg_tags() {
		return array(
			'svg' => array(
				'id' => true,
				'role' => true,
				'width' => true,
				'height' => true,
				'class' => true,
				'style' => true,
				'fill' => true,
				'xmlns' => true,
				'viewbox' => true,
				'aria-hidden' => true,
				'focusable' => true,
				'data-notice' => true, // may be deprecated soon. Used temporarily.
			),
			'use' => array(
				'xlink:href' => true
			),
			'title' => array(
				'data-title' => true
			),
			'path' => array(
				'fill' => true,
				'fill-rule' => true,
				'd' => true,
				'transform' => true,
			),
			'polygon' => array(
				'fill' => true,
				'fill-rule' => true,
				'points' => true,
				'transform' => true,
				'focusable' => true,
			),
		);
	}

	public function allowed_post_tags() {
		$post = wp_kses_allowed_html( 'post' );
		$post[ 'a' ][ 'onclick' ] = true;
		$post[ 'span' ][ 'onclick' ] = true;
		$post[ 'button' ][ 'onclick' ] = true;
		return array_merge( $post, $this->allowed_svg_tags() );
	}

	public function none_text() {
		return nm_favourites()->is_pro ? __( 'None', 'nm-favourites-pro' ) : __( 'None', 'nm-favourites' );
	}

	public function nmerimedia_svg() {
		ob_start();
		?>
		<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 22 22" style="enable-background:new 0 0 22 22;" xml:space="preserve">
			<g>
				<path d="M2.7,9.96v4.86c0,0.12,0.03,0.21,0.08,0.28s0.15,0.11,0.29,0.15v0.19H0.72v-0.19c0.14-0.04,0.24-0.09,0.29-0.15
							s0.08-0.15,0.08-0.28V8.04c0-0.12-0.03-0.21-0.08-0.27S0.86,7.66,0.72,7.62V7.44h2.72v0.19C3.27,7.67,3.19,7.76,3.19,7.89
							c0,0.09,0.05,0.2,0.16,0.31l4.41,4.59V8.04c0-0.12-0.03-0.21-0.08-0.27S7.53,7.66,7.39,7.62V7.44h2.35v0.19
							C9.6,7.66,9.5,7.71,9.45,7.78S9.36,7.93,9.36,8.04v6.77c0,0.12,0.03,0.21,0.08,0.28s0.15,0.11,0.29,0.15v0.19H7.22v-0.19
							c0.16-0.04,0.25-0.12,0.25-0.25c0-0.08-0.1-0.21-0.29-0.41L2.7,9.96z"/>
				<path d="M16.11,15.43l-2.95-5.53v4.91c0,0.12,0.03,0.21,0.08,0.28s0.15,0.11,0.29,0.15v0.19h-2.35v-0.19
							c0.14-0.04,0.24-0.09,0.29-0.15s0.08-0.15,0.08-0.28V8.04c0-0.12-0.03-0.21-0.08-0.27s-0.15-0.11-0.29-0.15V7.44h3.08v0.19
							c-0.18,0.05-0.28,0.15-0.28,0.32c0,0.08,0.03,0.17,0.08,0.27l2.18,4.13l2.21-4.06c0.1-0.18,0.15-0.31,0.15-0.39
							c0-0.16-0.1-0.25-0.29-0.27V7.44h3.02v0.19c-0.14,0.04-0.24,0.09-0.29,0.15s-0.08,0.15-0.08,0.27v6.77c0,0.12,0.03,0.21,0.08,0.28
							s0.15,0.11,0.29,0.15v0.19h-2.5v-0.19c0.14-0.04,0.23-0.08,0.29-0.15s0.08-0.16,0.08-0.28V9.91L16.11,15.43z"/>
			</g>
		</svg>
		<?php
		return ob_get_clean();
	}

}
