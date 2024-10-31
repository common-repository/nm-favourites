<?php

namespace NM\Favourites\Tables;

use NM\Favourites\Tables\Table;
use NM\Favourites\Fields\Fields;

class UserCategoriesTable extends Table {

	protected $id = 'user_categories_table';

	/**
	 * @var \NM\Favourites\Sub\Category | \NM\Favourites\Lib\Category
	 */
	protected $category;

	public function __construct( $category, $args = [] ) {
		$this->category = $category;
		nm_favourites()->is_pro ? ($this->items_per_page = 10 ) : '';

		$fields = new Fields();
		$fields->set_id( $this->id );
		$fields->set_data( $this->data() );
		$fields->set_values( [ $this, 'column_value' ] );
		$this->set_data( $fields->get_data() );

		$this->set_args( $args );
		$this->setup();
		$query_args = $this->get_pagination_args();
		$query_args[ 'orderby' ] = $this->orderby;
		$query_args[ 'order' ] = $this->order;

		$this->set_rows_object( $this->category->get_user_categories( $query_args ) );

		$table_attributes = [
			'data-user_id' => $this->category->user()->get_id(),
			'data-per_page' => $this->items_per_page,
		];
		$this->set_table_attributes( $table_attributes );
	}

	protected function get_items_count() {
		return $this->category->get_user_categories_count();
	}

	protected function data() {
		$data = [
			'name' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Name', 'nm-favourites-pro' ) :
				__( 'Name', 'nm-favourites' ),
			],
			'button' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Button', 'nm-favourites-pro' ) :
				__( 'Button', 'nm-favourites' ),
			],
			'count' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Count', 'nm-favourites-pro' ) :
				__( 'Count', 'nm-favourites' ),
			],
			'visibility' => [
				'label' => __( 'Visibility', 'nm-favourites-pro' ),
			],
			'actions' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Actions', 'nm-favourites-pro' ) :
				__( 'Actions', 'nm-favourites' ),
			],
		];

		if ( !nm_favourites()->is_pro ) {
			unset( $data[ 'visibility' ] );
		}

		return $data;
	}

	public function column_value() {
		$key = $this->get_cell_key();
		$category = $this->get_row_object();
		$url = $category->get_url();

		ob_start();

		switch ( $key ) {
			case 'name':
				$name = $category->get_name();
				$parent_category = $category->get_parent_category();
				// &#40; - brackets in html code
				$parent_name = $parent_category ? ' &#40;' . $parent_category->get_name() . '&#41;' : '';
				printf( '<a href="%s">%s</a> %s',
					esc_url( $url ),
					esc_html( $name ),
					esc_html( $parent_name ?? '' )
				);
				break;
			case 'button':
				$button = nm_favourites()->button( $category->get_id() );
				$button->set_preview( true );
				$button->count_template( false );
				$button->show();
				break;
			case 'count':
				echo esc_html( $category->get_extra_data( 'count' ) );
				break;
			case 'visibility':
				if ( method_exists( $category, 'get_visibility_template' ) ) {
					$category->get_visibility_template( true );
				}
				break;
			case 'actions':
				printf( '<a href="%s" class="nm_favourites_item">%s</a>',
					esc_url( $url ),
					( nm_favourites()->is_pro ? esc_html__( 'View', 'nm-favourites-pro' ) : esc_html__( 'View', 'nm-favourites' ) )
				);

				if ( nm_favourites()->is_pro && $category->is_user_own() ) {
					printf( '<a href="#" class="nm_favourites_item nm_favourites_shortcode_1"
						data-nm_favourites_post_action="delete_category" style="margin-left:12px;"
						data-location="table"
						data-confirm="%s" data-category_id="%d">%s</a>',
						esc_html( nm_favourites_confirm_text() ),
						( int ) $category->get_id(),
						esc_html( nm_favourites_delete_text() )
					);
				}
				break;
		}

		return ob_get_clean();
	}

	public function get_row_attributes() {
		$attributes = parent::get_row_attributes();
		$category = $this->get_row_object();
		$attributes[ 'id' ] = $category ? 'nm_favourites_category_' . $category->get_id() : null;
		return $attributes;
	}

}
