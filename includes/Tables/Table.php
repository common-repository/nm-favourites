<?php

namespace NM\Favourites\Tables;

defined( 'ABSPATH' ) || exit;

class Table {

	protected $prefix = 'nm_favourites_';
	protected $id;
	protected $args;
	protected $data = [];
	protected $row_key;
	protected $cell_key;
	protected $cell_tag = 'td';
	protected $row_tag = 'tr';
	protected $body_tag = 'tbody';
	protected $header_tag = 'th';
	protected $head_tag = 'thead';
	protected $table_tag = 'table';
	protected $table_attributes = [];
	protected $row_attributes = [];
	protected $cell_attributes = [];
	protected $rows_object = []; // Items to create table rows for
	protected $row_object;
	protected $head = true;
	protected $pagination_args = [];
	protected $order;
	protected $orderby;
	protected $page = 1; // The current page to display
	protected $items_per_page;
	protected $items_count;

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_html_id() {
		return $this->prefix . $this->id;
	}

	public function get_template_id() {
		return $this->prefix . 'template_' . $this->id;
	}

	public function set_args( $args ) {
		$this->args = $args;
	}

	public function get_args() {
		return $this->args;
	}

	public function set_order( $order ) {
		$this->order = $order;
	}

	public function get_order() {
		return $this->order;
	}

	public function set_orderby( $orderby ) {
		$this->orderby = $orderby;
	}

	public function get_orderby() {
		return $this->orderby;
	}

	/**
	 * Set the data used to compose the table cells.
	 * This data should be an array with each key representing each cell key.
	 * @param array $data
	 */
	public function set_data( $data ) {
		$this->data = $data;
	}

	public function get_data() {
		return $this->data;
	}

	public function set_items_per_page( $val ) {
		$this->items_per_page = $val;
	}

	/**
	 * Set attributes for the table elements such as table, row, cell
	 * @param string $key The table element
	 * @param array $attributes The attributes to set
	 */
	public function set_attributes( string $key, array $attributes ) {
		$prop = $key . '_attributes';
		if ( property_exists( $this, $prop ) ) {
			$this->{$prop} = array_merge_recursive( $this->{$prop}, $attributes );
		}
	}

	public function set_table_attributes( $attributes ) {
		$this->set_attributes( 'table', $attributes );
	}

	/**
	 * Whether to show the header
	 * @param boolean $bool
	 */
	public function set_head( $bool ) {
		$this->head = $bool;
	}

	public function get_cell_key() {
		return $this->cell_key;
	}

	public function get_table() {
		return sprintf(
			'<%1$s %2$s>%3$s</%4$s>',
			$this->table_tag,
			nm_favourites_format_attributes( $this->get_table_attributes() ),
			$this->get_head() . $this->get_rows(),
			$this->table_tag
		);
	}

	public function get() {
		return $this->get_template();
	}

	public function get_template() {
		$nav = $this->get_nav();
		$template = '<div id="' . $this->get_template_id() . '">';
		$template .= $nav . $this->get_table() . $nav;
		$template .= '</div>';
		return $template;
	}

	public function show() {
		echo wp_kses( $this->get(), nm_favourites()->settings()->allowed_post_tags() );
	}

	protected function get_head() {
		if ( !$this->head ) {
			return;
		}

		$cells = '';
		foreach ( array_keys( $this->get_data() ) as $key ) {
			$cells .= $this->get_header( $key );
		}

		if ( $cells ) {
			return sprintf(
				'<%1$s>%2$s</%3$s>',
				$this->head_tag,
				$cells,
				$this->head_tag
			);
		}
	}

	protected function get_header( $key ) {
		$value = $this->get_data()[ $key ][ 'table_header_content' ] ?? $this->get_data()[ $key ][ 'label' ] ?? null;

		return sprintf(
			'<%1$s %2$s>%3$s</%4$s>',
			$this->header_tag,
			nm_favourites_format_attributes( $this->get_header_attributes( $key ) ),
			$value,
			$this->header_tag
		);
	}

	protected function rows_object() {
		return [];
	}

	/**
	 * Array of objects to create rows for
	 * @param array $object
	 */
	public function set_rows_object( $object = [] ) {
		$this->rows_object = $object;
	}

	/**
	 * The object to create a single row for
	 * @param array|object $object
	 */
	public function set_row_object( $object ) {
		$this->row_object = $object;
	}

	public function get_row_object() {
		return $this->row_object;
	}

	/**
	 * Get the table rows
	 * Typically this should be used after setting the objects to create rows for
	 * with $this->set_rows_object().
	 */
	protected function get_rows() {
		$rows = '';
		foreach ( $this->rows_object as $key => $object ) {
			$this->row_key = $key;
			$this->set_row_object( $object );
			$rows .= $this->get_row();
		}

		if ( $rows ) {
			return !$this->body_tag ? $rows : sprintf(
					'<%1$s>%2$s</%3$s>',
					$this->body_tag,
					$rows,
					$this->body_tag
			);
		}
	}

	/**
	 * Get the table row
	 * Typically this should be used after setting the row object with $this->set_row_object().
	 */
	public function get_row() {
		$cells = '';
		foreach ( array_keys( $this->get_data() ) as $key ) {
			$cells .= $this->get_cell( $key );
		}

		if ( $cells ) {
			return sprintf(
				'<%1$s %2$s>%3$s</%4$s>',
				$this->row_tag,
				nm_favourites_format_attributes( $this->get_row_attributes() ),
				$cells,
				$this->row_tag
			);
		}
	}

	public function get_cell( $key ) {
		$value = $this->get_data()[ $key ][ 'value' ] ?? null;

		if ( is_callable( $value ) ) {
			$this->cell_key = $key;
			$value = call_user_func( $value, $this );
		}

		return sprintf(
			'<%1$s %2$s>%3$s</%4$s>',
			$this->cell_tag,
			nm_favourites_format_attributes( $this->get_cell_attributes( $key ) ),
			$value,
			$this->cell_tag
		);
	}

	protected function get_table_attributes() {
		$attributes = [
			'id' => $this->get_html_id(),
			'data-page' => $this->pagination_args[ 'page' ] ?? null,
			'data-order' => $this->order,
			'data-orderby' => $this->orderby,
			'data-id' => $this->id,
			'class' => [
				"{$this->prefix}table",
			],
		];

		return $this->merge_args( $attributes, $this->table_attributes );
	}

	protected function get_header_attributes( $key ) {
		$data = $this->get_data()[ $key ] ?? [];

		$attributes = [
			'class' => [
				$this->prefix . $key
			],
		];

		if ( !empty( $data[ 'desc' ] ) ) {
			$attributes[ 'title' ] = $data[ 'desc' ];
		}

		return $attributes;
	}

	protected function get_row_attributes() {
		return $this->row_attributes;
	}

	protected function get_cell_attributes( $key ) {
		$data = $this->get_data()[ $key ] ?? [];

		$attributes = [
			'class' => [
				$this->prefix . $key
			],
		];

		if ( !empty( $data[ 'label' ] ) &&
			(!array_key_exists( 'table_header_content', $data ) || !empty( $data[ 'table_header_content' ] )) ) {
			$attributes[ 'data-title' ] = $data[ 'label' ];
		}

		return $this->merge_args( $attributes, $this->cell_attributes );
	}

	protected function get_items_count() {
		return $this->items_count;
	}

	public function set_items_count( $count ) {
		$this->items_count = ( int ) $count;
	}

	public function get_items_per_page() {
		$value = ( int ) (nm_favourites()->is_pro ?
			apply_filters( $this->prefix . 'items_per_page', $this->items_per_page, $this->id ) :
			$this->items_per_page);

		return $value > 0 ? $value : 0;
	}

	/**
	 * Set the current page we want to display
	 * @param int $page
	 */
	public function set_page( int $page ) {
		$this->page = $page;
	}

	public function set_pagination_args() {
		$limit = $this->get_items_per_page();
		if ( $limit ) {
			$total_pages = ceil( ( int ) $this->get_items_count() / $limit );
			if ( 1 < $total_pages ) {
				$current_page = ( float ) $this->page;
				$page = min( $current_page, $total_pages );
				$this->pagination_args = [
					'total_pages' => $total_pages,
					'limit' => $limit,
					'page' => $page,
					'offset' => max( 0, intval( $page - 1 ) * $limit ),
				];
			}
		}
	}

	public function get_pagination_args() {
		return $this->pagination_args;
	}

	public function process_args() {
		$this->set_orderby( $this->args[ 'orderby' ] ?? $this->orderby );
		$this->set_order( $this->args[ 'order' ] ?? $this->order );
		$this->set_page( ( int ) ($this->args[ 'page' ] ?? $this->page) );
		$this->set_items_per_page( $this->args[ 'per_page' ] ?? $this->items_per_page );
		$this->set_pagination_args();
	}

	public function get_query_args() {
		$args = [];
		$args[ 'limit' ] = ( int ) ($this->args[ 'limit' ] ?? null);
		$args[ 'orderby' ] = $this->get_orderby();
		$args[ 'order' ] = $this->get_order();
		return array_merge( $args, $this->get_pagination_args() );
	}

	public function setup() {
		$this->process_args();
		$this->set_rows_object( $this->rows_object() );
		return $this;
	}

	/**
	 * Get the navigation buttons for the account section table.
	 *
	 * For this to work, the property $pagination_args must be set and this
	 * can only be done with the function $this->process_pagination() is run.
	 * This function is usually run in order to get the table in the first place.
	 * Therefore the table must be gotten before this function can be run.
	 *
	 * @return string|void
	 */
	public function get_nav() {
		if ( empty( $this->pagination_args ) ) {
			return;
		}

		$defaults = [
			'page' => 1,
			'total_pages' => 1,
			'wrapper_attributes' => [
				'class' => [
					$this->prefix . 'navs',
					$this->id,
				],
			],
		];
		$pargs = $this->merge_args( $defaults, $this->pagination_args );
		$page = ( int ) $pargs[ 'page' ];
		$total_pages = ( int ) $pargs[ 'total_pages' ];

		if ( 2 > $total_pages ) {
			return;
		}

		ob_start();
		?>
		<div <?php echo wp_kses( nm_favourites_format_attributes( $pargs[ 'wrapper_attributes' ] ), [] ); ?>>
			<span class="page-info">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					nm_favourites()->is_pro ? esc_html__( 'Page %1$d of %2$d', 'nm-favourites-pro' ) : esc_html__( 'Page %1$d of %2$d', 'nm-favourites' ),
					absint( $page ),
					absint( $total_pages )
				);
				?>
			</span>
			<?php if ( 1 !== $page ) : ?>
				<a href="#"
					 class="previous <?php echo esc_attr( $this->prefix . 'nav' ); ?>"
					 data-action="previous"
					 data-target_id="<?php echo esc_attr( $this->get_html_id() ); ?>"
					 title="<?php
					 nm_favourites()->is_pro ?
							 esc_html_e( 'Previous', 'nm-favourites-pro' ) :
							 esc_html_e( 'Previous', 'nm-favourites' );
					 ?>">&#10092;</a>
				 <?php endif; ?>
				 <?php if ( $page !== $total_pages ) : ?>
				<a href="#"
					 class="next <?php echo esc_attr( $this->prefix . 'nav' ); ?>"
					 data-action="next"
					 data-target_id="<?php echo esc_attr( $this->get_html_id() ); ?>"
					 title="<?php
					 nm_favourites()->is_pro ?
							 esc_html_e( 'Next', 'nm-favourites-pro' ) :
							 esc_html_e( 'Next', 'nm-favourites' );
					 ?>">&#10093;</a>
				 <?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Merge supplied values with default values
	 * Should be used with only associative arrays.
	 * This function doesn't use wp_parse_args because it doesn't merge recursively.
	 *
	 * @param array $defaults Default values
	 * @param array $args Supplied values
	 * @return array
	 */
	public function merge_args( $defaults, $args ) {
		$merged = $defaults;
		foreach ( ( array ) $args as $key => $val ) {
			if ( isset( $defaults[ $key ] ) ) {
				$merged[ $key ] = is_array( $defaults[ $key ] ) ? array_merge_recursive( $defaults[ $key ], ( array ) $val ) : $val;
			} else {
				$merged[ $key ] = $val;
			}
		}
		return $merged;
	}

}
