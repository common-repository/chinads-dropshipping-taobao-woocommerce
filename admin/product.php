<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Taobao_Post;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;


class Product {
	protected static $instance = null;

	private $settings;

	public function __construct() {
		$this->settings = Data::instance();
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'deleted_post' ) );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 20, 2 );
		add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_add_taobao_variation_selection' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'woocommerce_save_product_variation' ), 10, 2 );

		add_action( 'woocommerce_product_options_pricing', [ $this, 'simple_add_taobao_variation_selection' ], 99 );
		add_action( 'woocommerce_process_product_meta_simple', [ $this, 'woocommerce_process_product_meta_simple' ] );
		add_action( 'add_meta_boxes', array( $this, 'taobao_product_info' ) );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function taobao_product_info() {
		global $post;
		$product_id = $post->ID ??'';
		if ( get_post_meta( $product_id, '_tbds_taobao_product_id', true ) ) {
			add_meta_box(
				'taobao_product_info', esc_html__( 'Taobao product info', 'chinads' ),
				[ $this, 'add_meta_box_callback' ], 'product', 'side', 'high'
			);
		}
	}

	public function add_meta_box_callback( $post ) {
		$product_id        = $post->ID;
		$taobao_product_id = get_post_meta( $product_id, '_tbds_taobao_product_id', true );
		$taobao_url        = Utils::get_taobao_url( $product_id );
		if ( $taobao_url ) {
			printf( "<p>%s <a target='_blank' href='%s'>%s</a></p>", esc_html__( 'External ID', 'chinads' ), esc_url( $taobao_url ), esc_html( $taobao_product_id ) );
		}

		printf( "<p class='tbds-view-original-product-button'><a target='_blank' class='button' href='%s'>%s</a></p>",
			esc_url( admin_url( "admin.php?page=tbds-imported&tbds_search_woo_id={$product_id}" ) ),
			esc_html__( 'View on Imported page', 'chinads' ) );
	}

	/**
	 * @param $product_id
	 */
	public function woocommerce_process_product_meta_simple( $product_id ) {
        if (!wp_verify_nonce($_POST['tbds-settings-nonce']?? '','tbds-settings')){
            return;
        }
		$skuID = isset( $_POST['tbds_simple_variation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tbds_simple_variation_id'] ) ) : '';
		if ( $skuID ) {
			update_post_meta( $product_id, '_tbds_taobao_variation_id', $skuID );
		}
	}

	/**
	 *
	 */
	public function simple_add_taobao_variation_selection() {
		global $post;
		$product_id = $post->ID;
		if ( get_post_meta( $product_id, '_tbds_taobao_product_id', true ) ) {
			$from_id = Taobao_Post::get_post_id_by_woo_id( $product_id, false, false, [ 'publish', 'override' ] );
			if ( $from_id ) {
				$variations = Taobao_Post::get_post_meta( $from_id, '_tbds_variations', true );
				$skuAttr    = get_post_meta( $product_id, '_tbds_taobao_variation_id', true );
				if ( $skuAttr || count( $variations ) > 1 ) {
					$id = "tbds-original-attributes-simple-{$product_id}";
					wp_nonce_field('tbds-settings', 'tbds-settings-nonce', false);
					?>
                    <p class="tbds-original-attributes tbds-original-attributes-simple form-field">
                        <label for="<?php echo esc_attr( $id ) ?>">
							<?php esc_html_e( 'Original taobao variation', 'chinads' ); ?>
                        </label>
						<?php echo wp_kses_post( wc_help_tip( esc_html__( 'If your customers buy this product, this selected taobao variation will be used when fulfilling taobao orders', 'chinads' ) ) ) ?>
                        <select id="<?php echo esc_attr( $id ) ?>" class="tbds-original-attributes-select" name="tbds_simple_variation_id">
                            <option value=""><?php esc_html_e( 'Please select original variation', 'chinads' ); ?></option>
							<?php
							foreach ( $variations as $key => $value ) {
								$attr_name = '';
                                $attributes = (array) $value['attributes'] ?? array();
								if ( isset( $value['attributes_sub'] ) && is_array($value['attributes_sub'] ) && count( $value['attributes_sub'] ) > count( $attributes ) ) {
									$attr_name = implode( ', ', $value['attributes_sub'] );
								} elseif ( count( $attributes ) ) {
									$attr_name = implode( ', ', $attributes );
								}

								if ( ! $attr_name ) {
									continue;
								}

								printf( '<option value="%1$s"  data-tbds_sku_id="%1$s" %2$s>%3$s</option>',
									esc_attr( $value['skuId'] ), selected( $value['skuId'], $skuAttr, false ), esc_html( $attr_name ) );
							}
							?>
                        </select>
                    </p>
					<?php
				}
			}
		}
	}

	/**
	 * @param $variation_id
	 * @param $i
	 */
	public function woocommerce_save_product_variation( $variation_id, $i ) {
		if (!wp_verify_nonce($_POST['tbds-settings-'.$i.'-nonce'] ??'','tbds-settings-'.$i)){
			return;
		}
		$skuID = isset( $_POST['tbds_variation_id'], $_POST['tbds_variation_id'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['tbds_variation_id'][ $i ] ) ) : '';
		if ( $skuID ) {
			update_post_meta( $variation_id, '_tbds_taobao_variation_id', $skuID );
		}
	}

	/**
	 * @param $loop
	 * @param $variation_data
	 * @param $variation WP_Post
	 */
	public function variation_add_taobao_variation_selection( $loop, $variation_data, $variation ) {
		global $post;
		$product_id = $post->ID;
		if ( $variation && get_post_meta( $product_id, '_tbds_taobao_product_id', true ) ) {
			$from_id = Taobao_Post::get_post_id_by_woo_id( $product_id, false, false, [ 'publish', 'override' ] );
			if ( $from_id ) {
				$variation_id = $variation->ID;
				$variations   = Taobao_Post::get_post_meta( $from_id, '_tbds_variations', true );
				$skuAttr      = get_post_meta( $variation_id, '_tbds_taobao_variation_id', true );
				$id           = "tbds-original-attributes-{$variation_id}";
				wp_nonce_field('tbds-settings-'.$loop, 'tbds-settings-'.$loop.'-nonce', false);
				?>
                <div class="tbds-original-attributes tbds-original-attributes-variable">
                    <label for="<?php echo esc_attr( $id ) ?>">
						<?php esc_html_e( 'Original taobao variation', 'chinads' ); ?>
                    </label>
					<?php echo wp_kses_post( wc_help_tip( esc_html__( 'If your customers buy this product, this selected taobao variation will be used when fulfilling taobao orders', 'chinads' ) ) ) ?>
                    <select id="<?php echo esc_attr( $id ) ?>" class="tbds-original-attributes-select" name="tbds_variation_id[<?php echo esc_attr( $loop ) ?>]">
						<?php
						if ( ! $skuAttr ) {
							?>
                            <option value=""><?php esc_html_e( 'Please select original variation', 'chinads' ); ?></option>
							<?php
						}
						foreach ( $variations as $key => $value ) {
							$attr_name = '';
                            $attributes = (array) $value['attributes'] ?? array();
							if ( isset( $value['attributes_sub'] ) && is_array($value['attributes_sub']) && count( $value['attributes_sub'] ) > count( $attributes ) ) {
								$attr_name = implode( ', ', $value['attributes_sub'] );
							} elseif ( count( $attributes) ) {
								$attr_name = implode( ', ', $attributes );
							}

							if ( ! $attr_name ) {
								continue;
							}

							printf( '<option value="%1$s"  data-tbds_sku_id="%1$s" %2$s>%3$s</option>',
								esc_attr( $value['skuId'] ), selected( $value['skuId'], $skuAttr, false ), esc_html( $attr_name ) );

						}
						?>
                    </select>
                </div>
				<?php
			}
		}
	}

	/**
	 * @param $actions
	 * @param $post
	 *
	 * @return mixed
	 */
	public function post_row_actions( $actions, $post ) {
		if ( $post && $post->post_type === 'product' && $post->post_status !== 'trash' ) {
			$taobao_url = Utils::get_taobao_url( $post->ID );
			if ( $taobao_url ) {
				$actions['tbds_view_on_taobao'] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $taobao_url ), esc_html__( 'View product on Taobao', 'chinads' ) );

				$actions['tbds_view_on_imported_page'] = sprintf( '<a href="%s" target="_blank">%s</a>',
					esc_url( admin_url( "admin.php?page=tbds-imported&tbds_search_woo_id={$post->ID}" ) ),
					esc_html__( 'View product on Imported', 'chinads' ) );
            }

		}

		return $actions;
	}


	/**Set a product status
	 *
	 * @param $product_id
	 * @param string $status
	 */
	public function set_status( $product_id, $status = 'trash' ) {
		$taobao_sku = get_post_meta( $product_id, '_tbds_taobao_product_id', true );
		if ( $taobao_sku ) {
			if ( $status === 'publish' ) {
				$id = Taobao_Post::get_post_id_by_woo_id( $product_id, false, false, 'trash' );
			} else {
				$id = Taobao_Post::get_post_id_by_woo_id( $product_id );
			}
			if ( $id ) {
				Taobao_Post::update_post( array( 'ID' => $id, 'post_status' => $status ) );
			}
		}
	}

	/**Set a product status to trash when a WC product is deleted
	 *
	 * @param $product_id
	 */
	public function deleted_post( $product_id ) {
		$this->set_status( $product_id, 'trash' );
	}

	/**Set a product status to trash when a WC product is trashed and set to publish when a trashed product is restored
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( 'product' === $post->post_type ) {
			$product_id = $post->ID;
			if ( 'trash' === $new_status ) {
				$this->set_status( $product_id );
			} elseif ( $old_status === 'trash' ) {
				$this->set_status( $product_id, 'publish' );
			}
		}
	}
}
