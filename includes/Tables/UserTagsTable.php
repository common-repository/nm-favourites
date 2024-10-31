<?php

namespace NM\Favourites\Tables;

use NM\Favourites\Tables\Table;
use NM\Favourites\Fields\Fields;

class UserTagsTable extends Table {

	protected $id = 'user_tags_table';

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
		$fields->order();
		$fields->filter_by_order();
		$fields->set_args( [ 'category' => $this->category ] );

		$this->set_data( $fields->get_data() );
		$this->set_items_count( $this->category->tag()->get_user_tags_count_in_category() );
		$this->set_args( $args );
		$this->setup();
		$this->set_rows_object( $this->category->tag()->get_user_tags_in_category( $this->get_query_args() ) );

		$table_attributes = [
			'data-category_id' => $this->category->get_id(),
			'data-user_id' => $this->category->user()->get_id(),
			'data-per_page' => $this->get_items_per_page(),
		];
		$this->set_table_attributes( $table_attributes );
	}

	protected function data() {
		$object_type = $this->category->get_object_type();
		if ( method_exists( $this, "data_$object_type" ) ) {
			$default_data = $this->data_default();
			unset( $default_data[ 'tag' ] );
			$object_type_data = call_user_func( [ $this, "data_$object_type" ] );
			$data = array_merge( $object_type_data, $default_data );
		} else {
			$data = $this->data_default();
		}

		return $data;
	}

	protected function data_default() {
		$data = [
			'tag' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Tag', 'nm-favourites-pro' ) :
				__( 'Tag', 'nm-favourites' ),
				'value' => [ $this, 'get_column_tag' ],
			],
			'date' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Date', 'nm-favourites-pro' ) :
				__( 'Date', 'nm-favourites' ),
				'value' => [ $this, 'get_column_date' ],
			],
		];

		if ( $this->category->user()->is_same() ) {
			$data[ 'actions' ] = [
				'label' => nm_favourites()->is_pro ?
				__( 'Actions', 'nm-favourites-pro' ) :
				__( 'Actions', 'nm-favourites' ),
				'value' => [ $this, 'get_column_actions' ],
			];
		}

		return $data;
	}

	protected function data_post() {
		return [
			'image' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Image', 'nm-favourites-pro' ) :
				__( 'Image', 'nm-favourites' ),
				'value' => [ $this, 'get_column_image' ],
			],
			'title' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Title', 'nm-favourites-pro' ) :
				__( 'Title', 'nm-favourites' ),
				'value' => [ $this, 'get_column_title' ],
			],
		];
	}

	protected function data_comment() {
		return [
			'comment_author' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Author', 'nm-favourites-pro' ) :
				__( 'Author', 'nm-favourites' ),
				'value' => [ $this, 'get_column_comment_author' ],
			],
			'comment' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Comment', 'nm-favourites-pro' ) :
				__( 'Comment', 'nm-favourites' ),
				'value' => [ $this, 'get_column_comment' ],
			],
			'comment_response' => [
				'label' => nm_favourites()->is_pro ?
				__( 'In response to', 'nm-favourites-pro' ) :
				__( 'In response to', 'nm-favourites' ),
				'value' => [ $this, 'get_column_comment_response' ],
			],
		];
	}

	protected function data_product() {
		return array_merge( $this->data_post(), [
			'price' => [
				'label' => nm_favourites()->is_pro ?
				__( 'Price', 'nm-favourites-pro' ) :
				__( 'Price', 'nm-favourites' ),
				'value' => [ $this, 'get_column_price' ],
			],
			] );
	}

	public function get_column_tag() {
		$tag = $this->get_row_object();
		return $tag->get_object_id();
	}

	public function get_column_date() {
		$tag = $this->get_row_object();
		return wp_kses_post( mysql2date( get_option( 'date_format' ), $tag->get_date_created() ) );
	}

	public function get_column_image() {
		$tag = $this->get_row_object();
		$post = get_post( $tag->get_object_id() );
		return get_the_post_thumbnail( $post, 'thumbnail' );
	}

	public function get_column_title() {
		$tag = $this->get_row_object();
		$post = get_post( $tag->get_object_id() );
		return '<a href="' . get_permalink( $post ) . '">' . get_the_title( $post ) . '</a>';
	}

	public function get_column_price() {
		$tag = $this->get_row_object();
		$product = wc_get_product( $tag->get_object_id() );
		return $product->get_price_html();
	}

	public function get_column_comment_author() {
		$tag = $this->get_row_object();
		$comment = get_comment( $tag->get_object_id() );
		if ( $comment ) {
			return '<strong>' . get_comment_author( $comment ) . '</strong><br />' . get_comment_author_email( $comment );
		}
	}

	public function get_column_comment() {
		$tag = $this->get_row_object();
		$comment = get_comment( $tag->get_object_id() );
		if ( $comment ) {
			return get_comment_text( $comment );
		}
	}

	public function get_column_comment_response() {
		$tag = $this->get_row_object();
		$comment = get_comment( $tag->get_object_id() );
		if ( $comment ) {
			$post = get_post( $comment->comment_post_ID );

			if ( $post ) {
				return '<a href="' . get_permalink( $post->ID ) . '">' . get_the_title( $post->ID ) . '</a>';
			}
		}
	}

	public function get_column_actions() {
		$tag = $this->get_row_object();
		ob_start();
		?>
		<a href="#" class="nm_favourites_shortcode_1" data-nm_favourites_post_action="delete_tag"
			 data-confirm="<?php echo esc_attr( nm_favourites_confirm_text() ); ?>"
			 data-category_id="<?php echo esc_attr( $tag->get_category_id() ); ?>"
			 data-location="table"
			 data-tag_id="<?php echo esc_attr( $tag->get_id() ); ?>">
				 <?php
				 nm_favourites()->is_pro ? esc_html_e( 'Remove', 'nm-favourites-pro' ) : esc_html_e( 'Remove', 'nm-favourites' );
				 ?>
		</a>
		<?php
		return ob_get_clean();
	}

	public function get_row_attributes() {
		$attributes = parent::get_row_attributes();
		$tag = $this->get_row_object();
		$attributes[ 'id' ] = $tag ? 'nm_favourites_tag_' . $tag->get_id() : null;
		return $attributes;
	}

}
