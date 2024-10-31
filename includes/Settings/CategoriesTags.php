<?php

namespace NM\Favourites\Settings;

use NM\Favourites\Settings\CategoriesListTable;
use NM\Favourites\Settings\TagsListTable;
use NM\Favourites\Lib\Scripts;

defined( 'ABSPATH' ) || exit;

class CategoriesTags {

	private $categories_table;
	private $tags_table;
	private $category;

	public function run() {
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'load-nm-favourites_page_nm-favourites-categories', array( $this, 'load_page' ) );
	}

	public function heading_text() {
		return nm_favourites()->is_pro ? __( 'Categories', 'nm-favourites-pro' ) : __( 'Categories', 'nm-favourites' );
	}

	public function add_menu_page() {
		$this->category = $this->current_category_id() ? nm_favourites()->category( $this->current_category_id() ) : null;
		$category_name = $this->category ? $this->category->get_name() : '';
		$page_title = ($category_name ? $category_name . ' &ndash; ' : '') . $this->heading_text() . ' &ndash; ' .
			nm_favourites()->settings()->page_name();

		add_submenu_page(
			nm_favourites()->settings()->page_slug(),
			$page_title,
			$this->heading_text(),
			'manage_options',
			'nm-favourites-categories',
			[ $this, 'page_content' ],
		);
	}

	public function load_page() {
		if ( 'view_tags' === $this->current_action() ) {
			$this->tags_table = new TagsListTable();
		} elseif ( 'create_edit' !== $this->current_action() ) {
			$this->categories_table = new CategoriesListTable();
		}

		add_action( 'admin_head', function () {
			$this->styles();
		} );

		Scripts::admin_scripts();
		$this->add_screen_option();
		$this->create_edit_category();
		$this->delete_category();
		$this->delete_tag();
	}

	public function set_screen_option( $status, $option, $value ) {
		if ( in_array( $option, [ 'nm_favourites_categories_per_page', 'nm_favourites_tags_per_page' ], true ) ) {
			return $value;
		}
		return $status;
	}

	private function add_screen_option() {
		if ( 'create_edit' !== $this->current_action() ) {
			add_screen_option(
				'per_page',
				array(
					'default' => 10,
					'option' => 'view_tags' === $this->current_action() ?
						'nm_favourites_tags_per_page' :
						'nm_favourites_categories_per_page',
				)
			);
		}
	}

	public static function current_action() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return sanitize_text_field( wp_unslash( $_GET[ 'act' ] ?? [] ) );
	}

	public static function current_category_id() {
		// phpcs:ignore WordPress.Security.NonceVerification
		return ( int ) wp_unslash( $_GET[ 'id' ] ?? 0 );
	}

	private function show_updated_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( !empty( ( int ) ( $_GET[ 'updated' ] ?? 0 ) ) ) {
			echo '<div id="message" class="updated"><p>' .
			wp_kses_post( nm_favourites()->settings()->changes_saved_text() ) . '</p></div>';
		}
	}

	public function page_content() {
		$this->show_updated_notice();
		switch ( $this->current_action() ) {
			case 'create_edit':
				$this->create_edit_page();
				break;
			default:
				$this->list_table();
				break;
		}
	}

	private function create_edit_page() {
		$id = $this->current_category_id();
		$category = nm_favourites()->category( $id );
		?>

		<div class="wrap create_edit_page">
			<?php
			$this->header();
			$this->header_menu();
			?>

			<h2 style="margin-bottom:0;font-weight:500;">
				<?php echo esc_html( !$id ? nm_favourites_add_new_text() : nm_favourites_edit_text() ); ?>
			</h2>

			<form id="form" method="POST">
				<div class="metabox-holder" id="poststuff">
					<div id="post-body">
						<div id="post-body-content">
							<div class="postbox">
								<div class="inside">
									<?php
									$create_edit_fields = nm_favourites()->create_edit_category_fields( $category );
									$section1 = [ 'fields' => $create_edit_fields->get_data() ];
									nm_favourites()->settings()->add_settings_section_and_fields( $create_edit_fields->get_id(), $section1 );
									do_settings_sections( $create_edit_fields->get_id() );
									?>
								</div>
							</div>
							<?php
							if ( $category->is_default_parent() ) :
								$category_button_fields = nm_favourites()->category_button_fields( $category );
								?>
								<div class="postbox category_button_fields">
									<div class="inside">
										<fieldset>
											<?php
											$section2 = [ 'fields' => $category_button_fields->get_core_data() ];
											nm_favourites()->settings()->add_settings_section_and_fields( 'button_core_data', $section2 );
											do_settings_sections( 'button_core_data' );
											?>
										</fieldset>
									</div>
								</div>
								<div class="postbox category_button_fields">
									<div class="inside">
										<fieldset>
											<section id="display_1">
												<?php
												$appearance_data = $category_button_fields->get_appearance_data();
												$section3 = [ 'fields' => $appearance_data ];
												nm_favourites()->settings()->add_settings_section_and_fields( 'button_appearance_data', $section3 );
												do_settings_sections( 'button_appearance_data' );
												?>
											</section>
										</fieldset>
									</div>
								</div>
								<?php
								if ( method_exists( $category_button_fields, 'get_include_exclude_data' ) ) :
									?>
									<div class="postbox category_button_fields">
										<div class="inside">
											<fieldset id="include_exclude">
												<?php
												$section4 = [ 'fields' => $category_button_fields->get_include_exclude_data() ];
												nm_favourites()->settings()->add_settings_section_and_fields( 'get_include_exclude_data', $section4 );
												do_settings_sections( 'get_include_exclude_data' );
												?>
											</fieldset>
										</div>
									</div>

									<?php
								endif;
							endif;
							wp_nonce_field( "create_edit_category_$id" );
							submit_button();
							?>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	private function list_table() {
		$current_action = $this->current_action();
		$is_categories = 'view_tags' !== $current_action;
		$is_categories ? $this->categories_table->prepare_items() : $this->tags_table->prepare_items();
		?>

		<div id="nm-favourites-categories-page" class="wrap">
			<?php $this->header(); ?>

			<form method="GET">
				<?php
				$this->header_menu();

				if ( 'view_tags' === $current_action ) {
					?>
					<h2>
						<?php
						nm_favourites()->is_pro ? esc_html_e( 'Tags', 'nm-favourites-pro' ) : esc_html_e( 'Tags', 'nm-favourites' );
						?>
					</h2>
					<?php
				}

				if ( 'view_subcategories' === $current_action ) {
					?>
					<h2>
						<?php
						echo esc_html( __( 'Subcategories', 'nm-favourites-pro' ) );
						?>
					</h2>
					<?php
				}

				// phpcs:ignore WordPress.Security.NonceVerification
				if ( !empty( ($_GET[ 'user_id' ] ) ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification
					$user_id = sanitize_text_field( wp_unslash( $_GET[ 'user_id' ] ) );
					$user = get_user_by( 'id', $user_id );
					if ( !empty( $user ) ) {
						$user_title = $user->user_nicename;
					} else {
						$user_title = (nm_favourites()->is_pro ? __( 'Guest', 'nm-favourites-pro' ) : __( 'Guest', 'nm-favourites' )) .
							' (' . $user_id . ')';
					}
					?>
					<h2>
						<?php
						nm_favourites()->is_pro ? esc_html_e( 'User', 'nm-favourites-pro' ) : esc_html_e( 'User', 'nm-favourites' );
						echo ' &ndash; ' . esc_html( $user_title );
						?>
					</h2>
					<?php
				}

				// phpcs:ignore WordPress.Security.NonceVerification
				if ( !empty( $_GET[ 'object_id' ] ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification
					$object_id = sanitize_text_field( wp_unslash( $_GET[ 'object_id' ] ) );
					?>
					<h2>
						<?php
						echo (nm_favourites()->is_pro ?
							esc_html__( 'Object id', 'nm-favourites-pro' ) :
							esc_html__( 'Object id', 'nm-favourites' )) .
						' &ndash; ' . esc_html( $object_id );
						?>
					</h2>
					<?php
				}
				?>
				<input type="hidden" name="page"
							 value="<?php
							 // phpcs:ignore WordPress.Security.NonceVerification
							 echo esc_attr( sanitize_text_field( $_GET[ 'page' ] ?? null ) );
							 ?>"/>
				<input type="hidden" name="act" value="<?php echo esc_attr( $this->current_action() ); ?>"/>
				<?php $is_categories ? $this->categories_table->display() : $this->tags_table->display(); ?>
			</form>

		</div>
		<?php
	}

	private function styles() {
		nm_favourites()->settings()->checkbox_styles();
		?>
		<style>
			.header-actions {
				margin-top: 1em;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.create_edit_page select,
			.create_edit_page input[type=text],
			.create_edit_page input[type=number],
			.create_edit_page textarea {
				min-width: 409px;
			}
		</style>
		<?php
	}

	public static function url() {
		return get_admin_url( get_current_blog_id(), 'admin.php?page=nm-favourites-categories' );
	}

	public static function view_tags_url( $id ) {
		return add_query_arg( [
			'act' => 'view_tags',
			'id' => $id,
			],
			self::url()
		);
	}

	public static function view_tags_for_object_url( $object_id, $category_id ) {
		return add_query_arg( 'object_id', $object_id, self::view_tags_url( $category_id ) );
	}

	public static function view_subcategories_url( $id ) {
		return add_query_arg( [
			'id' => $id,
			'act' => 'view_subcategories',
			], self::url()
		);
	}

	public static function create_edit_category_url( $id = null ) {
		$args = [ 'act' => 'create_edit' ];
		if ( $id ) {
			$args[ 'id' ] = $id;
		}
		return add_query_arg( $args, self::url() );
	}

	public static function delete_category_url( $id ) {
		return wp_nonce_url( add_query_arg( [
			'act' => 'delete',
			'id' => $id,
			] ),
			"delete_cat_$id"
		);
	}

	public function save_category( $posted_data = [] ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$formdata = $posted_data ? $posted_data : $_POST;
		$id = ( int ) wp_unslash( $formdata[ 'id' ] ?? '' );
		$parent = ( int ) wp_unslash( $formdata[ 'parent' ] ?? 0 );
		$name = sanitize_text_field( wp_unslash( $formdata[ 'name' ] ?? [] ) );
		$desc = sanitize_textarea_field( wp_unslash( $formdata[ 'description' ] ?? [] ) );
		$visibility = sanitize_text_field( wp_unslash( $formdata[ 'visibility' ] ?? [] ) );

		if ( $name ) {
			$category = nm_favourites()->category( $id );
			$category->set_name( $name );
			$category->set_description( $desc );

			if ( is_callable( [ $category, 'set_parent' ] ) ) {
				$category->set_parent( $parent );
			}

			if ( $visibility && is_callable( [ $category, 'set_visibility' ] ) ) {
				$category->set_visibility( $visibility );
			}

			$button_fields = nm_favourites()->category_button_fields( '', 'post', null );
			$standard_fields = $button_fields->get_data();
			$meta = $this->get_sanitized_posted_data( $formdata, $standard_fields );

			// Sanitize display fields separately.
			if ( !empty( $formdata[ 'display' ] ) ) {
				foreach ( $formdata[ 'display' ] as $display_key => $display_args ) {
					$display_fields = $button_fields->get_appearance_data();
					$sanitized = $this->get_sanitized_posted_data( $display_args, $display_fields );
					if ( !empty( $sanitized ) ) {
						$meta[ 'display' ][ $display_key ] = $sanitized;
					}
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( !empty( $meta ) ) {
				$category->set_meta( $meta );
			}

			$saved = $category->save();

			return (!$id && !$saved ) ? false : $category->get_id();
		}
	}

	private function create_edit_category() {
		$id = ( int ) wp_unslash( $_POST[ 'id' ] ?? '' );

		if ( 'create_edit' === $this->current_action() &&
			isset( $_POST[ 'id' ] ) &&
			check_admin_referer( "create_edit_category_$id" ) ) {
			$category_id = $this->save_category();

			if ( $category_id ) {
				if ( $id ) {
					add_settings_error( 'create_edit_category', 'settings-saved',
						nm_favourites()->settings()->changes_saved_text(), 'success' );
				} else {
					wp_redirect( add_query_arg(
							[
								'id' => $category_id,
								'updated' => $category_id,
							],
							wp_get_referer()
						) );
					exit;
				}
			}
		}
	}

	private function delete_category() {
		if ( 'delete' === $this->current_action() && !empty( $_GET[ 'id' ] ) ) {
			$cat_id = ( int ) wp_unslash( $_GET[ 'id' ] );
			if ( check_admin_referer( "delete_cat_$cat_id" ) ) {
				$category = nm_favourites()->category( $cat_id );
				if ( $category->delete_with_tags() ) {
					wp_safe_redirect( add_query_arg( 'updated', 1, remove_query_arg( [ 'act', 'id', '_wpnonce' ] ) ) );
					exit;
				}
			}
		}
	}

	private function delete_tag() {
		if ( 'delete_tag' === $this->current_action() && !empty( $_GET[ 'tag_id' ] ) ) {
			$tag_id = ( int ) wp_unslash( $_GET[ 'tag_id' ] );
			if ( check_admin_referer( "delete_tag_$tag_id" ) ) {
				$tag = nm_favourites()->tag( $this->current_category_id() );
				$tag->set_id( $tag_id );
				$tag->get_by_id();
				if ( $tag->delete() ) {
					wp_safe_redirect( add_query_arg( 'updated', 1, wp_get_referer() ) );
					exit;
				}
			}
		}
	}

	private function get_sanitized_posted_data( $posted_data, $fields ) {
		$data = [];
		foreach ( $fields as $field ) {
			$field_name = $field[ 'name' ] ?? null;
			if ( $field_name && isset( $posted_data[ $field_name ] ) && array_key_exists( 'type', $field ) ) {
				$posted_value = wp_unslash( $posted_data[ $field_name ] );
				switch ( $field[ 'type' ] ) {
					case 'text':
						$value = sanitize_text_field( $posted_value );
						break;
					case 'select':
						if ( !empty( $field[ 'custom_attributes' ][ 'multiple' ] ) ) {
							/**
							 * Always cast value of multiple select as array so that even when no value is
							 * submitted as in the case with hidden input for the select element,
							 * we can have an empty array.
							 */
							$cast_to_array = array_filter( ( array ) $posted_value );
							$value = array_map( 'sanitize_text_field', $cast_to_array );
						} else {
							$value = sanitize_text_field( $posted_value );
						}
						break;
					case 'checkbox':
						$value = ( int ) $posted_value;
						break;

					default:
						$value = wp_kses( $posted_value, nm_favourites()->settings()->allowed_post_tags() );
						break;
				}
				$data[ $field_name ] = $value;
			}
		}
		return $data;
	}

	private function header() {
		?>
		<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
		<h1 style="display:flex;">
			<?php
			$category_name = $this->category ? $this->category->get_name() : '';
			echo esc_html( $this->heading_text() . ($category_name ? ' &ndash; ' . $category_name : '') );

			if ( $this->category && $this->category->is_default_parent() ) {
				$button = nm_favourites()->button( $this->category->get_id() );
				$button->set_preview( true );
				echo '<span style="margin-left:20px;">' .
				wp_kses( $button->get(), nm_favourites()->settings()->allowed_post_tags() ) .
				'</span>';
			}
			?>
		</h1>
		<div>
			<?php
			if ( $this->category && $this->category->get_slug() ) {
				echo '<code>[nm_favourites id=' . esc_html( $this->category->get_slug() ) . ']</code>';
			}
			?>
		</div>
		<?php
		settings_errors();
	}

	private function header_menu() {
		$action = $this->current_action();
		$category_id = $this->current_category_id();
		?>
		<div class="header-actions">
			<div class="_menu">
				<a class="page-title-action" href="<?php echo esc_url( $this->create_edit_category_url() ); ?>">
					<?php echo esc_html( nm_favourites_add_new_text() ); ?>
				</a>

				<a class="page-title-action" href="<?php echo esc_url( $this->url() ); ?>">
					<?php
					nm_favourites()->is_pro ? esc_html_e( 'View all', 'nm-favourites-pro' ) : esc_html_e( 'View all', 'nm-favourites' );
					?>
				</a>

				<?php
				if ( $category_id && 'create_edit' !== $action ) {
					?>
					<a class="page-title-action" href="<?php echo esc_url( $this->create_edit_category_url( $category_id ) ); ?>">
						<?php echo esc_html( nm_favourites_edit_text() ); ?>
					</a>
					<?php
				}

				if ( in_array( $action, [ 'create_edit' ], true ) && $category_id ) {
					?>
					<a class="page-title-action" style="color:#cc1818;border-color:#cc1818;"
						 onclick='return showNotice.warn()'
						 href="<?php echo esc_url( $this->delete_category_url( $category_id ) ); ?>">
							 <?php echo esc_html( nm_favourites_delete_text() ); ?>
					</a>
					<?php
				}

				if ( $category_id ) {
					?>
					<a class="page-title-action" href="<?php echo esc_url( $this->view_tags_url( $category_id ) ); ?>">
						<?php
						nm_favourites()->is_pro ? esc_html_e( 'Tags', 'nm-favourites-pro' ) : esc_html_e( 'Tags', 'nm-favourites' );
						?>
					</a>
					<?php
				}

				if ( nm_favourites()->is_pro && $this->category && $this->category->has_child() ) {
					?>
					<a class="page-title-action"
						 href="<?php echo esc_url( $this->view_subcategories_url( $category_id ) ); ?>">
							 <?php
							 echo esc_html( __( 'Subcategories', 'nm-favourites-pro' ) );
							 ?>
					</a>
					<?php
				}
				?>
			</div>

			<div class="search-form">
				<?php
				if ( !in_array( $action, [ 'create_edit', 'view_tags' ], true ) ) {
					$this->categories_table->search_box(
						nm_favourites()->is_pro ? __( 'Search', 'nm-favourites-pro' ) : __( 'Search', 'nm-favourites' ),
						'search_id'
					);
				}
				?>
			</div>
		</div>
		<?php
	}

}
