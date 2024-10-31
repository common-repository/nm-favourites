<?php

namespace NM\Favourites\Tables;

use NM\Favourites\Tables\Table;
use NM\Favourites\Fields\Fields;
use NM\Favourites\Settings\CategoriesTags;

class ObjectCountInCategoriesTable extends Table {

	protected $id = 'object_count_in_categories_table';
	protected $object_id;

	public function __construct( $object_id, $buttons ) {
		$this->object_id = $object_id;

		if ( !empty( $buttons ) ) {
			$fields = new Fields();
			$fields->set_id( $this->id );
			$fields->set_data( $this->data() );
			$fields->set_values( [ $this, 'column_value' ] );

			$this->set_data( $fields->get_data() );
			$this->set_rows_object( $buttons );
		}
	}

	protected function data() {
		return [
			'category' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Category', 'nm-favourites-pro' ) :
				__( 'Category', 'nm-favourites' ),
			],
			'count' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Count', 'nm-favourites-pro' ) :
				__( 'Count', 'nm-favourites' ),
			],
		];
	}

	public function column_value() {
		$key = $this->get_cell_key();
		$button = $this->get_row_object();

		ob_start();

		switch ( $key ) {
			case 'category':
				$name = $button->category()->get_name();
				$pname = $button->category()->is_parent() ? $name : '&mdash; ' . $name;
				printf( '<a href="%s">%s</a>',
					esc_url( CategoriesTags::view_tags_for_object_url( $this->object_id, $button->get_id() ) ),
					esc_html( $pname )
				);
				break;
			case 'count':
				echo ( int ) $button->count();
				break;
		}

		return ob_get_clean();
	}

	public function styles() {
		?>
		<style>
			<?php echo '#' . esc_attr( $this->get_html_id() ); ?> td,
			<?php echo '#' . esc_attr( $this->get_html_id() ); ?> th {
				padding: .625em;
				text-align: left;
			}
		</style>
		<?php
	}

}
