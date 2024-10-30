<?php

namespace TaobaoDropship\Admin;

use Exception;
use WC_Product;
use TaobaoDropship\Admin\Background_Process\Download_Images;
use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Utils;
use TaobaoDropship\Inc\Taobao_Post;

defined( 'ABSPATH' ) || exit;

class Import_List {
	protected static $instance = null;
	protected $settings;
	public $process_image;

	public function __construct() {
		$this->settings = Data::instance();
		add_action( 'wp_ajax_tbds_load_variations_table', [ $this, 'load_variations_table' ] );
		add_action( 'wp_ajax_tbds_import', array( $this, 'import' ) );
		add_action( 'wp_ajax_tbds_remove', array( $this, 'remove' ) );
		add_action( 'wp_ajax_tbds_override', array( $this, 'override' ) );
		add_action( 'wp_ajax_tbds_search_product', array( $this, 'search_product' ) );
		add_action( 'wp_ajax_tbds_save_attributes', array( $this, 'save_attributes' ) );
		add_action( 'wp_ajax_tbds_remove_attribute', array( $this, 'ajax_remove_attribute' ) );

		add_action( 'init', array( $this, 'background_process' ) );
		add_action( 'admin_init', array( $this, 'empty_import_list' ) );
		add_action( 'admin_head', array( $this, 'menu_product_count' ), 999 );
	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function search_product() {
		check_ajax_referer( 'tbds_security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$keyword                 = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : '';
		$exclude_taobao_products = isset( $_GET['exclude_taobao_products'] ) ? sanitize_text_field( wp_unslash( $_GET['exclude_taobao_products'] ) ) : '';

		if ( empty( $keyword ) ) {
			die();
		}

		$post_status = array( 'publish' );
		if ( current_user_can( 'edit_private_products' ) ) {
			if ( $exclude_taobao_products ) {
				$post_status = array(
					'private',
					'draft',
					'pending',
					'publish'
				);
			} else {
				$post_status = array(
					'private',
					'publish'
				);
			}
		}

		$arg = array(
			'post_type'      => 'product',
			'posts_per_page' => 50,
			's'              => $keyword,
			'post_status'    => apply_filters( 'tbds_search_product_statuses', $post_status )
		);

		if ( $exclude_taobao_products ) {
			$arg['meta_query'] = array(//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => '_tbds_taobao_product_id',
					'compare' => 'NOT EXISTS'
				)
			);
		}

		$the_query      = new \WP_Query( $arg );
		$found_products = [];

		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$product_id       = get_the_ID();
				$found_products[] = array(
					'id'   => $product_id,
					'text' => "(#{$product_id}) " . get_the_title()
				);
			}
		}

		wp_send_json( $found_products );
	}

	public function page_callback() {
		$user     = get_current_user_id();
		$screen   = get_current_screen();
		$option   = $screen->get_option( 'per_page', 'option' );
		$decimals = wc_get_price_decimals() < 1 ? 1 : pow( 10, ( - 1 * wc_get_price_decimals() ) );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty ( $per_page ) || $per_page < 1 ) {
			$per_page = $screen->get_option( 'per_page', 'default' );
		}

		$paged = isset( $_GET['paged'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : 1;//phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'post_type'      => 'tbds_draft_product',
			'post_status'    => array( 'draft', 'override' ),
			'order'          => 'DESC',
			'orderby'        => 'date',
			'fields'         => 'ids',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
		);

		$tbds_search_id = isset( $_GET['tbds_search_id'] ) ? sanitize_text_field( wp_unslash( $_GET['tbds_search_id'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$keyword        = isset( $_GET['tbds_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tbds_search'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $tbds_search_id ) {
			$args['post__in']       = array( $tbds_search_id );
			$args['posts_per_page'] = 1;
			$keyword                = '';
		} else if ( $keyword ) {
			$args['s'] = $keyword;
		}

		$the_query   = Taobao_Post::query( $args );
		$product_ids = $the_query->get_posts();
		$count       = $the_query->found_posts;
		$total_page  = $the_query->max_num_pages;
		wp_reset_postdata();
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Import List', 'chinads' ) ?></h2>

			<?php
			if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
				include_once TBDS_CONST['admin_views'] . 'html-import-list-header-section.php';

				$key                         = 0;
				$currency                    = 'CNY';
				$woocommerce_currency        = get_woocommerce_currency();
				$woocommerce_currency_symbol = get_woocommerce_currency_symbol();
				$use_different_currency      = false;
				if ( strtolower( $woocommerce_currency ) != strtolower( $currency ) ) {
					$use_different_currency = true;
				}
				extract( [
					'default_select_image'        => $this->settings->get_param( 'product_gallery' ),
					'product_status'              => $this->settings->get_param( 'product_status' ),
					'product_shipping_class'      => $this->settings->get_param( 'product_shipping_class' ),
					'catalog_visibility'          => $this->settings->get_param( 'catalog_visibility' ),
					'manage_stock'                => $this->settings->get_param( 'manage_stock' ),
					'product_tags'                => $this->settings->get_param( 'product_tags' ),
					'product_categories'          => $this->settings->get_param( 'product_categories' ),
					'category_options'            => Utils::get_product_categories(),
					'tags_options'                => Utils::get_product_tags(),
					'shipping_class_options'      => Utils::get_shipping_class_options(),
					'catalog_visibility_options'  => Utils::get_catalog_visibility_options(),
					'product_status_options'      => Utils::get_product_status_options(),
					'use_different_currency'      => $use_different_currency,
					'woocommerce_currency_symbol' => $woocommerce_currency_symbol,
				] );

				?>
                <div class="vi-ui segment <?php echo esc_attr( Utils::set_class_name( 'import-list' ) ) ?>">
					<?php
					foreach ( $product_ids as $product_id ) {
						$product    = Taobao_Post::get_post( $product_id );
						$attributes = $this->get_product_attributes( $product_id );
						$parent     = [];

						if ( is_array( $attributes ) && count( $attributes ) ) {
							foreach ( $attributes as $attribute_k => $attribute_v ) {
								$parent[ $attribute_k ] = $attribute_v['slug'];
							}
						}

						$gallery = Taobao_Post::get_post_meta( $product_id, '_tbds_gallery', true );
						if ( ! $gallery ) {
							$gallery = [];
						}

						$desc_images         = Taobao_Post::get_post_meta( $product_id, '_tbds_description_images', true );
						$price_alert         = false;
						$product_type        = $product->post_status;
						$override_product_id = $product->post_parent;
						$override_product    = '';

						if ( $product_type === 'override' && $override_product_id ) {
							$override_product = Taobao_Post::get_post( $override_product_id );
							if ( ! $override_product ) {
								$product_type        = 'draft';
								$override_product_id = '';
								Taobao_Post::update_post( array(
									'ID'          => $product_id,
									'post_parent' => 0,
									'post_status' => $product_type,
								) );
							}
						}

						$accordion_class = [ 'vi-ui', 'styled', 'fluid', 'accordion', 'active', 'tbds-accordion', 'tbds-product-row' ];

						if ( $price_alert ) {
							$accordion_class[] = 'tbds-product-price-alert';
						}

						extract( [
							'host'        => Taobao_Post::get_post_meta( $product_id, '_tbds_taobao_host', true ),
							'sku'         => Taobao_Post::get_post_meta( $product_id, '_tbds_sku', true ),
							'store_info'  => Taobao_Post::get_post_meta( $product_id, '_tbds_store_info', true ),
							'image'       => isset( $gallery[0] ) ? $gallery[0] : '',
							'variations'  => $this->get_product_variations( $product_id ),
							'desc_images' => ! $desc_images ? [] : array_values( array_unique( $desc_images ) ),
							'is_variable' => is_array( $parent ) && ! empty( $parent )
						] );

						include TBDS_CONST['admin_views'] . '/html-import-list-item.php';

						$key ++;
					}
					?>
                </div>
				<?php
				include_once TBDS_CONST['admin_views'] . 'html-import-list-bulk-action-modal.php';
				wc_get_template( 'html-import-list-override-options.php',
					array(
						'settings' => $this->settings,
					),
					'',
					TBDS_CONST['admin_views'] );
			} else {
				?>
                <div>
                    <p><?php esc_html_e( 'No products found', 'chinads' ) ?></p>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}

	public function simple_product_price_field_html( $key, $manage_stock, $variations, $use_different_currency, $currency, $product_id, $woocommerce_currency_symbol, $decimals, $coutry = '', $company = '' ) {
		if ( empty( $variations ) ) {
			return;
		}

		//Maybe shipping cost at here

		$inventory               = intval( $variations[0]['stock'] );
		$variation_sale_price    = $variations[0]['sale_price'] ? ( ( $variations[0]['sale_price'] ) ) : ( $variations[0]['sale_price'] );
		$variation_regular_price = floatval( $variations[0]['regular_price'] );
		$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
		$sale_price              = $this->process_price( $price, true );
		$regular_price           = $this->process_price( $price );

		$cost_html = wc_price( $price, [ 'currency' => $currency, 'decimals' => 2, 'price_format' => '%1$s&nbsp;%2$s' ] );

		if ( $use_different_currency ) {
			$cost_html .= '(' . wc_price( $this->process_exchange_price( $price ) ) . ')';
		}

		$sale_price    = $this->process_exchange_price( $sale_price );
		$regular_price = $this->process_exchange_price( $regular_price );

		?>
        <div class="field <?php echo esc_attr( Utils::set_class_name( 'simple-product-price-field' ) ) ?>">
            <input type="hidden"
                   name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][0][skuId]' ) ?>"
                   value="<?php echo esc_attr( $variations[0]['skuId'] ?? '' ) ?>">
            <div class="equal width fields">

                <div class="field">
                    <label><?php esc_html_e( 'Cost', 'chinads' ); ?></label>
                    <div class="<?php echo esc_attr( Utils::set_class_name( 'price-field' ) ) ?>">
						<?php echo wp_kses_post( $cost_html ) ?>
                    </div>
                </div>

                <div class="field">
                    <label><?php esc_html_e( 'Sale price', 'chinads' ) ?></label>
                    <div class="vi-ui left labeled input">
                        <label for="amount" class="vi-ui label"><?php echo esc_html( $woocommerce_currency_symbol ) ?></label>
                        <input type="number" min="0" step="<?php echo esc_attr( $decimals ) ?>" value="<?php echo esc_attr( $sale_price ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][0][sale_price]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-sale-price' ) ) ?>">
                    </div>
                </div>

                <div class="field">
                    <label><?php esc_html_e( 'Regular price', 'chinads' ) ?></label>
                    <div class="vi-ui left labeled input">
                        <label for="amount" class="vi-ui label"><?php echo esc_html( $woocommerce_currency_symbol ) ?></label>
                        <input type="number" min="0" step="<?php echo esc_attr( $decimals ) ?>" value="<?php echo esc_attr( $regular_price ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][0][regular_price]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-regular-price' ) ) ?>">
                    </div>
                </div>
				<?php
				if ( $manage_stock ) {
					?>
                    <div class="field">
                        <label><?php esc_html_e( 'Inventory', 'chinads' ) ?></label>
                        <input type="number" min="0" step="<?php echo esc_attr( $decimals ) ?>" value="<?php echo esc_attr( $inventory ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][0][stock]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-inventory' ) ) ?>">
                    </div>
					<?php
				}
				?>
            </div>
        </div>
		<?php
	}

	public function load_variations_table() {
		check_ajax_referer( 'tbds_security' );
		$key        = isset( $_GET['product_index'] ) ? absint( $_GET['product_index'] ) : '';
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : '';
		if ( $key > - 1 && $product_id ) {
			$currency                    = 'CNY';
			$woocommerce_currency        = get_woocommerce_currency();
			$woocommerce_currency_symbol = get_woocommerce_currency_symbol();
			$manage_stock                = $this->settings->get_param( 'manage_stock' );
			$use_different_currency      = false;
			$variations                  = $this->get_product_variations( $product_id );
			$decimals                    = wc_get_price_decimals();

			if ( $decimals < 1 ) {
				$decimals = 1;
			} else {
				$decimals = pow( 10, ( - 1 * $decimals ) );
			}

			if ( strtolower( $woocommerce_currency ) != strtolower( $currency ) ) {
				$use_different_currency = true;
			}

			$attributes = $this->get_product_attributes( $product_id );
			$parent     = [];

			if ( is_array( $attributes ) && count( $attributes ) ) {
				foreach ( $attributes as $attribute_k => $attribute_v ) {
					$parent[ $attribute_k ] = $attribute_v['slug'];
				}
			}

			ob_start();
			$this->variation_html( $key, $parent, $attributes, $manage_stock, $variations, $use_different_currency, $currency, $product_id, $woocommerce_currency_symbol, $decimals, false );
			$return = ob_get_clean();
			wp_send_json( array( 'status' => 'success', 'data' => $return ) );
		} else {
			wp_send_json(
				array(
					'status' => 'error',
					'data'   => esc_html__( 'Missing required arguments', 'chinads' )
				)
			);
		}
	}

	public function variation_html( $key, $parent, $attributes, $manage_stock, $variations, $use_different_currency, $currency, $product_id, $woocommerce_currency_symbol, $decimals, $lazy_load = true, $coutry = '', $company = '' ) {
		?>
        <thead>
        <tr>
            <td width="1%"></td>
            <td class="<?php echo esc_attr( Utils::set_class_name( 'fix-width' ) ) ?>">
                <input type="checkbox" checked
                       class="<?php echo esc_attr( Utils::set_class_name( array( 'variations-bulk-enable', 'variations-bulk-enable-' . $key ) ) ) ?>">
            </td>
            <td class="<?php echo esc_attr( Utils::set_class_name( 'fix-width' ) ) ?>">
                <input type="checkbox" checked
                       class="<?php echo esc_attr( Utils::set_class_name( array(
					       'variations-bulk-select-image',
				       ) ) ) ?>">
            </td>
            <th class="<?php echo esc_attr( Utils::set_class_name( 'fix-width' ) ) ?>"><?php esc_html_e( 'Default variation', 'chinads' ) ?></th>
            <th><?php esc_html_e( 'Sku', 'chinads' ) ?></th>
			<?php
			if ( is_array( $parent ) && count( $parent ) ) {
				foreach ( $parent as $parent_k => $parent_v ) {
					?>
                    <th class="<?php echo esc_attr( Utils::set_class_name( 'attribute-filter-list-container' ) ) ?>">
						<?php
						$attribute_name = isset( $attributes[ $parent_k ]['name'] ) ? $attributes[ $parent_k ]['name'] : Utils::get_attribute_name_by_slug( $parent_v );
						echo esc_html( $attribute_name );
						$attribute_values = isset( $attributes[ $parent_k ]['values'] ) ? $attributes[ $parent_k ]['values'] : [];
						if ( count( $attribute_values ) ) {
							?>
                            <ul class="<?php echo esc_attr( Utils::set_class_name( 'attribute-filter-list' ) ) ?>"
                                data-attribute_slug="<?php echo esc_attr( $parent_v ) ?>">
								<?php
								foreach ( $attribute_values as $attribute_value ) {
									?>
                                    <li class="<?php echo esc_attr( Utils::set_class_name( 'attribute-filter-item' ) ) ?>"
                                        title="<?php echo esc_attr( $attribute_value ) ?>"
                                        data-attribute_slug="<?php echo esc_attr( $parent_v ) ?>"
                                        data-attribute_value="<?php echo esc_attr( trim( $attribute_value ) ) ?>"><?php echo esc_html( $attribute_value ) ?></li>
									<?php
								}
								?>
                            </ul>
							<?php
						}
						?>
                    </th>
					<?php
				}
			}

			?>
            <th>
				<?php esc_html_e( 'Cost', 'chinads' ) ?>
            </th>
            <th class="<?php echo esc_attr( Utils::set_class_name( 'sale-price-col' ) ) ?>">
				<?php esc_html_e( 'Sale price', 'chinads' ) ?>
                <div class="<?php echo esc_attr( Utils::set_class_name( 'set-price' ) ) ?>" data-set_price="sale_price">
					<?php esc_html_e( 'Set price', 'chinads' ) ?>
                </div>
            </th>
            <th class="<?php echo esc_attr( Utils::set_class_name( 'regular-price-col' ) ) ?>">
				<?php esc_html_e( 'Regular price', 'chinads' ) ?>
                <div class="<?php echo esc_attr( Utils::set_class_name( 'set-price' ) ) ?>" data-set_price="regular_price">
					<?php esc_html_e( 'Set price', 'chinads' ) ?>
                </div>
            </th>
			<?php
			if ( $manage_stock ) {
				?>
                <th class="<?php echo esc_attr( Utils::set_class_name( 'inventory-col' ) ) ?>"><?php esc_html_e( 'Inventory', 'chinads' ) ?></th>
				<?php
			}
			?>
        </tr>
        </thead>
        <tbody>
		<?php
		foreach ( $variations as $variation_key => $variation ) {
			$variation_image = $variation['image'] ?? '';
			$inventory       = intval( $variation['stock'] );

			$variation_sale_price    = $variation['sale_price'] ? ( $variation['sale_price'] ) : ( $variation['sale_price'] );
			$variation_regular_price = ( $variation['regular_price'] );
			$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
			$sale_price              = $this->process_price( $price, true );
			$regular_price           = $this->process_price( $price );

			$profit = $variation_sale_price ? ( $sale_price - $variation_sale_price ) : ( $regular_price - $variation_regular_price );

			$cost_html = wc_price( $price, array(
				'currency'     => $currency,
				'decimals'     => 2,
				'price_format' => '%1$s&nbsp;%2$s'
			) );

			$profit_html = wc_price( $profit, array(
				'currency'     => $currency,
				'decimals'     => 2,
				'price_format' => '%1$s&nbsp;%2$s'
			) );

			if ( $use_different_currency ) {
				$cost_html   .= '(' . wc_price( $this->process_exchange_price( $price ) ) . ')';
				$profit_html .= '(' . wc_price( $this->process_exchange_price( $profit ) ) . ')';
			}

			$sale_price    = $this->process_exchange_price( $sale_price );
			$regular_price = $this->process_exchange_price( $regular_price );
			$image_src     = $variation_image ? $variation_image : wc_placeholder_img_src();

			?>
            <tr class="<?php echo esc_attr( Utils::set_class_name( 'product-variation-row' ) ) ?>">
                <td><?php echo esc_html( $variation_key + 1 ) ?></td>
                <td>
                    <input type="checkbox" checked
                           class="<?php echo esc_attr( Utils::set_class_name( array(
						       'variation-enable',
						       'variation-enable-' . $key,
						       'variation-enable-' . $key . '-' . $variation_key
					       ) ) ) ?>">
                </td>
                <td>
                    <div class="<?php echo esc_attr( Utils::set_class_name( array( 'variation-image', 'selected-item' ) ) ) ?>">
                        <span class="<?php echo esc_attr( Utils::set_class_name( 'selected-item-icon-check' ) ) ?>"> </span>
                        <img style="width: 64px;height: 64px" data-image_src="<?php echo esc_url( $image_src ) ?>"
                             src="<?php echo esc_url( $lazy_load ? TBDS_CONST['img_url'] . 'loading.gif' : $image_src ) ?>"
                             class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-image' ) ) ?>">
                        <input type="hidden" value="<?php echo esc_attr( $variation_image ? $variation_image : '' ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][image]' ) ?>">
                    </div>
                </td>
                <td><input type="radio" value="<?php echo esc_attr( $variation['skuId'] ) ?>"
                           class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-default' ) ) ?>"
                           name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][default_variation]' ) ?>">
                </td>
                <td>
                    <div>
                        <input type="text" value="<?php echo esc_attr( $variation['skuId'] ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][sku]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-sku' ) ) ?>">
                        <input type="hidden" value="<?php echo esc_attr( $variation['skuId'] ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][skuId]' ) ?>">

                    </div>
                </td>
				<?php
				if ( is_array( $parent ) && count( $parent ) ) {
					foreach ( $parent as $parent_k => $parent_v ) {
						?>
                        <td>
                            <input type="text" readonly data-attribute_slug="<?php echo esc_attr( $parent_v ) ?>"
                                   data-attribute_value="<?php echo esc_attr( isset( $variation['attributes'][ $parent_v ] ) ? trim( $variation['attributes'][ $parent_v ] ) : '' ) ?>"
                                   name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][attributes][' . $parent_v . ']' ) ?>"
                                   class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-attribute' ) ) ?>"
                                   value="<?php echo esc_attr( isset( $variation['attributes'][ $parent_v ] ) ? $variation['attributes'][ $parent_v ] : '' ) ?>">
                        </td>
						<?php
					}
				}
				?>
                <td>
                    <div class="<?php echo esc_attr( Utils::set_class_name( 'price-field' ) ) ?>">
                        <span class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-cost' ) ) ?>">
                            <?php echo wp_kses_post( $cost_html ) ?>
                        </span>
                    </div>
                </td>
                <td>
                    <div class="vi-ui left labeled input">
                        <label for="amount" class="vi-ui label"><?php echo esc_html( $woocommerce_currency_symbol ) ?></label>
                        <input type="number" min="0" step="<?php echo esc_attr( $decimals ) ?>" value="<?php echo esc_attr( $sale_price ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][sale_price]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-sale-price' ) ) ?>">
                    </div>
                </td>
                <td>
                    <div class="vi-ui left labeled input">
                        <label for="amount" class="vi-ui label"><?php echo esc_html( $woocommerce_currency_symbol ) ?></label>
                        <input type="number" min="0" step="<?php echo esc_attr( $decimals ) ?>" value="<?php echo esc_attr( $regular_price ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][regular_price]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-regular-price' ) ) ?>">
                    </div>
                </td>
				<?php
				if ( $manage_stock ) {
					?>
                    <td>
                        <input type="number" min="0" step="<?php echo esc_attr( $decimals ) ?>" value="<?php echo esc_attr( $inventory ) ?>"
                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][variations][' . $variation_key . '][stock]' ) ?>"
                               class="<?php echo esc_attr( Utils::set_class_name( 'import-data-variation-inventory' ) ) ?>">
                    </td>
					<?php
				}
				?>
            </tr>
			<?php
		}
		?>
        </tbody>
		<?php
	}

	public function import() {
		check_ajax_referer( 'tbds_security' );
		Utils::set_time_limit();

		if ( ! isset( $_POST['form_data']['z_check_max_input_vars'] ) ) {
			/*z_check_max_input_vars is the last key of POST data. If it does not exist in $form_data after using parse_str(), some data may also be missing*/
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'PHP max_input_vars is too low, please increase it in php.ini', 'chinads' ),
			) );
		}

		$data     = isset( $_POST['form_data']['tbds_product'] ) ? wc_clean( wp_unslash( $_POST['form_data']['tbds_product'] ) ) : [];
		$selected = isset( $_POST['selected'] ) ? wc_clean( wp_unslash( $_POST['selected'] ) ) : [];
		$response = array(
			'status'         => 'error',
			'message'        => '',
			'woo_product_id' => '',
			'button_html'    => '',
		);

		if ( empty( $data ) ) {
			$response['message'] = esc_html__( 'Please select product to import', 'chinads' );
		} else {
			$product_data                = array_values( $data )[0];
			$product_draft_id            = array_keys( $data )[0];
			$product_data['description'] = isset( $_POST['form_data']['tbds_product'][ $product_draft_id ]['description'] ) ? wp_kses_post( wp_unslash( $_POST['form_data']['tbds_product'][ $product_draft_id ]['description'] ) ) : '';

			if ( ! count( $selected[ $product_draft_id ] ) ) {
				$response['message'] = esc_html__( 'Please select at least 1 variation to import this product.', 'chinads' );
				wp_send_json( $response );
			}

			if ( ! $product_draft_id || Utils::sku_exists( $product_data['sku'] ) ) {
				$response['message'] = esc_html__( 'Sku exists.', 'chinads' );
				wp_send_json( $response );
			}

			if ( Taobao_Post::get_post_id_by_taobao_id( Taobao_Post::get_post_meta( $product_draft_id, '_tbds_sku', true ), [ 'publish' ] ) ) {
				wp_send_json( array(
					'status'  => 'error',
					'message' => esc_html__( 'This product has already been imported', 'chinads' ),
				) );
			}

			$variations_attributes = [];
			$attributes            = $this->get_product_attributes( $product_draft_id );

			if ( isset( $product_data['variations'] ) ) {
				$variations = array_values( $product_data['variations'] );

				if ( ! empty( $variations ) ) {
					$var_default = isset( $product_data['default_variation'] ) ? $product_data['default_variation'] : '';

					foreach ( $variations as $variations_v ) {
						if ( $var_default === $variations_v['skuId'] ) {
							$product_data['variation_default'] = $variations_v['attributes'];
						}

						$variations_attribute = isset( $variations_v['attributes'] ) ? $variations_v['attributes'] : [];

						if ( is_array( $variations_attribute ) && count( $variations_attribute ) ) {
							foreach ( $variations_attribute as $variations_attribute_k => $variations_attribute_v ) {
								if ( ! isset( $variations_attributes[ $variations_attribute_k ] ) ) {
									$variations_attributes[ $variations_attribute_k ] = array( $variations_attribute_v );
								} elseif ( ! in_array( $variations_attribute_v, $variations_attributes[ $variations_attribute_k ] ) ) {
									$variations_attributes[ $variations_attribute_k ][] = $variations_attribute_v;
								}
							}
						}
					}

					if ( is_array( $attributes ) && count( $attributes ) ) {
						foreach ( $attributes as $attributes_k => $attributes_v ) {
							if ( ! empty( $variations_attributes[ $attributes_v['slug'] ] ) ) {
								$attributes[ $attributes_k ]['values'] = array_intersect( $attributes[ $attributes_k ]['values'], $variations_attributes[ $attributes_v['slug'] ] );
							}
						}
					}
				}
			} else {
				$variations    = $this->get_product_variations( $product_draft_id );
				$shipping_cost = 0;

				if ( $this->settings->get_param( 'shipping_cost_after_price_rules' ) ) {
					foreach ( $variations as $variations_k => $variations_v ) {
						$variation_sale_price    = ( $variations_v['sale_price'] );
						$variation_regular_price = ( $variations_v['regular_price'] );
						$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
						$sale_price              = $this->process_price( $price, true );
						if ( $sale_price ) {
							$sale_price += $shipping_cost;
						}
						$regular_price                                = $this->process_price( $price ) + $shipping_cost;
						$variations[ $variations_k ]['sale_price']    = $this->process_exchange_price( $sale_price );
						$variations[ $variations_k ]['regular_price'] = $this->process_exchange_price( $regular_price );
					}
				} else {
					foreach ( $variations as $variations_k => $variations_v ) {
						$variation_sale_price                         = $variations_v['sale_price'] ? ( ( $variations_v['sale_price'] ) + $shipping_cost ) : ( $variations_v['sale_price'] );
						$variation_regular_price                      = ( $variations_v['regular_price'] ) + $shipping_cost;
						$price                                        = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
						$variations[ $variations_k ]['sale_price']    = $this->process_exchange_price( $this->process_price( $price, true ) );
						$variations[ $variations_k ]['regular_price'] = $this->process_exchange_price( $this->process_price( $price ) );
					}
				}
			}

			if ( ! empty( $variations ) ) {
				$product_data['gallery'] = array_values( array_filter( $product_data['gallery'] ) );

				if ( $product_data['image'] ) {
					$product_image_key = array_search( $product_data['image'], $product_data['gallery'] );
					if ( $product_image_key !== false ) {
						unset( $product_data['gallery'][ $product_image_key ] );
						$product_data['gallery'] = array_values( $product_data['gallery'] );
					}
				}

				$variation_images                    = Taobao_Post::get_post_meta( $product_draft_id, '_tbds_variation_images', true );
				$product_data['attributes']          = $attributes;
				$product_data['variation_images']    = $variation_images;
				$product_data['variations']          = $variations;
				$product_data['parent_id']           = $product_draft_id;
				$product_data['taobao_product_id']   = Taobao_Post::get_post_meta( $product_draft_id, '_tbds_sku', true );
				$product_data['taobao_product_host'] = Taobao_Post::get_post_meta( $product_draft_id, '_tbds_taobao_host', true );
				$woo_product_id                      = $this->import_product( $product_data );

				if ( ! is_wp_error( $woo_product_id ) ) {
					$response['status']         = 'success';
					$response['message']        = esc_html__( 'Import successfully', 'chinads' );
					$response['woo_product_id'] = $woo_product_id;

					$response['button_html'] = Utils::get_button_view_edit_html( $woo_product_id );
				} else {
					$response['message'] = $woo_product_id->get_error_messages();
				}
			} else {
				$response['message'] = esc_html__( 'Please select at least 1 variation to import this product.', 'chinads' );
			}
		}
		wp_send_json( $response );
	}

	public function import_product( $product_data ) {
		Utils::set_time_limit();

		$taobao_product_host        = $product_data['taobao_product_host'];
		$taobao_product_id          = $product_data['taobao_product_id'];
		$parent_id                  = $product_data['parent_id'];
		$image                      = $product_data['image'];
		$categories                 = $product_data['categories'] ?? [];
		$shipping_class             = $product_data['shipping_class'] ?? '';
		$title                      = $product_data['title'];
		$sku                        = $product_data['sku'];
		$status                     = $product_data['status'];
		$tags                       = $product_data['tags'] ?? [];
		$description                = $product_data['description'];
		$variations                 = $product_data['variations'];
		$gallery                    = $product_data['gallery'];
		$attributes                 = $product_data['attributes'];
		$catalog_visibility         = $product_data['catalog_visibility'];
		$default_attr               = $product_data['variation_default'] ?? [];
		$disable_background_process = $this->settings->get_param( 'disable_background_process' );

		if ( is_array( $attributes ) && count( $attributes ) && ( count( $variations ) > 1 || ! $this->settings->get_param( 'simple_if_one_variation' ) ) ) {
			$attr_data = $this->create_product_attributes( $attributes, $default_attr );

			/*Create data for product*/
			$data = array( // Set up the basic post data to insert for our product
				'post_excerpt' => '',
				'post_content' => $description,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_type'    => 'product',
				'meta_input'   => array(
					'_sku'        => wc_product_generate_unique_sku( 0, $sku ),
					'_visibility' => 'visible',
				)
			);

			$product_id = wp_insert_post( $data ); // Insert the post returning the new post id

			if ( ! is_wp_error( $product_id ) ) {
				if ( $parent_id ) {
					$update_data = array(
						'ID'          => $parent_id,
						'post_status' => 'publish',
						'post_author' => get_current_user_id()
					);
					Taobao_Post::update_post( $update_data );
					Taobao_Post::update_post_meta( $parent_id, '_tbds_woo_id', $product_id );
				}

				update_post_meta( $product_id, '_tbds_taobao_product_id', $taobao_product_id );
				update_post_meta( $product_id, '_tbds_taobao_product_host', $taobao_product_host );

				// Set it to a variable product type
				wp_set_object_terms( $product_id, 'variable', 'product_type' );
				if ( ! empty( $attr_data ) ) {
					$product_obj = wc_get_product( $product_id );
					if ( $product_obj ) {
						$product_obj->set_attributes( $attr_data );
						if ( $default_attr ) {
							$product_obj->set_default_attributes( $default_attr );
						}
						$product_obj->save();
						/*Use this twice in case other plugin override product type after product is saved*/
						wp_set_object_terms( $product_id, 'variable', 'product_type' );
					}
				}

				/*download image gallery*/
				$dispatch = false;

				if ( isset( $product_data['old_product_image'] ) ) {
					if ( $product_data['old_product_image'] ) {
						update_post_meta( $product_id, '_thumbnail_id', $product_data['old_product_image'] );
					}
					if ( isset( $product_data['old_product_gallery'] ) && $product_data['old_product_gallery'] ) {
						update_post_meta( $product_id, '_product_image_gallery', $product_data['old_product_gallery'] );
					}
				} else {
					if ( $image ) {
						$thumb_id = Utils::download_image( $image_id, $image, $product_id );
						if ( ! is_wp_error( $thumb_id ) ) {
							update_post_meta( $product_id, '_thumbnail_id', $thumb_id );
						}
					}

					$this->process_gallery_images( $gallery, $disable_background_process, $product_id, $parent_id, $dispatch );
				}

				$this->process_description_images( $description, $disable_background_process, $product_id, $parent_id, $dispatch );

				/*Set product tag*/
				if ( ! empty( $tags ) && is_array( $tags ) ) {
					wp_set_post_terms( $product_id, $tags, 'product_tag', true );
				}

				/*Set product categories*/
				if ( ! empty( $categories ) && is_array( $categories ) ) {
					wp_set_post_terms( $product_id, $categories, 'product_cat', true );
				}

				/*Set product shipping class*/
				if ( $shipping_class && get_term_by( 'id', $shipping_class, 'product_shipping_class' ) ) {
					wp_set_post_terms( $product_id, array( intval( $shipping_class ) ), 'product_shipping_class', false );
				}

				/*Create product variation*/
				$this->import_product_variation( $product_id, $product_data, $dispatch, $disable_background_process );

				Utils::set_catalog_visibility( $product_id, $catalog_visibility );
			}
		} else {
			/*Create data for product*/
			$sale_price    = isset( $variations[0]['sale_price'] ) ? floatval( $variations[0]['sale_price'] ) : '';
			$regular_price = isset( $variations[0]['regular_price'] ) ? floatval( $variations[0]['regular_price'] ) : '';
			$data          = array( // Set up the basic post data to insert for our product
				'post_excerpt' => '',
				'post_content' => $description,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_type'    => 'product',
				'meta_input'   => array(
					'_sku'           => wc_product_generate_unique_sku( 0, $sku ),
					'_visibility'    => 'visible',
					'_regular_price' => $regular_price,
					'_price'         => $regular_price,
					'_manage_stock'  => $this->settings->get_param( 'manage_stock' ) ? 'yes' : 'no',
					'_stock_status'  => 'instock',
				)
			);

			if ( ! empty( $variations[0]['stock'] ) && $data['meta_input']['_manage_stock'] === 'yes' ) {
				$data['meta_input']['_stock'] = absint( $variations[0]['stock'] );
			}

			if ( $sale_price ) {
				$data['meta_input']['_sale_price'] = $sale_price;
				$data['meta_input']['_price']      = $sale_price;
			}

			$product_id = wp_insert_post( $data ); // Insert the post returning the new post id

			if ( ! is_wp_error( $product_id ) ) {
				if ( $parent_id ) {
					$update_data = array(
						'ID'          => $parent_id,
						'post_status' => 'publish',
						'post_author' => get_current_user_id()
					);
					Taobao_Post::update_post( $update_data );
					Taobao_Post::update_post_meta( $parent_id, '_tbds_woo_id', $product_id );
				}
				// Set it to a variable product type
				wp_set_object_terms( $product_id, 'simple', 'product_type' );
				/*download image gallery*/
				$dispatch = false;

				if ( isset( $product_data['old_product_image'] ) ) {
					if ( $product_data['old_product_image'] ) {
						update_post_meta( $product_id, '_thumbnail_id', $product_data['old_product_image'] );
					}
					if ( isset( $product_data['old_product_gallery'] ) && $product_data['old_product_gallery'] ) {
						update_post_meta( $product_id, '_product_image_gallery', $product_data['old_product_gallery'] );
					}
				} else {
					if ( $image ) {
						$thumb_id = Utils::download_image( $image_id, $image, $product_id );
						if ( ! is_wp_error( $thumb_id ) ) {
							update_post_meta( $product_id, '_thumbnail_id', $thumb_id );
						}
					}
					$this->process_gallery_images( $gallery, $disable_background_process, $product_id, $parent_id, $dispatch );
				}

				$this->process_description_images( $description, $disable_background_process, $product_id, $parent_id, $dispatch );

				if ( $dispatch ) {
					$this->process_image->save()->dispatch();
				}

				/*Set product tag*/
				if ( is_array( $tags ) && count( $tags ) ) {
					wp_set_post_terms( $product_id, $tags, 'product_tag', true );
				}

				/*Set product categories*/
				if ( is_array( $categories ) && count( $categories ) ) {
					wp_set_post_terms( $product_id, $categories, 'product_cat', true );
				}

				/*Set product shipping class*/
				if ( $shipping_class && get_term_by( 'id', $shipping_class, 'product_shipping_class' ) ) {
					wp_set_post_terms( $product_id, array( intval( $shipping_class ) ), 'product_shipping_class', false );
				}

				update_post_meta( $product_id, '_tbds_taobao_product_id', $taobao_product_id );
				update_post_meta( $product_id, '_tbds_taobao_product_host', $taobao_product_host );

				if ( ! empty( $variations[0]['skuId'] ) ) {
					update_post_meta( $product_id, '_tbds_taobao_variation_id', $variations[0]['skuId'] );
				}


				Utils::set_catalog_visibility( $product_id, $catalog_visibility );

				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product->save();
				}
			}
		}

		return $product_id;
	}

	public function create_product_attributes( $attributes, &$default_attr ) {
		global $wp_taxonomies;
		$position  = 0;
		$attr_data = [];
		if ( $this->settings->get_param( 'use_global_attributes' ) ) {
			foreach ( $attributes as $key => $attr ) {
				$attribute_name = isset( $attr['name'] ) ? $attr['name'] : Utils::get_attribute_name_by_slug( $attr['slug'] );
				$attribute_id   = wc_attribute_taxonomy_id_by_name( $attribute_name );
				if ( ! $attribute_id ) {
					$attribute_id = wc_create_attribute( array(
						'name'         => $attribute_name,
						'slug'         => $attr['slug'],
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					) );
				}
				if ( $attribute_id && ! is_wp_error( $attribute_id ) ) {
					$attribute_obj     = wc_get_attribute( $attribute_id );
					$attribute_options = [];
					if ( ! empty( $attribute_obj ) ) {
						$taxonomy = $attribute_obj->slug; // phpcs:ignore
						if ( isset( $default_attr[ $attr['slug'] ] ) ) {
							$default_attr[ $taxonomy ] = $default_attr[ $attr['slug'] ];
							unset( $default_attr[ $attr['slug'] ] );
						}
						/*Update global $wp_taxonomies for latter insert attribute values*/
						$wp_taxonomies[ $taxonomy ] = new \WP_Taxonomy( $taxonomy, 'product' );
						if ( count( $attr['values'] ) ) {
							foreach ( $attr['values'] as $attr_value ) {
								$attr_value  = strval( wc_clean( $attr_value ) );
								$insert_term = wp_insert_term( $attr_value, $taxonomy );
								if ( ! is_wp_error( $insert_term ) ) {
									$attribute_options[] = $insert_term['term_id'];
									if ( isset( $default_attr[ $taxonomy ] ) ) {
										$term_exists = get_term_by( 'id', $insert_term['term_id'], $taxonomy );
										if ( $term_exists ) {
											$default_attr[ $taxonomy ] = $term_exists->slug;
										}
									}
								} elseif ( isset( $insert_term->error_data ) && isset( $insert_term->error_data['term_exists'] ) ) {
									$attribute_options[] = $insert_term->error_data['term_exists'];
									if ( isset( $default_attr[ $taxonomy ] ) ) {
										$term_exists = get_term_by( 'id', $insert_term->error_data['term_exists'], $taxonomy );
										if ( $term_exists ) {
											$default_attr[ $taxonomy ] = $term_exists->slug;
										}
									}
								}
							}
						}
					}
					$attribute_object = new \WC_Product_Attribute();
					$attribute_object->set_id( $attribute_id );
					$attribute_object->set_name( wc_attribute_taxonomy_name_by_id( $attribute_id ) );
					if ( count( $attribute_options ) ) {
						$attribute_object->set_options( $attribute_options );
					} else {
						$attribute_object->set_options( $attr['values'] );
					}
					$attribute_object->set_position( isset( $attr['position'] ) ? $attr['position'] : $position );
					$attribute_object->set_visible( $this->settings->get_param( 'variation_visible' ) ? 1 : '' );
					$attribute_object->set_variation( 1 );
					$attr_data[] = $attribute_object;
				}
				$position ++;
			}
		} else {
			foreach ( $attributes as $key => $attr ) {
				$attribute_name   = isset( $attr['name'] ) ? $attr['name'] : Utils::get_attribute_name_by_slug( $attr['slug'] );
				$attribute_object = new \WC_Product_Attribute();
				$attribute_object->set_name( $attribute_name );
				$attribute_object->set_options( $attr['values'] );
				$attribute_object->set_position( isset( $attr['position'] ) ? $attr['position'] : $position );
				$attribute_object->set_visible( $this->settings->get_param( 'variation_visible' ) ? 1 : '' );
				$attribute_object->set_variation( 1 );
				$attr_data[] = $attribute_object;
				$position ++;
			}
		}

		return $attr_data;
	}

	public function get_product_attributes( $product_id ) {
		$attributes = Taobao_Post::get_post_meta( $product_id, '_tbds_attributes', true );
		if ( is_array( $attributes ) && count( $attributes ) ) {
			foreach ( $attributes as $key => $value ) {
				if ( ! empty( $value['slug_edited'] ) ) {
					$attributes[ $key ]['slug'] = $value['slug_edited'];
					unset( $attributes[ $key ]['slug_edited'] );
				}
				if ( ! empty( $value['name_edited'] ) ) {
					$attributes[ $key ]['name'] = $value['name_edited'];
					unset( $attributes[ $key ]['name_edited'] );
				}
				if ( ! empty( $value['values_edited'] ) ) {
					$attributes[ $key ]['values'] = $value['values_edited'];
					unset( $attributes[ $key ]['values_edited'] );
				}
			}
		}

		return $attributes;
	}

	public function get_product_variations( $product_id ) {
		$variations =Taobao_Post::get_post_meta( $product_id, '_tbds_variations', true );
		if ( is_array( $variations ) && count( $variations ) ) {
			foreach ( $variations as $key => $value ) {
				if ( ! empty( $value['attributes_edited'] ) ) {
					$variations[ $key ]['attributes'] = $value['attributes_edited'];
					unset( $variations[ $key ]['attributes_edited'] );
				}
			}
		}

		return $variations;
	}


	public function add_order_note( $order_id, $note ) {
		$commentdata = apply_filters(
			'woocommerce_new_order_note_data',
			array(
				'comment_post_ID'      => $order_id,
				'comment_author'       => '',
				'comment_author_email' => __( 'WooCommerce', 'woocommerce' ),
				'comment_author_url'   => '',
				'comment_content'      => $note,
				'comment_agent'        => 'WooCommerce',
				'comment_type'         => 'order_note',
				'comment_parent'       => 0,
				'comment_approved'     => 1,
			),
			array(
				'order_id'         => $order_id,
				'is_customer_note' => 0,
			)
		);
		wp_insert_comment( $commentdata );
	}

	public function process_gallery_images( $gallery, $disable_background_process, $product_id, $parent_id, &$dispatch ) {
		if ( is_array( $gallery ) && count( $gallery ) ) {
			if ( $disable_background_process ) {
				foreach ( $gallery as $image_url ) {
					$image_data = array(
						'woo_product_id' => $product_id,
						'parent_id'      => $parent_id,
						'src'            => $image_url,
						'product_ids'    => [],
						'set_gallery'    => 1,
					);
					Error_Images_Query::insert( $product_id, implode( ',', $image_data['product_ids'] ), $image_data['src'], intval( $image_data['set_gallery'] ) );
				}
			} else {
				$dispatch = true;
				foreach ( $gallery as $image_url ) {
					$image_data = array(
						'woo_product_id' => $product_id,
						'parent_id'      => $parent_id,
						'src'            => $image_url,
						'product_ids'    => [],
						'set_gallery'    => 1,
					);
					$this->process_image->push_to_queue( $image_data );
				}
			}
		}
	}

	public function process_description_images( $description, $disable_background_process, $product_id, $parent_id, &$dispatch ) {
		if ( $description && ! $this->settings->get_param( 'use_external_image' ) && $this->settings->get_param( 'download_description_images' ) ) {
			preg_match_all( '/src="([\s\S]*?)"/im', $description, $matches );

			if ( isset( $matches[1] ) && is_array( $matches[1] ) && count( $matches[1] ) ) {
				$description_images = array_unique( $matches[1] );

				if ( $disable_background_process ) {
					foreach ( $description_images as $description_image ) {
						Error_Images_Query::insert( $product_id, '', $description_image, 2 );
					}
				} else {
					foreach ( $description_images as $description_image ) {
						$images_data = array(
							'woo_product_id' => $product_id,
							'parent_id'      => $parent_id,
							'src'            => $description_image,
							'product_ids'    => [],
							'set_gallery'    => 2,
						);
						$this->process_image->push_to_queue( $images_data );
					}
					$dispatch = true;
				}
			}
		}
	}

	public function import_product_variation( $product_id, $product_data, $dispatch, $disable_background_process ) {
		$product = wc_get_product( $product_id );
		if ( $product ) {
			if ( is_array( $product_data['variations'] ) && count( $product_data['variations'] ) ) {
				$found_items   = isset( $product_data['found_items'] ) ? $product_data['found_items'] : [];
				$replace_items = isset( $product_data['replace_items'] ) ? $product_data['replace_items'] : [];
				$replace_title = isset( $product_data['replace_title'] ) ? $product_data['replace_title'] : '';
				$variation_ids = [];

				if ( ! empty( $product_data['variation_images'] ) && is_array( $product_data['variation_images'] ) ) {
					foreach ( $product_data['variation_images'] as $key => $val ) {
						$variation_ids[ $key ] = [];
					}
				}

				$use_global_attributes = $this->settings->get_param( 'use_global_attributes' );
				$manage_stock          = $this->settings->get_param( 'manage_stock' ) ? 'yes' : 'no';

				foreach ( $product_data['variations'] as $product_variation ) {
					if ( ! empty( $product_variation['variation_id'] ) ) {
						$variation_id = $product_variation['variation_id'];
					} else {
						$stock_quantity = isset( $product_variation['stock'] ) ? absint( $product_variation['stock'] ) : 0;
						$variation      = new \WC_Product_Variation();
						$variation->set_parent_id( $product_id );
						$attributes = [];

						if ( $use_global_attributes ) {
							foreach ( $product_variation['attributes'] as $option_k => $attr ) {
								$attribute_id  = wc_attribute_taxonomy_id_by_name( $option_k );
								$attribute_obj = wc_get_attribute( $attribute_id );
								if ( $attribute_obj ) {
									$attribute_value = $this->get_term_by_name( $attr, $attribute_obj->slug );
									if ( $attribute_value ) {
										$attributes[ strtolower( urlencode( $attribute_obj->slug ) ) ] = $attribute_value->slug;
									}
								}
							}
						} else {
							foreach ( $product_variation['attributes'] as $option_k => $attr ) {
								$attributes[ strtolower( urlencode( $option_k ) ) ] = $attr;
							}
						}

						$variation->set_attributes( $attributes );

						/*Set metabox for variation . Check field name at woocommerce/includes/class-wc-ajax.php*/
						$fields = array(
							'sku'            => wc_product_generate_unique_sku( 0, $product_variation['sku'] ),
							'regular_price'  => $product_variation['regular_price'],
							'price'          => $product_variation['regular_price'],
							'manage_stock'   => $manage_stock,
							'stock_status'   => 'instock',
							'stock_quantity' => $stock_quantity,
						);

						if ( isset( $product_variation['sale_price'] ) && $product_variation['sale_price'] && $product_variation['sale_price'] < $product_variation['regular_price'] ) {
							$fields['sale_price'] = $product_variation['sale_price'];
							$fields['price']      = $product_variation['sale_price'];
						}

						foreach ( $fields as $field => $value ) {
							$variation->{"set_$field"}( wc_clean( $value ) );
						}

						do_action( 'product_variation_linked', $variation->save() );

						$variation_id = $variation->get_id();
						$replaces     = array_keys( $replace_items, $product_variation['skuId'] ?? '' );

						if ( count( $replaces ) ) {
							foreach ( $replaces as $old_variation_id ) {
								$order_item_data = isset( $found_items[ $old_variation_id ] ) ? $found_items[ $old_variation_id ] : [];

								if ( count( $order_item_data ) ) {
									foreach ( $order_item_data as $order_item_data_k => $order_item_data_v ) {
										$order_id      = $order_item_data_v['order_id'];
										$order_item_id = $order_item_data_v['order_item_id'];

										if ( 1 == $replace_title ) {
											wc_update_order_item( $order_item_id, array( 'order_item_name' => $replace_title ) );
										}

										if ( $order_item_data_v['meta_key'] === '_variation_id' ) {
											$old_variation = wc_get_product( $old_variation_id );
											if ( $old_variation ) {
												$_product_id = wc_get_order_item_meta( $order_item_id, '_product_id', true );

												$note = sprintf( "%s #%s %s #%s. %s #%s %s #%s",
													esc_html__( 'Product', 'chinads' ),
													esc_html( $_product_id ),
													esc_html__( 'is replaced with product', 'chinads' ),
													esc_html( $product_id ),
													esc_html__( 'Variation', 'chinads' ),
													esc_html( $old_variation_id ),
													esc_html__( 'is replaced with variation', 'chinads' ),
													esc_html( $variation_id )
												);

												$this->add_order_note( $order_id, $note );
												$old_attributes = $old_variation->get_attributes();
												if ( count( $old_attributes ) ) {
													foreach ( $old_attributes as $old_attribute_k => $old_attribute_v ) {
														wc_delete_order_item_meta( $order_item_id, $old_attribute_k );
													}
												}
											}

										} else {
											$note = sprintf( "%s #%s %s #%s", esc_html__( 'Product', 'chinads' ),
												esc_html( $old_variation_id ), esc_html__( 'is replaced with product', 'chinads' ), esc_html( $product_id ) );
											$this->add_order_note( $order_id, $note );
											foreach ( $product_variation['attributes'] as $new_attribute_k => $new_attribute_v ) {
												wc_update_order_item_meta( $order_item_id, $new_attribute_k, $new_attribute_v );
											}
										}

										foreach ( $product_variation['attributes'] as $new_attribute_k => $new_attribute_v ) {
											wc_update_order_item_meta( $order_item_id, $new_attribute_k, $new_attribute_v );
										}

										wc_update_order_item_meta( $order_item_id, '_product_id', $product_id );
										wc_update_order_item_meta( $order_item_id, '_variation_id', $variation_id );
									}
								}
							}
						}

						update_post_meta( $variation_id, '_tbds_taobao_variation_id', $product_variation['sku'] ?? '' );

					}

					if ( $product_variation['image'] ??'') {
						$pos = array_search( $product_variation['image'], (array) $product_data['variation_images'] );
						if ( $pos !== false ) {
							$variation_ids[ $pos ][] = $variation_id;
						}
					}
				}

				if ( count( $variation_ids ) ) {
					if ( $disable_background_process ) {
						foreach ( $variation_ids as $key => $values ) {
							if ( count( $values ) && ! empty( $product_data['variation_images'][ $key ] ) ) {
								$image_data = array(
									'woo_product_id' => $product_id,
									'parent_id'      => '',
									'src'            => $product_data['variation_images'][ $key ],
									'product_ids'    => $values,
									'set_gallery'    => 0,
								);
								Error_Images_Query::insert( $product_id, implode( ',', $image_data['product_ids'] ), $image_data['src'], intval( $image_data['set_gallery'] ) );
							}
						}
					} else {
						foreach ( $variation_ids as $key => $values ) {
							if ( count( $values ) && ! empty( $product_data['variation_images'][ $key ] ) ) {
								$dispatch   = true;
								$image_data = array(
									'woo_product_id' => $product_id,
									'parent_id'      => '',
									'src'            => $product_data['variation_images'][ $key ],
									'product_ids'    => $values,
									'set_gallery'    => 0,
								);
								$this->process_image->push_to_queue( $image_data );
							}
						}
					}
				}
			}

			$data_store = $product->get_data_store();
			$data_store->sort_all_product_variations( $product->get_id() );
		}

		if ( $dispatch ) {
			$this->process_image->save()->dispatch();
		}
	}

	public function get_term_by_name( $value, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
		// 'term_taxonomy_id' lookups don't require taxonomy checks.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		// No need to perform a query for empty 'slug' or 'name'.
		$value = (string) $value;

		if ( 0 === strlen( $value ) ) {
			return false;
		}

		$args = array(
			'get'                    => 'all',
			'name'                   => $value,
			'number'                 => 0,
			'taxonomy'               => $taxonomy,
			'update_term_meta_cache' => false,
			'orderby'                => 'none',
			'suppress_filter'        => true,
		);

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return false;
		}
        $check_slug = sanitize_title( $value ) ;
		if ( count( $terms ) > 1 ) {
			foreach ( $terms as $term ) {
                if ($term->slug == $check_slug){
	                return get_term( $term, $taxonomy, $output, $filter );
                }
				if ( $term->name === $value ) {
					return get_term( $term, $taxonomy, $output, $filter );
				}
			}
		}
		$term = array_shift( $terms );

		return get_term( $term, $taxonomy, $output, $filter );
	}

	public function background_process() {

		if ( ! class_exists( 'WP_Async_Request' ) ) {
			include_once TBDS_CONST['plugin_dir'] . 'admin/background-process/wp-async-request.php';
		}

		if ( ! class_exists( 'WP_Background_Process' ) ) {
			include_once TBDS_CONST['plugin_dir'] . 'admin/background-process/wp-background-process.php';
		}

		$this->process_image = Download_Images::instance();

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( $_REQUEST['_wpnonce'] ) : '';

		if ( wp_verify_nonce( $nonce ) ) {

			if ( ! empty( $_REQUEST['tbds_cancel_download_product_image'] ) ) {
				$this->process_image->kill_process();
				wp_safe_redirect( @remove_query_arg( array( 'tbds_cancel_download_product_image', '_wpnonce' ) ) );
				exit;
			}

			if ( ! empty( $_REQUEST['tbds_run_download_product_image'] ) ) {
				if ( ! $this->process_image->is_process_running() && ! $this->process_image->is_queue_empty() ) {
					$this->process_image->dispatch();
				}
				wp_safe_redirect( @remove_query_arg( array( 'tbds_run_download_product_image', '_wpnonce' ) ) );
				exit;
			}
		}
	}

	public function remove() {
		check_ajax_referer( 'tbds_security' );
		Utils::set_time_limit();
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : '';
		if ( $product_id ) {
			if ( Taobao_Post::delete_post( $product_id, true ) ) {
				wp_send_json( array(
					'status'  => 'success',
					'message' => esc_html__( 'Removed', 'chinads' ),
				) );
			} else {
				wp_send_json( array(
					'status'  => 'error',
					'message' => esc_html__( 'Error', 'chinads' ),
				) );
			}
		} else {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Not found', 'chinads' ),
			) );
		}
	}

	public function override() {
		check_ajax_referer( 'tbds_security' );
		Utils::set_time_limit();

		if ( ! isset( $_POST['form_data']['z_check_max_input_vars'] ) ) {
			/*z_check_max_input_vars is the last key of POST data. If it does not exist in $form_data after using parse_str(), some data may also be missing*/
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'PHP max_input_vars is too low, please increase it in php.ini', 'chinads' ),
			) );
		}

		$selected                = isset( $_POST['selected'] ) ? wc_clean( wp_unslash( $_POST['selected'] ) ) : [];
		$override_product_id     = isset( $_POST['override_product_id'] ) ? absint( $_POST['override_product_id'] ) : '';
		$override_woo_id         = isset( $_POST['override_woo_id'] ) ? absint( $_POST['override_woo_id'] ) : '';
		$override_options        = array(
			'override_title'       => isset( $_POST['override_title'] ) ? sanitize_text_field( wp_unslash( $_POST['override_title'] ) ) : '',
			'override_images'      => isset( $_POST['override_images'] ) ? sanitize_text_field( wp_unslash( $_POST['override_images'] ) ) : '',
			'override_description' => isset( $_POST['override_description'] ) ? sanitize_text_field( wp_unslash( $_POST['override_description'] ) ) : '',
		);
		$override_hide           = isset( $_POST['override_hide'] ) ? sanitize_text_field( wp_unslash( $_POST['override_hide'] ) ) : '';
		$override_keep_product   = isset( $_POST['override_keep_product'] ) ? sanitize_text_field( wp_unslash( $_POST['override_keep_product'] ) ) : '';
		$override_find_in_orders = isset( $_POST['override_find_in_orders'] ) ? sanitize_text_field( wp_unslash( $_POST['override_find_in_orders'] ) ) : '';

		if ( $override_hide ) {
			$params = $this->settings->get_params();
			foreach ( $override_options as $override_option_k => $override_option_v ) {
				$params[ $override_option_k ] = $override_option_v;
			}
			$params['override_hide']           = $override_hide;
			$params['override_keep_product']   = $override_keep_product;
			$params['override_find_in_orders'] = $override_find_in_orders;
			update_option( 'tbds_params', $params );

		} elseif ( $this->settings->get_param( 'override_hide' ) ) {
			foreach ( $override_options as $override_option_k => $override_option_v ) {
				$override_options[ $override_option_k ] = $this->settings->get_param( $override_option_k );
			}
		}

		if ( ! $override_product_id && ! $override_woo_id ) {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Product is deleted from your store', 'chinads' ),
			) );
		}

		$data                        = isset( $_POST['form_data']['tbds_product'] ) ? wc_clean( wp_unslash( $_POST['form_data']['tbds_product'] ) ) : [];
		$product_draft_id            = array_keys( $data )[0];
		$product_data                = array_values( $data )[0];
		$product_data['description'] = isset( $_POST['form_data']['tbds_product'][ $product_draft_id ]['description'] ) ? wp_kses_post( wp_unslash( $_POST['form_data']['tbds_product'][ $product_draft_id ]['description'] ) ) : '';

		$check_orders  = isset( $_POST['check_orders'] ) ? sanitize_text_field( wp_unslash( $_POST['check_orders'] ) ) : '';
		$found_items   = isset( $_POST['found_items'] ) ? wc_clean( wp_unslash( $_POST['found_items'] ) ) : [];
		$replace_items = isset( $_POST['replace_items'] ) ? wc_clean( wp_unslash( $_POST['replace_items'] ) ) : [];

		if ( $override_product_id ) {
			$woo_product_id = Taobao_Post::get_post_meta( $override_product_id, '_tbds_woo_id', true );
		} else {
			$woo_product_id      = $override_woo_id;
			$override_product_id = Taobao_Post::get_post_id_by_woo_id( $woo_product_id, false, false, [ 'publish', 'override' ] );
		}

		$attributes = $this->get_product_attributes( $product_draft_id );

		if ( ! count( $selected[ $product_draft_id ] ) ) {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Please select at least 1 variation to import this product.', 'chinads' ),
			) );
		}

		if ( ! $product_draft_id ) {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Invalid data', 'chinads' ),
			) );
		}

		if ( ! $this->settings->get_param( 'auto_generate_unique_sku' ) && Utils::sku_exists( $product_data['sku'] ) && $product_data['sku'] != get_post_meta( $woo_product_id, '_sku', true ) ) {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Sku exists.', 'chinads' ),
			) );
		}

		if ( Taobao_Post::get_post_id_by_taobao_id( Taobao_Post::get_post_meta( $product_draft_id, '_tbds_sku', true ), array( 'publish' ) ) ) {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'This product has already been imported', 'chinads' ),
			) );
		}

		if ( ! $override_product_id || Taobao_Post::get_post_meta( $override_product_id, '_tbds_sku', true ) == Taobao_Post::get_post_meta( $product_draft_id, '_tbds_sku', true ) ) {
			$override_keep_product = '1';
		}

		$woo_product = wc_get_product( $woo_product_id );
		if ( $woo_product ) {
			if ( 1 != $check_orders && ( $override_find_in_orders == 1 || $override_keep_product == 1 ) ) {

				$is_simple = false;

				if ( ! is_array( $attributes ) || ! count( $attributes ) ||
				     ( isset( $product_data['variations'] ) && count( $selected[ $product_draft_id ] ) === 1 && $this->settings->get_param( 'simple_if_one_variation' ) ) ) {
					$is_simple = true;
				}

				if ( $is_simple ) {
					$variations = array( array_values( $product_data['variations'] )[0] );
				} else {
					if ( isset( $product_data['variations'] ) ) {
						$variations = array_values( $product_data['variations'] );
					} else {
						$variations = $this->get_product_variations( $product_draft_id );
					}
				}

				$replace_order_html = '';

				if ( $woo_product->is_type( 'variable' ) && ! $is_simple ) {
					$woo_product_children = $woo_product->get_children();
					if ( ! empty( $woo_product_children ) ) {
						foreach ( $woo_product_children as $woo_product_child ) {
							$found_item = $this->query_order_item_meta( [ 'order_item_type' => 'line_item' ], [ 'meta_key' => '_variation_id', 'meta_value' => $woo_product_child ] );//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

							$this->skip_item_with_taobao_order_id( $found_item );

							if ( $override_keep_product || count( $found_item ) ) {
								$found_items[ $woo_product_child ] = $found_item;

								$replace_order_html .= $this->get_override_variation_html( $woo_product, $woo_product_child, $variations, count( $found_item ), $is_simple );
							}
						}
					}
				} else {
					$found_item = $this->query_order_item_meta( array( 'order_item_type' => 'line_item' ), array( 'meta_key'   => '_product_id', 'meta_value' => $woo_product_id, ) );//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

					$this->skip_item_with_taobao_order_id( $found_item );
					if ( count( $found_item ) ) {
						$found_items[ $woo_product_id ] = $found_item;

						$replace_order_html = $this->get_override_simple_html( $woo_product, $woo_product_id, $variations, count( $found_item ), $is_simple );
					}
				}

				if ( count( $found_items ) ) {
					$message = $override_keep_product ? '<div class="vi-ui message warning">'
					                                    . esc_html__( 'By selecting a replacement, a new variation will be created by modifying the respective overridden variation. Overridden variations with no replacement selected will be deleted', 'chinads' )
					                                    . '</div>' : '';

					wp_send_json( array(
						'status'             => 'checked',
						'message'            => '',
						'found_items'        => $found_items,
						'replace_order_html' => $message . '<table class="vi-ui celled table"><thead><tr><th>'
						                        . esc_html__( 'Overridden items', 'chinads' )
						                        . '</th><th width="1%">'
						                        . esc_html__( 'Found in unfulfilled orders', 'chinads' )
						                        . '</th><th>' . esc_html__( 'Replacement', 'chinads' )
						                        . '</th></tr></thead><tbody>' . $replace_order_html . '</tbody></table>',
					) );
				}
			}
		} else {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Overridden product does not exists', 'chinads' ),
			) );
		}

		$variations_attributes = [];
		if ( isset( $product_data['variations'] ) ) {
			$variations = array_values( $product_data['variations'] );
			if ( count( $variations ) > 1 ) {
				$var_default = isset( $product_data['default_variation'] ) ? $product_data['default_variation'] : '';
				foreach ( $variations as $variations_v ) {
					if ( $var_default === $variations_v['skuId'] ) {
						$product_data['variation_default'] = $variations_v['attributes'];
					}

					$variations_attribute = isset( $variations_v['attributes'] ) ? $variations_v['attributes'] : [];
					if ( is_array( $variations_attribute ) && count( $variations_attribute ) ) {
						foreach ( $variations_attribute as $variations_attribute_k => $variations_attribute_v ) {
							if ( ! isset( $variations_attributes[ $variations_attribute_k ] ) ) {
								$variations_attributes[ $variations_attribute_k ] = array( $variations_attribute_v );
							} elseif ( ! in_array( $variations_attribute_v, $variations_attributes[ $variations_attribute_k ] ) ) {
								$variations_attributes[ $variations_attribute_k ][] = $variations_attribute_v;
							}
						}
					}
				}

				if ( is_array( $attributes ) && count( $attributes ) ) {
					foreach ( $attributes as $attributes_k => $attributes_v ) {
						if ( ! empty( $variations_attributes[ $attributes_v['slug'] ] ) ) {
							$attributes[ $attributes_k ]['values'] = array_intersect( $attributes[ $attributes_k ]['values'], $variations_attributes[ $attributes_v['slug'] ] );
						}
					}
				}
			}
		} else {
			$variations    = $this->get_product_variations( $product_draft_id );
			$shipping_cost = 0;

			if ( $this->settings->get_param( 'shipping_cost_after_price_rules' ) ) {
				foreach ( $variations as $variations_k => $variations_v ) {
					$variation_sale_price    = ( $variations_v['sale_price'] );
					$variation_regular_price = ( $variations_v['regular_price'] );
					$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
					$sale_price              = $this->process_price( $price, true );
					if ( $sale_price ) {
						$sale_price += $shipping_cost;
					}
					$regular_price                                = $this->process_price( $price ) + $shipping_cost;
					$variations[ $variations_k ]['sale_price']    = $this->process_exchange_price( $sale_price );
					$variations[ $variations_k ]['regular_price'] = $this->process_exchange_price( $regular_price );
				}
			} else {
				foreach ( $variations as $variations_k => $variations_v ) {
					$variation_sale_price                         = $variations_v['sale_price'] ? ( ( $variations_v['sale_price'] ) + $shipping_cost ) : ( $variations_v['sale_price'] );
					$variation_regular_price                      = ( $variations_v['regular_price'] ) + $shipping_cost;
					$price                                        = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
					$variations[ $variations_k ]['sale_price']    = $this->process_exchange_price( $this->process_price( $price, true ) );
					$variations[ $variations_k ]['regular_price'] = $this->process_exchange_price( $this->process_price( $price ) );
				}
			}
		}

		if ( count( $variations ) ) {
			if ( 1 != $override_options['override_title'] ) {
				$product_data['title'] = $woo_product->get_title();
			}

			if ( 1 != $override_options['override_images'] ) {
				$product_data['old_product_image']   = get_post_meta( $woo_product_id, '_thumbnail_id', true );
				$product_data['old_product_gallery'] = get_post_meta( $woo_product_id, '_product_image_gallery', true );
			}

			if ( 1 != $override_options['override_description'] ) {
				$product_data['short_description'] = $woo_product->get_short_description();
				$product_data['description']       = $woo_product->get_description();
			}

			if ( isset( $product_data['gallery'] ) ) {
				$product_data['gallery'] = array_values( array_filter( $product_data['gallery'] ) );
				if ( $product_data['image'] ) {
					$product_image_key = array_search( $product_data['image'], $product_data['gallery'] );
					if ( $product_image_key !== false ) {
						unset( $product_data['gallery'][ $product_image_key ] );
						$product_data['gallery'] = array_values( $product_data['gallery'] );
					}
				}
			} else {
				$product_data['gallery'] = [];
			}

			$variation_images                  = Taobao_Post::get_post_meta( $product_draft_id, '_tbds_variation_images', true );
			$product_data['variation_images']  = $variation_images;
			$product_data['attributes']        = $attributes;
			$product_data['variations']        = $variations;
			$product_data['parent_id']         = $product_draft_id;
			$product_data['taobao_product_id'] = Taobao_Post::get_post_meta( $product_draft_id, '_tbds_sku', true );
			$disable_background_process        = $this->settings->get_param( 'disable_background_process' );

			if ( $override_keep_product ) {
				$is_simple = false;
				if ( ! is_array( $attributes ) || ! count( $attributes ) || ( count( $variations ) === 1 && $this->settings->get_param( 'simple_if_one_variation' ) ) ) {
					$is_simple = true;
				}

				$woo_product->set_status( $product_data['status'] );
				if ( $product_data['sku'] ) {
					try {
						$woo_product->set_sku( wc_product_generate_unique_sku( $woo_product_id, $product_data['sku'] ) );
					} catch ( \WC_Data_Exception $e ) {
					}
				}

				if ( 1 == $override_options['override_title'] && $product_data['title'] ) {
					$woo_product->set_name( $product_data['title'] );
				}

				$dispatch = false;
				if ( 1 == $override_options['override_images'] ) {
					if ( $product_data['image'] ) {
						$thumb_id = Utils::download_image( $image_id, $product_data['image'], $woo_product_id );
						if ( ! is_wp_error( $thumb_id ) ) {
							update_post_meta( $woo_product_id, '_thumbnail_id', $thumb_id );
						}
					}

					update_post_meta( $woo_product_id, '_product_image_gallery', '' );

					$this->process_gallery_images( $product_data['gallery'], $disable_background_process, $woo_product_id, $product_draft_id, $dispatch );
				}

				if ( 1 == $override_options['override_description'] ) {
					$woo_product->set_description( $product_data['description'] );
					$this->process_description_images( $product_data['description'], $disable_background_process, $woo_product_id, $product_draft_id, $dispatch );
				}

				/*Set product tag*/
				if ( isset( $product_data['tags'] ) && is_array( $product_data['tags'] ) && count( $product_data['tags'] ) ) {
					wp_set_post_terms( $woo_product_id, $product_data['tags'], 'product_tag', true );
				}

				/*Set product categories*/
				if ( isset( $product_data['categories'] ) && is_array( $product_data['categories'] ) && count( $product_data['categories'] ) ) {
					wp_set_post_terms( $woo_product_id, $product_data['categories'], 'product_cat', true );
				}

				/*Set product shipping class*/
				if ( isset( $product_data['shipping_class'] ) && $product_data['shipping_class'] && get_term_by( 'id', $product_data['shipping_class'], 'product_shipping_class' ) ) {
					wp_set_post_terms( $woo_product_id, array( intval( $product_data['shipping_class'] ) ), 'product_shipping_class', false );
				}

				update_post_meta( $woo_product_id, '_tbds_taobao_product_id', $product_data['taobao_product_id'] );

				Utils::set_catalog_visibility( $woo_product_id, $product_data['catalog_visibility'] );

				if ( $is_simple ) {
					if ( ! empty( $variations[0]['skuId'] ) ) {
						update_post_meta( $woo_product_id, '_tbds_taobao_variation_id', $variations[0]['skuId'] );
					}

					if ( $woo_product->is_type( 'variable' ) ) {
						$woo_product->set_attributes( [] );
						$woo_product->save();
						$children = $woo_product->get_children();
						if ( count( $children ) ) {
							foreach ( $children as $variation_id ) {
								wp_delete_post( $variation_id, true );
							}
						}
						wp_set_object_terms( $woo_product_id, 'simple', 'product_type' );
					}

					$sale_price    = isset( $variations[0]['sale_price'] ) ? floatval( $variations[0]['sale_price'] ) : '';
					$regular_price = isset( $variations[0]['regular_price'] ) ? floatval( $variations[0]['regular_price'] ) : '';
					$price         = $regular_price;

					if ( $sale_price && $sale_price > 0 && $regular_price && $sale_price < $regular_price ) {
						$price = $sale_price;
					} else {
						$sale_price = '';
					}

					$woo_product->set_regular_price( $regular_price );
					$woo_product->set_sale_price( $sale_price );
					$woo_product->set_price( $price );
					$woo_product->set_manage_stock( 'yes' );
					$woo_product->set_stock_status( 'instock' );
					$woo_product->set_stock_quantity( isset( $variations[0]['stock'] ) ? absint( $variations[0]['stock'] ) : 0 );
					$woo_product->save();
					wp_set_object_terms( $woo_product_id, 'simple', 'product_type' );

					if ( $dispatch ) {
						$this->process_image->save()->dispatch();
					}

				} else {
					$default_attr = isset( $product_data['variation_default'] ) ? $product_data['variation_default'] : [];
					$attr_data    = $this->create_product_attributes( $attributes, $default_attr );

					if ( count( $attr_data ) ) {
						$woo_product->set_attributes( $attr_data );
						if ( $default_attr ) {
							$woo_product->set_default_attributes( $default_attr );
						}
						$woo_product->save();
					}

					wp_set_object_terms( $woo_product_id, 'variable', 'product_type' );
					$children = [];

					if ( $woo_product->is_type( 'variable' ) ) {
						$children = $woo_product->get_children();
					}

					$use_global_attributes = $this->settings->get_param( 'use_global_attributes' );
					$manage_stock          = $this->settings->get_param( 'manage_stock' );
					$manage_stock          = $manage_stock ? 'yes' : 'no';

					if ( count( $children ) ) {
						$skuIdArray = array_column( $variations, 'skuId' );
						foreach ( $children as $variation_id ) {

							if ( ! empty( $replace_items[ $variation_id ] ) ) {
								$variations_key = array_search( $replace_items[ $variation_id ], $skuIdArray );
								if ( $variations_key !== false ) {
									$variation = new \WC_Product_Variation( $variation_id );

									if ( $variation ) {
										$product_data['variations'][ $variations_key ]['variation_id'] = $variation_id;

										if ( 1 != $override_options['override_images'] && ! $variation->get_image_id() ) {
											$product_data['variations'][ $variations_key ]['image'] = '';
										}

										$product_variation = $variations[ $variations_key ];
										$stock_quantity    = isset( $product_variation['stock'] ) ? absint( $product_variation['stock'] ) : 0;
										$v_attributes      = [];
										if ( $use_global_attributes ) {
											foreach ( $product_variation['attributes'] as $option_k => $attr ) {
												$attribute_id  = wc_attribute_taxonomy_id_by_name( $option_k );
												$attribute_obj = wc_get_attribute( $attribute_id );
												if ( $attribute_obj ) {
													$attribute_value = $this->get_term_by_name( $attr, $attribute_obj->slug );
													if ( $attribute_value ) {
														$v_attributes[ strtolower( urlencode( $attribute_obj->slug ) ) ] = $attribute_value->slug;
													}
												}
											}
										} else {
											foreach ( $product_variation['attributes'] as $option_k => $attr ) {
												$v_attributes[ strtolower( urlencode( $option_k ) ) ] = $attr;
											}
										}

										$variation->set_attributes( $v_attributes );
										$fields = array(
											'sku'            => wc_product_generate_unique_sku( $variation_id, $product_variation['sku'] ),
											'regular_price'  => $product_variation['regular_price'],
											'price'          => $product_variation['regular_price'],
											'sale_price'     => '',
											'manage_stock'   => $manage_stock,
											'stock_status'   => 'instock',
											'stock_quantity' => $stock_quantity,
										);

										if ( isset( $product_variation['sale_price'] ) && $product_variation['sale_price'] && $product_variation['sale_price'] < $product_variation['regular_price'] ) {
											$fields['sale_price'] = $product_variation['sale_price'];
											$fields['price']      = $product_variation['sale_price'];
										}

										foreach ( $fields as $field => $value ) {
											$variation->{"set_$field"}( wc_clean( $value ) );
										}
										$variation->update_meta_data( '_tbds_taobao_variation_id', $product_variation['skuId'] );
										$variation->save();

									}
								} else {
									wp_delete_post( $variation_id, true );
								}
							} else {
								wp_delete_post( $variation_id, true );
							}
						}
					}
					/*Create product variation*/
					$this->import_product_variation( $woo_product_id, $product_data, $dispatch, $disable_background_process );
				}

				Taobao_Post::update_post( [ 'ID' => $product_draft_id, 'post_status' => 'publish' ] );

				Taobao_Post::update_post_meta( $product_draft_id, '_tbds_woo_id', $woo_product_id );

				if ( $override_product_id ) {
					Taobao_Post::delete_post( $override_product_id );
				}

				wp_send_json( array(
					'status'      => 'success',
					'product_id'  => $woo_product_id,
					'message'     => '',
					'button_html' => Utils::get_button_view_edit_html( $woo_product_id ),
				) );
			} else {
				$product_data['replace_items'] = $replace_items;
				$product_data['replace_title'] = $override_options['override_title'];
				$product_data['found_items']   = $found_items;
				$product_id                    = $this->import_product( $product_data );

				$response = array(
					'status'     => 'error',
					'message'    => '',
					'product_id' => '',
				);

				if ( ! is_wp_error( $product_id ) ) {
					if ( $override_product_id ) {
						Taobao_Post::delete_post( $override_product_id );
					}
					wp_delete_post( $woo_product_id );
					$response['status']      = 'success';
					$response['product_id']  = $product_id;
					$response['button_html'] = Utils::get_button_view_edit_html( $woo_product_id );
				} else {
					$response['message'] = $product_id->get_error_messages();
				}
				wp_send_json( $response );
			}
		} else {
			wp_send_json( array(
				'status'  => 'error',
				'message' => esc_html__( 'Please select at least 1 variation to import this product.', 'chinads' ),
			) );
		}

	}

	/**
	 * @param array $args1 $key=>$value are key and value of woocommerce_order_items table
	 * @param array $args2 $key=>$value are key and value of woocommerce_order_itemmeta table
	 *
	 * @return array|null|object
	 */
	protected function query_order_item_meta( $args1 = [], $args2 = [] ) {
		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}woocommerce_order_items as woocommerce_order_items 
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta as woocommerce_order_itemmeta 
                WHERE woocommerce_order_items.order_item_id=woocommerce_order_itemmeta.order_item_id";

		$args = [];

		if ( ! empty( $args1 ) ) {
			foreach ( $args1 as $key => $value ) {
				if ( is_array( $value ) ) {
					$sql .= " AND woocommerce_order_items.{$key} IN (" . implode( ', ', array_fill( 0, count( $value ), '%s' ) ) . ")";
					foreach ( $value as $v ) {
						$args[] = $v;
					}
				} else {
					$sql    .= " AND woocommerce_order_items.{$key}='%s'";
					$args[] = $value;
				}
			}
		}

		if ( ! empty( $args2 ) ) {
			foreach ( $args2 as $key => $value ) {
				if ( is_array( $value ) ) {
					$sql .= " AND woocommerce_order_itemmeta.{$key} IN (" . implode( ', ', array_fill( 0, count( $value ), '%s' ) ) . ")";
					foreach ( $value as $v ) {
						$args[] = $v;
					}
				} else {
					$sql    .= " AND woocommerce_order_itemmeta.{$key}='%s'";
					$args[] = $value;
				}
			}
		}

		$query      = $wpdb->prepare( $sql, $args );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$line_items = $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching , WordPress.DB.PreparedSQL.NotPrepared

		return $line_items;
	}

	/**
	 * @param $items
	 *
	 * @throws Exception
	 */
	public function skip_item_with_taobao_order_id( &$items ) {
		foreach ( $items as $key => $item ) {
			if ( wc_get_order_item_meta( $item['order_item_id'], '_tbds_taobao_order_id', true ) ) {
				unset( $items[ $key ] );
			}
		}
		$items = array_values( $items );
	}

	/**
	 * @param $woo_product WC_Product
	 * @param $woo_product_child
	 * @param $variations
	 * @param $item_count
	 * @param bool $is_simple
	 *
	 * @return false|string
	 */
	public function get_override_variation_html( $woo_product, $woo_product_child, $variations, $item_count, $is_simple ) {
		$html                  = '';
		$woo_product_child_obj = wc_get_product( $woo_product_child );

		if ( $woo_product_child_obj ) {
			$current = [];
			$child_attrs = $woo_product_child_obj->get_attributes();
			$attrs = wc_get_product( $woo_product )->get_attributes();
			foreach ($attrs as $attr_key => $option){
				$tmp = $child_attrs[$attr_key];
				if ( substr( $attr_key, 0, 3 ) === 'pa_' ) {
					$attribute_id  = wc_attribute_taxonomy_id_by_name( $option['name'] );
					$attribute_obj = wc_get_attribute( $attribute_id );
					if ( $attribute_obj ) {
						$attribute_value = get_term_by( 'slug', $tmp, $attribute_obj->slug );
						if ( ! $attribute_value ) {
							$attribute_value = get_term_by( 'name', $tmp, $attribute_obj->slug );
						}
						if ( $attribute_value ) {
							$tmp = $attribute_value->name;
						}
					}
				}
				$current[] = $tmp;
			}
			$current = implode(', ', $current);
			ob_start();
			?>
            <tr class="tbdsoverride-order-container" data-replace_item_id="<?php echo esc_attr( $woo_product_child ) ?>">
                <td class="tbdsoverride-from-td">
                    <div class="tbdsoverride-from">
						<?php
						if ( $woo_product_child_obj ) {
							if ( $woo_product_child_obj->get_image_id() ) {
								$image_src = wp_get_attachment_thumb_url( $woo_product_child_obj->get_image_id() );
							} elseif ( $woo_product->get_image_id() ) {
								$image_src = wp_get_attachment_thumb_url( $woo_product->get_image_id() );
							} else {
								$image_src = wc_placeholder_img_src();
							}
							if ( $image_src ) {
								?>
                                <div class="tbdsoverride-from-image">
                                    <img src="<?php echo esc_url( $image_src ) ?>" width="30px"
                                         height="30px">
                                </div>
								<?php
							}

						}
						?>
                        <div class="tbdsoverride-from-title">
							<?php
							echo esc_html( $current );
							?>
                        </div>
                    </div>
                </td>
                <td><?php echo esc_html( $item_count ); ?></td>
                <td class="tbdsoverride-with-attributes">
					<?php
					if ( $is_simple ) {
						$this->get_override_simple_select_html( $variations[0] );
					} else {
						$this->get_override_variable_select_html( $variations, $current );
					}
					?>
                </td>
            </tr>
			<?php
			$html = ob_get_clean();
		}

		return $html;
	}

	public function get_override_simple_select_html( $variation ) {
		?>
        <select class="vi-ui fluid dropdown tbds-override-with">
            <option value="none">
				<?php esc_html_e( 'Do not replace', 'chinads' ) ?>
            </option>
            <option value="<?php echo esc_attr( $variation['skuId'] ) ?>">
				<?php esc_html_e( 'Replace with new product', 'chinads' ) ?>
            </option>
        </select>
		<?php
	}

	public function get_override_variable_select_html( $variations, $current = '' ) {
		?>
        <select class="vi-ui fluid dropdown tbds-override-with">
            <option value=""><?php esc_html_e( 'Do not replace', 'chinads' ) ?></option>
			<?php
			foreach ( $variations as $variation ) {
				$attribute = implode( ', ', array_values( $variation['attributes'] ) );
				$selected  = $this->is_attribute_value_equal( $current, $attribute ) ? 'selected' : '';
				printf( '<option value="%s" %s>%s</option>', esc_attr( $variation['skuId'] ), esc_attr( $selected ), esc_html( $attribute ) );
			}
			?>
        </select>
		<?php
	}

	public function is_attribute_value_equal( $value_1, $value_2 ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value_1 ) === mb_strtolower( $value_2 ) : strtolower( $value_1 ) === strtolower( $value_2 );
	}

	/**
	 * @param $woo_product WC_Product
	 * @param $woo_product_id
	 * @param $variations
	 * @param bool $found_item
	 * @param bool $is_simple
	 *
	 * @return false|string
	 */
	public function get_override_simple_html( $woo_product, $woo_product_id, $variations, $found_item, $is_simple ) {
		ob_start();
		?>
        <tr class="tbds-override-order-container" data-replace_item_id="<?php echo esc_attr( $woo_product_id ) ?>">
            <td class="tbds-override-from-td">
                <div class="tbds-override-from">
					<?php
					if ( $woo_product->get_image_id() ) {
						$image_src = wp_get_attachment_thumb_url( $woo_product->get_image_id() );
					} elseif ( $woo_product->get_image_id() ) {
						$image_src = wp_get_attachment_thumb_url( $woo_product->get_image_id() );
					} else {
						$image_src = wc_placeholder_img_src();
					}
					if ( $image_src ) {
						?>
                        <div class="tbds-override-from-image">
                            <img src="<?php echo esc_url( $image_src ) ?>" width="30px"
                                 height="30px">
                        </div>
						<?php
					}
					?>
                    <div class="tbds-override-from-title">
						<?php
						echo esc_html( $woo_product->get_title() );
						?>
                    </div>
                </div>
            </td>
            <td class="tbds-override-unfulfilled-items-count">
				<?php echo esc_html( $found_item ); ?>
            </td>
            <td class="tbds-override-with-attributes">
				<?php
				if ( $is_simple ) {
					$this->get_override_simple_select_html( $variations[0] );
				} else {
					$this->get_override_variable_select_html( $variations );
				}
				?>
            </td>
        </tr>
		<?php
		return ob_get_clean();
	}

	public function process_price( $price, $is_sale_price = false ) {
		if ( ! $price ) {
			return $price;
		}
		$original_price  = $price;
		$price_default   = $this->settings->get_param( 'price_default' );
		$price_from      = $this->settings->get_param( 'price_from' );
		$price_to        = $this->settings->get_param( 'price_to' );
		$plus_value_type = $this->settings->get_param( 'plus_value_type' );

		if ( $is_sale_price ) {
			$plus_sale_value = $this->settings->get_param( 'plus_sale_value' );
			$level_count     = count( $price_from );
			if ( $level_count > 0 ) {
				/*adjust price rules since version 1.0.1.1*/
				if ( ! is_array( $price_to ) || count( $price_to ) !== $level_count ) {
					if ( $level_count > 1 ) {
						$price_to   = array_values( array_slice( $price_from, 1 ) );
						$price_to[] = '';
					} else {
						$price_to = array( '' );
					}
				}
				$match = false;
				for ( $i = 0; $i < $level_count; $i ++ ) {
					if ( $price >= $price_from[ $i ] && ( $price_to[ $i ] === '' || $price <= $price_to[ $i ] ) ) {
						$match = $i;
						break;
					}
				}
				if ( $match !== false ) {
					if ( $plus_sale_value[ $match ] < 0 ) {
						$price = 0;
					} else {
						$price = $this->calculate_price_base_on_type( $price, $plus_sale_value[ $match ], $plus_value_type[ $match ] );
					}
				} else {
					$plus_sale_value_default = isset( $price_default['plus_sale_value'] ) ? $price_default['plus_sale_value'] : 1;
					if ( $plus_sale_value_default < 0 ) {
						$price = 0;
					} else {
						$price = $this->calculate_price_base_on_type( $price, $plus_sale_value_default, isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply' );
					}
				}
			}
		} else {
			$plus_value  = $this->settings->get_param( 'plus_value' );
			$level_count = count( $price_from );
			if ( $level_count > 0 ) {
				/*adjust price rules since version 1.0.1.1*/
				if ( ! is_array( $price_to ) || count( $price_to ) !== $level_count ) {
					if ( $level_count > 1 ) {
						$price_to   = array_values( array_slice( $price_from, 1 ) );
						$price_to[] = '';
					} else {
						$price_to = array( '' );
					}
				}
				$match = false;
				for ( $i = 0; $i < $level_count; $i ++ ) {
					if ( $price >= $price_from[ $i ] && ( $price_to[ $i ] === '' || $price <= $price_to[ $i ] ) ) {
						$match = $i;
						break;
					}
				}
				if ( $match !== false ) {
					$price = $this->calculate_price_base_on_type( $price, $plus_value[ $match ], $plus_value_type[ $match ] );
				} else {
					$price = $this->calculate_price_base_on_type( $price, isset( $price_default['plus_value'] ) ? $price_default['plus_value'] : 2, isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply' );
				}
			}
		}

		return apply_filters( 'tbds_processed_price', $price, $is_sale_price, $original_price );
	}

	protected function calculate_price_base_on_type( $price, $value, $type ) {
		$match_value = floatval( $value );
		switch ( $type ) {
			case 'fixed':
				$price = $price + $match_value;
				break;
			case 'percent':
				$price = $price * ( 1 + $match_value / 100 );
				break;
			case 'multiply':
				$price = $price * $match_value;
				break;
			default:
				$price = $match_value;
		}

		return $price;
	}

	public function save_attributes() {
		check_ajax_referer( 'tbds_security' );
		$response = array(
			'status'       => 'error',
			'new_slug'     => '',
			'change_value' => false,
			'message'      => '',
		);

		$data          = isset( $_POST['form_data']['tbds_product'] ) ? wc_clean( wp_unslash( $_POST['form_data']['tbds_product'] ) ) : [];
		$product_data  = array_values( $data )[0];
		$product_id    = array_keys( $data )[0];
		$new_attribute = $product_data['attributes'] ?? [];
		$attributes    = Taobao_Post::get_post_meta( $product_id, '_tbds_attributes', true );
		$variations    = Taobao_Post::get_post_meta( $product_id, '_tbds_variations', true );
		$change_slug   = '';
		$change_value  = false;
		if ( count( $new_attribute ) && count( $attributes ) ) {
			$response['status'] = 'success';
			$new_attribute_v    = array_values( $new_attribute )[0];
			$attribute_k        = array_keys( $new_attribute )[0];

			if ( ! empty( $new_attribute_v['name'] ) && isset( $attributes[ $attribute_k ] ) ) {
				$new_slug       = Utils::sanitize_taxonomy_name( $new_attribute_v['name'] );
				$attribute_slug = isset( $attributes[ $attribute_k ]['slug_edited'] ) ? $attributes[ $attribute_k ]['slug_edited'] : $attributes[ $attribute_k ]['slug'];

				if ( ! $this->is_attribute_value_equal( $new_slug, $attribute_slug ) ) {
					$change_slug = $new_slug;

					foreach ( $variations as $variation_k => $variation ) {
						$v_attributes = isset( $variation['attributes_edited'] ) ? $variation['attributes_edited'] : $variation['attributes'];
						if ( isset( $v_attributes[ $attribute_slug ] ) ) {
							$v_attributes[ $new_slug ] = $v_attributes[ $attribute_slug ];
							unset( $v_attributes[ $attribute_slug ] );
							$variations[ $variation_k ]['attributes_edited'] = $v_attributes;
						}
					}

					$attributes[ $attribute_k ]['slug_edited'] = $new_slug;
					$attributes[ $attribute_k ]['name_edited'] = $new_attribute_v['name'];
					$attribute_slug                            = $new_slug;
				}

				if ( ! empty( $new_attribute_v['values'] ) ) {
					$new_values    = $new_attribute_v['values'];
					$values_edited = isset( $attributes[ $attribute_k ]['values_edited'] ) ? $attributes[ $attribute_k ]['values_edited'] : $attributes[ $attribute_k ]['values'];
					foreach ( $values_edited as $value_k => $value ) {
						if ( ! empty( $new_values[ $value_k ] ) ) {
							$new_value = trim( $new_values[ $value_k ] );
							if ( $new_value !== $value ) {
								$change_value = true;
								foreach ( $variations as $variation_k => $variation ) {
									$v_attributes = isset( $variation['attributes_edited'] ) ? $variation['attributes_edited'] : $variation['attributes'];
									if ( isset( $v_attributes[ $attribute_slug ] ) && $this->is_attribute_value_equal( $v_attributes[ $attribute_slug ], $value ) ) {

										$values_edited[ $value_k ] = $new_value;

										$v_attributes[ $attribute_slug ] = $new_value;

										$variations[ $variation_k ]['attributes_edited'] = $v_attributes;
									}
								}
								$attributes[ $attribute_k ]['values_edited'] = $values_edited;
							}
						}
					}
				}
			}
		}

		if ( $change_slug || $change_value ) {
			Taobao_Post::update_post_meta( $product_id, '_tbds_attributes', $attributes );
			Taobao_Post::update_post_meta( $product_id, '_tbds_variations', $variations );
		}

		$response['new_slug']     = $change_slug;
		$response['change_value'] = $change_value;
		wp_send_json( $response );
	}

	public function ajax_remove_attribute() {
		check_ajax_referer( 'tbds_security' );

		$response = array(
			'status'  => 'error',
			'html'    => '',
			'message' => esc_html__( 'Invalid data', 'chinads' ),
		);

		$data             = isset( $_POST['form_data']['tbds_product'] ) ? wc_clean( wp_unslash( $_POST['form_data']['tbds_product'] ) ) : [];
		$product_data     = array_values( $data )[0];
		$product_id       = array_keys( $data )[0];
		$attribute_value  = isset( $_POST['attribute_value'] ) ? sanitize_text_field( wp_unslash( $_POST['attribute_value'] ) ) : '';
		$remove_attribute = $product_data['attributes'] ?? [];
		$product          = Taobao_Post::get_post( $product_id );
		if ( $product && $product->post_type === 'tbds_draft_product' && in_array( $product->post_status, array(
				'draft',
				'override'
			) ) ) {
			$attributes       = Taobao_Post::get_post_meta( $product_id, '_tbds_attributes', true );
			$variations       = Taobao_Post::get_post_meta( $product_id, '_tbds_variations', true );
			$split_variations = Taobao_Post::get_post_meta( $product_id, '_tbds_split_variations', true );

			if ( $this->remove_product_attribute( $product_id, $remove_attribute, $attribute_value, $split_variations, $attributes, $variations ) ) {
				$response['status'] = 'success';
				if ( ! count( $attributes ) ) {
					$key                         = isset( $_POST['product_index'] ) ? absint( $_POST['product_index'] ) : '';
					$currency                    = 'CNY';
					$woocommerce_currency        = get_woocommerce_currency();
					$woocommerce_currency_symbol = get_woocommerce_currency_symbol();
					$manage_stock                = $this->settings->get_param( 'manage_stock' );
					$use_different_currency      = false;
//					$variations                  = $this->get_product_variations( $product_id );
					$decimals = wc_get_price_decimals();

					if ( $decimals < 1 ) {
						$decimals = 1;
					} else {
						$decimals = pow( 10, ( - 1 * $decimals ) );
					}

					if ( strtolower( $woocommerce_currency ) != strtolower( $currency ) ) {
						$use_different_currency = true;
					}

					ob_start();
					$this->simple_product_price_field_html( $key, $manage_stock, $variations, $use_different_currency, $currency, $product_id, $woocommerce_currency_symbol, $decimals, '', '' );
					$response['html'] = ob_get_clean();
				}
				$response['message'] = esc_html__( 'Remove attribute successfully', 'chinads' );
			}
		} else {
			$response['message'] = esc_html__( 'Invalid product', 'chinads' );
		}
		wp_send_json( $response );
	}

	public function remove_product_attribute( $product_id, $remove_attribute, $attribute_value, $split_variations, &$attributes, &$variations ) {
		$remove = false;
		if ( count( $remove_attribute ) && count( $attributes ) ) {
			$new_attribute_v = array_values( $remove_attribute )[0];
			$attribute_k     = array_keys( $remove_attribute )[0];

			if ( ( ! isset( $new_attribute_v['name'] ) || $new_attribute_v['name'] ) && isset( $attributes[ $attribute_k ] ) ) {
				$attribute_slug = isset( $attributes[ $attribute_k ]['slug_edited'] ) ? $attributes[ $attribute_k ]['slug_edited'] : $attributes[ $attribute_k ]['slug'];

				foreach ( $variations as $variation_k => $variation ) {
					if ( isset( $variation['attributes_edited'] ) ) {
						if ( isset( $variation['attributes_edited'][ $attribute_slug ] ) ) {
							if ( ! $this->is_attribute_value_equal( $variation['attributes_edited'][ $attribute_slug ], $attribute_value ) ) {
								unset( $variations[ $variation_k ] );
								if ( is_array( $split_variations ) && count( $split_variations ) ) {
									$search = array_search( $variation['skuAttr'], $split_variations );
									if ( $search !== false ) {
										unset( $split_variations[ $search ] );
									} else {
										$search = array_search( "{$variation['skuId']}{$variation['skuAttr']}", $split_variations );
										if ( $search !== false ) {
											unset( $split_variations[ $search ] );
										}
									}
								}
							}
							unset( $variations[ $variation_k ]['attributes_edited'][ $attribute_slug ] );
						}
					} else {
						if ( isset( $variation['attributes'][ $attribute_slug ] ) ) {
							if ( ! $this->is_attribute_value_equal( $variation['attributes'][ $attribute_slug ], $attribute_value ) ) {
								unset( $variations[ $variation_k ] );
								if ( is_array( $split_variations ) && count( $split_variations ) ) {
									$search = array_search( $variation['skuAttr'], $split_variations );
									if ( $search !== false ) {
										unset( $split_variations[ $search ] );
									} else {
										$search = array_search( "{$variation['skuId']}{$variation['skuAttr']}", $split_variations );
										if ( $search !== false ) {
											unset( $split_variations[ $search ] );
										}
									}
								}
							}
							unset( $variations[ $variation_k ]['attributes'][ $attribute_slug ] );
						}
					}
				}

				unset( $attributes[ $attribute_k ] );
				$variations = array_values( $variations );

				Taobao_Post::update_post_meta( $product_id, '_tbds_attributes', $attributes );
				Taobao_Post::update_post_meta( $product_id, '_tbds_variations', $variations );

				if ( is_array( $split_variations ) ) {
					Taobao_Post::update_post_meta( $product_id, '_tbds_split_variations', $split_variations );
				}

				$remove = true;
			}
		}

		return $remove;
	}

	public function empty_import_list() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! empty( $_GET['tbds_empty_product_list'] ) && $page === 'tbds-import-list' ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ) ) ) {
				Taobao_Post::empty_import_list();
				wp_safe_redirect( admin_url( "admin.php?page={$page}" ) );
				exit();
			}
		}
	}

	public function menu_product_count() {
		global $submenu;
		if ( isset( $submenu['tbds-import-list'] ) ) {
			// Add count if user has access.
			if ( current_user_can( 'manage_options' ) ) {
				$count         = Taobao_Post::count_posts( 'tbds_draft_product', 'readable' );
				$product_count = floatval( $count->draft ?? 0 ) + floatval( $count->override ?? 0 );
				foreach ( $submenu['tbds-import-list'] as $key => $menu_item ) {
					if ( ! empty( $menu_item[2] ) && $menu_item[2] === 'tbds-import-list' ) {
						$count_label = sprintf( " <span class='update-plugins count-%s'><span class='tbds-import-list-count'>%s</span></span>",
							esc_attr( $product_count ), esc_html( number_format_i18n( $product_count ) ) );
						$submenu['tbds-import-list'][ $key ][0] .= $count_label;
					}
				}
			}
		}
	}

	public function process_exchange_price( $price ) {
		if ( ! $price ) {
			return $price;
		}
		$rate = floatval( $this->settings->get_param( 'import_currency_rate' ) );
		if ( $rate ) {
			$price = $price * $rate;
		}

		return round( $price, wc_get_price_decimals() );
	}
}
