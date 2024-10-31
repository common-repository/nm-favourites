<?php

namespace NM\Favourites\Lib;

class Modal {

	protected $id = '';
	protected $type = 'modal';
	protected $content = '';
	protected $options = [
		'width' => 300,
	];
	protected $container_attributes = [];
	protected $footer = '';
	public $is_admin = false;

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function set_option( $option, $value ) {
		$this->options[ $option ] = $value;
	}

	public function set_content( $content ) {
		$this->content = $content;
	}

	public function set_title( $title ) {
		$this->options[ 'title' ] = $title;
	}

	/**
	 * Set the opacity of the modal backdrop
	 * @param int|string $number Opacity. Should be between 0 and 1. Default is 0.7.
	 */
	public function set_opacity( $number ) {
		$this->options[ 'opacity' ] = $number;
	}

	protected function get_container_attributes( $formatted = false ) {
		$atts = array_merge( [ 'id' => $this->id ], $this->container_attributes );
		$atts[ 'data-options' ] = htmlspecialchars( wp_json_encode( $this->get_options() ) );
		return $formatted ? $this->format_attributes( $atts ) : $atts;
	}

	/**
	 * Make the modal width large (800px)
	 * Default width is 300px set in javascript dialog options
	 */
	public function make_large() {
		$this->options[ 'width' ] = 800;
	}

	protected function get_options() {
		$this->options[ 'classes' ] = [
			'ui-dialog' => "$this->type $this->id",
		];

		return $this->options;
	}

	public function set_footer( $footer ) {
		$this->footer = $footer;
	}

	/**
	 * Get the 'save' button for saving the component contents. Typically used in the footer.
	 * @param array $args Arguments used to compose the button html. Arguments supplied
	 * 									  are merged with the default arguments. Possible arguments:
	 * - text {string} The text to display in the button. Default "Save".
	 * - attributes {array} Attributes to add to the button such as class and data attributes.
	 * @param string $action The type of result to return. Valid values are
	 * - replace : Whether to replace the default arguments with the supplied arguments instead of merging.
	 * - args: Whether to return only the arguments used to compose the button instead of the button html.
	 * Default value is "null" which is to merge the default arguments with the supplied arguments.
	 * @return mixed
	 */
	public function get_save_button( $args = array(), $action = null ) {
		$attributes = $this->get_default_button_attributes();
		$attributes[ 'class' ][] = 'nm-save';
		$text = nm_favourites()->is_pro ?
			__( 'Save', 'nm-favourites-pro' ) :
			__( 'Save', 'nm-favourites' );
		$defaults = [ 'text' => $text, 'attributes' => $attributes ];

		return 'args' === $action ? $defaults : $this->compose_button( $defaults, $args, $action );
	}

	protected function get_default_button_attributes() {
		return [
			'type' => 'button',
			'class' => [
				'btn',
				'button',
				'nm-dialog-button',
			],
		];
	}

	protected function compose_button( $defaults, $args, $action ) {
		if ( 'replace' === $action ) {
			$params = $args;
		} else {
			$params = $this->merge_button_args( $defaults, $args );
		}

		return '<button ' . $this->format_attributes( $params[ 'attributes' ] ) .
			'>' . esc_html( $params[ 'text' ] ) . '</button>';
	}

	protected function merge_button_args( $defaults, $args ) {
		$result = $defaults;
		if ( isset( $args[ 'attributes' ] ) ) {
			$result[ 'attributes' ] = array_merge_recursive( $defaults[ 'attributes' ], $args[ 'attributes' ] );
			unset( $args[ 'attributes' ] );
		}
		return array_merge_recursive( $result, ( array ) $args );
	}

	protected function selector( $selector = '' ) {
		echo esc_attr( ".$this->id $selector" );
	}

	protected function styles() {
		$width = $this->options[ 'width' ] ?? 0;

		if ( !empty( $this->options[ 'minWidth' ] ) ) {
			$width = $this->options[ 'minWidth' ];
		}
		?>
		<style>
		<?php
		if ( $this->options[ 'opacity' ] ) {
			?>
			<?php $this->selector( '+ .ui-widget-overlay' ); ?> {
					opacity: <?php echo esc_attr( $this->options[ 'opacity' ] ); ?>
				}
			<?php
		}
		?>
		<?php if ( $width && 'modal' === $this->type ) { ?>
				@media (max-width: <?php echo esc_attr( $width ); ?>px) {
			<?php $this->selector(); ?> {
						left: 50%;
						transform: translateX(-50%);
						max-width: 95vw;
					}
				}
		<?php } ?>

		<?php $this->selector(); ?> {
				border-radius: calc(.3rem - 1px);
			}

		<?php
		/**
		 * Run this code on admin area because woocommerce jquery ui css affects the display
		 * of these selectors in admin
		 */
		if ( $this->is_admin ):
			?>
			<?php $this->selector( '.ui-dialog-titlebar-close' ); ?> {
					height: 36px;
					top: inherit;
					margin: inherit;
				}
			<?php
		endif;
		?>

		<?php
		/**
		 * Run this code on admin area because woocommerce jquery ui css affects the display
		 * of these selectors in admin
		 */
		if ( $this->is_admin && !empty( $this->options[ 'title' ] ) ) :
			?>
			<?php $this->selector( '.ui-dialog-titlebar' ); ?> {
					background: transparent;
					border: none;
					border-bottom: 1px solid #dcdcde;
					border-radius: 0;
					font-weight: 400;
				}
			<?php
		endif;
		?>

		<?php if ( empty( $this->options[ 'title' ] ) ) : ?>
			<?php $this->selector( '.ui-dialog-titlebar' ); ?> {
					height: 0;
				}
		<?php endif; ?>

		<?php if ( $this->footer ) : ?>
			<?php $this->selector( '.footer' ); ?> {
					display: flex;
					flex-wrap: wrap;
					flex-shrink: 0;
					align-items: center;
					justify-content: flex-end;
					padding-top: 16px;
					margin-top: 16px;
					border-top: 1px solid #dee2e6;
				}
		<?php endif; ?>
		</style>
		<?php
	}

	protected function template() {
		?>
		<div <?php echo wp_kses( $this->get_container_attributes( true ), [] ); ?>>
			<?php
			$this->styles();
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The output has already been escaped in the included file
			echo $this->content;
			echo $this->footer ? '<div class="footer">' . $this->footer . '</div>' : '';
			// phpcs:enable
			?>
		</div>
		<?php
	}

	protected function format_attributes( $attributes ) {
		$attr = array();

		if ( !empty( $attributes ) ) {
			foreach ( ( array ) $attributes as $key => $value ) {
				$val = is_array( $value ) ? implode( ' ', $value ) : $value;
				$attr[] = $key . '="' . $val . '"';
			}
		}
		return implode( ' ', $attr );
	}

	public function get() {
		ob_start();
		$this->print();
		return ob_get_clean();
	}

	public function print() {
		$this->template();
	}

}
