<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Taobao_Post;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;

class Imported {
	protected static $instance = null;
	protected $settings;
	protected $product_count;

	public function __construct() {
		$this->settings = Data::instance();
		add_action( 'admin_head', array( $this, 'menu_product_count' ), 999 );
		add_action( 'wp_ajax_tbds_delete_product', array( $this, 'delete' ) );
		add_action( 'wp_ajax_tbds_trash_product', array( $this, 'trash' ) );
		add_action( 'wp_ajax_tbds_override_product', array( $this, 'override_product' ) );

	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function menu_product_count() {
		global $submenu;
		if ( isset( $submenu['tbds-import-list'] ) ) {
			// Add count if user has access.
			if ( current_user_can( 'manage_options' ) ) {
				$count         = Taobao_Post::count_posts( );
				$product_count = intval( $count->publish ?? 0 ) + intval($count->trash??0);
				foreach ( $submenu['tbds-import-list'] as $key => $menu_item ) {
					if ( ! empty( $menu_item[2] ) && $menu_item[2] === 'tbds-imported' ) {
						$count_label = sprintf( " <span class='update-plugins count-%s'><span class='tbds-import-list-count'>%s</span></span>",
							esc_attr( $product_count ), esc_html( number_format_i18n( $product_count ) ) );
						$submenu['tbds-import-list'][ $key ][0] .= $count_label;
					}
				}
			}
		}
	}

	public function imported_list_callback() {
		$user     = get_current_user_id();
		$screen   = get_current_screen();
		$option   = $screen->get_option( 'per_page', 'option' );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty ( $per_page ) || $per_page < 1 ) {
			$per_page = $screen->get_option( 'per_page', 'default' );
		}

		$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = ! empty( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'publish';//phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>

        <div class="wrap">
            <h2><?php esc_html_e( 'All imported products', 'chinads' ) ?></h2>
			<?php
			$args           = array(
				'post_type'      => 'tbds_draft_product',
				'post_status'    => $status,
				'order'          => 'DESC',
				'orderby'        => 'meta_value_num',
				'fields'         => 'ids',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'meta_query'     => array(//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'and',
					array(
						'key'     => '_tbds_woo_id',
						'compare' => 'exists',
					)
				),
			);
			$keyword        = isset( $_GET['tbds_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tbds_search'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tbds_search_id = isset( $_GET['tbds_search_woo_id'] ) ? absint( $_GET['tbds_search_woo_id'] ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $tbds_search_id ) {
				$args['meta_value']     = $tbds_search_id;//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				$args['posts_per_page'] = 1;
				$keyword                = '';
			} else if ( $keyword ) {
				$args['s'] = $keyword;
			}

			$the_query     = Taobao_Post::query($args);
			$product_ids = $the_query->get_posts();
			$count         = $the_query->found_posts;
			$total_page    = $the_query->max_num_pages;
			$paged         = $total_page >= intval( $paged ) ? $paged : 1;
			$product_count = Taobao_Post::count_posts();
			wp_reset_postdata();
			if (! empty( $product_ids ) && is_array( $product_ids ) ) {
				ob_start();
				?>
                <form method="get" class="tbds-imported-products-<?php echo esc_attr( $status ) ?>">
                    <input type="hidden" name="page" value="tbds-imported">
                    <input type="hidden" name="post_status" value="<?php echo esc_attr( $status ) ?>">
                    <div class="tablenav top">
                        <div class="subsubsub">
                            <ul>
                                <li class="tbds-imported-products-count-publish-container">
                                    <a href="<?php echo esc_attr( admin_url( 'admin.php?page=tbds-imported' ) ) ?>">
										<?php esc_html_e( 'Publish', 'chinads' ); ?></a>
                                    (<span class="tbds-imported-products-count-publish">
		                                <?php echo esc_html( $product_count->publish ) ?>
	                                </span>)
                                </li>
                                |
                                <li class="tbds-imported-products-count-trash-container">
                                    <a href="<?php echo esc_attr( admin_url( 'admin.php?page=tbds-imported&post_status=trash' ) ) ?>">
										<?php esc_html_e( 'Trash', 'chinads' ); ?></a>
                                    (<span class="tbds-imported-products-count-trash">
		                                <?php echo esc_html( $product_count->trash ) ?>
	                                </span>)
                                </li>
                            </ul>
                        </div>
                        <div class="tablenav-pages">
                            <div class="pagination-links">
								<?php
								if ( $paged > 2 ) {
									?>
                                    <a class="prev-page button" href="<?php echo esc_url( add_query_arg(
										array(
											'page'        => 'tbds-imported',
											'paged'       => 1,
											'tbds_search' => $keyword,
											'post_status' => $status,
										), admin_url( 'admin.php' )
									) ) ?>">
                                        <span class="screen-reader-text"><?php esc_html_e( 'First Page', 'chinads' ) ?></span>
                                        <span aria-hidden="true">«</span>
                                    </a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
									<?php
								}

								/*Previous button*/
								if ( $per_page * $paged > $per_page ) {
									$p_paged = $paged - 1;
								} else {
									$p_paged = 0;
								}

								if ( $p_paged ) {
									$p_url = add_query_arg(
										array(
											'page'        => 'tbds-imported',
											'paged'       => $p_paged,
											'tbds_search' => $keyword,
											'post_status' => $status,
										), admin_url( 'admin.php' )
									);
									?>
                                    <a class="prev-page button" href="<?php echo esc_url( $p_url ) ?>">
                                        <span class="screen-reader-text">
	                                        <?php esc_html_e( 'Previous Page', 'chinads' ) ?>
                                        </span>
                                        <span aria-hidden="true">‹</span>
                                    </a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
									<?php
								}
								?>
                                <span class="screen-reader-text"><?php esc_html_e( 'Current Page', 'chinads' ) ?></span>
                                <span id="table-paging" class="paging-input">
                                    <span class="tablenav-paging-text">
                                        <input class="current-page" type="text" name="paged" size="1" value="<?php echo esc_html( $paged ) ?>">
	                                    <span class="tablenav-paging-text"><?php esc_html_e( ' of ', 'chinads' ) ?>
                                            <span class="total-pages"><?php echo esc_html( $total_page ) ?></span>
                                        </span>
                                    </span>
                                </span>

								<?php /*Next button*/
								if ( $per_page * $paged < $count ) {
									$n_paged = $paged + 1;
								} else {
									$n_paged = 0;
								}

								if ( $n_paged ) {
									$n_url = add_query_arg(
										array(
											'page'        => 'tbds-imported',
											'paged'       => $n_paged,
											'tbds_search' => $keyword,
											'post_status' => $status,
										), admin_url( 'admin.php' )
									); ?>
                                    <a class="next-page button" href="<?php echo esc_url( $n_url ) ?>">
                                        <span class="screen-reader-text"><?php esc_html_e( 'Next Page', 'chinads' ) ?></span>
                                        <span aria-hidden="true">›</span>
                                    </a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
									<?php
								}

								if ( $total_page > $paged + 1 ) {
									?>
                                    <a class="next-page button" href="<?php echo esc_url( add_query_arg(
										array(
											'page'        => 'tbds-imported',
											'paged'       => $total_page,
											'tbds_search' => $keyword,
											'post_status' => $status,
										), admin_url( 'admin.php' )
									) ) ?>">
                                        <span class="screen-reader-text"><?php esc_html_e( 'Last Page', 'chinads' ) ?></span>
                                        <span aria-hidden="true">»</span>
                                    </a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
									<?php
								}
								?>
                            </div>
                        </div>
                        <p class="search-box">
                            <input type="search" class="text short" name="tbds_search" value="<?php echo esc_attr( $keyword ) ?>"
                                   placeholder="<?php esc_attr_e( 'Search imported product', 'chinads' ) ?>">
                            <input type="submit" name="submit" class="button" value="<?php echo esc_attr__( 'Search product', 'chinads' ) ?>">
                        </p>
                    </div>
                </form>

				<?php
				$pagination_html = ob_get_clean();
				$allow_html      = array_merge( wp_kses_allowed_html( 'post' ), [
					'input' => [
						'type'         => 1,
						'id'           => 1,
						'name'         => 1,
						'class'        => 1,
						'placeholder'  => 1,
						'autocomplete' => 1,
						'style'        => 1,
						'value'        => 1,
						'size'         => 1,
						'data-*'       => 1,
					],
					'form'  => [
						'method' => 1,
						'class'  => 1,
					]
				] );

				echo wp_kses( $pagination_html, $allow_html );
				$key = 0;

				foreach ( $product_ids as $product_id ) {
					$product            = Taobao_Post::get_post( $product_id );
					$woo_product_id     = Taobao_Post::get_post_meta( $product_id, '_tbds_woo_id', true );
					$title              = $product->post_title;
					$woo_product        = wc_get_product( $woo_product_id );
					$woo_product_status = '';
					$woo_product_name   = $title;
					$sku                = Taobao_Post::get_post_meta( $product_id, '_tbds_sku', true );
					$host               = Taobao_Post::get_post_meta( $product_id, '_tbds_taobao_host', true );
					$woo_sku            = $sku;

					if ( $woo_product ) {
						$woo_sku            = $woo_product->get_sku();
						$woo_product_status = $woo_product->get_status();
						$woo_product_name   = $woo_product->get_name();
					}

					$gallery            = Taobao_Post::get_post_meta( $product_id, '_tbds_gallery', true );
					$store_info         = Taobao_Post::get_post_meta( $product_id, '_tbds_store_info', true );
					$variations         = Taobao_Post::get_post_meta( $product_id, '_tbds_variations', true );
					$overriding_product = Taobao_Post::get_overriding_product( $product_id );
					$accordion_active   = $overriding_product ? 'active' : '';
					$image              = wp_get_attachment_thumb_url( Taobao_Post::get_post_meta( $product_id, '_tbds_product_image', true ) );

					if ( ! $image ) {
						$image = ( is_array( $gallery ) && count( $gallery ) ) ? array_shift( $gallery ) : '';
					}

					?>
                    <div class="vi-ui styled fluid accordion tbds-accordion" id="tbds-product-item-id-<?php echo esc_attr( $product_id ) ?>">
                        <div class="title <?php echo esc_attr( $accordion_active ) ?>">
                            <i class="dropdown icon tbds-accordion-title-icon"> </i>
                            <div class="tbds-accordion-product-image-title-container">
                                <div class="tbds-accordion-product-image-title">
                                    <img src="<?php echo esc_url( $image ? $image : wc_placeholder_img_src() ) ?>"
                                         class="tbds-accordion-product-image">
                                    <div class="tbds-accordion-product-title-container">
                                        <div class="tbds-accordion-product-title" title="<?php echo esc_attr( $title ) ?>">
											<?php echo esc_html( $title ) ?>
                                        </div>
										<?php
										if ( ! empty( $store_info['name'] ) ) {
											$store_name = $store_info['name'];

											esc_html_e( 'Store: ', 'chinads' );

											if ( ! empty( $store_info['url'] ) ) {
												printf( "<a class='tbds-accordion-store-url' href='%s' target='_blank'>%s</a>", esc_url( $store_info['url'] ), esc_html( $store_name ) );
											} else {
												echo esc_html( $store_name );
											}
										}
										?>
                                    </div>
                                </div>
                                <div class="tbds-button-view-and-edit">
                                    <a href="<?php echo esc_url( "{$host}/item.htm?id={$sku}" ); ?>" target="_blank" class="vi-ui mini button" rel="nofollow">
										<?php esc_html_e( 'View on Taobao', 'chinads' ) ?>
                                    </a>

									<?php
									if ( $woo_product ) {
										if ( $woo_product_status !== 'trash' ) {
											echo wp_kses_post( Utils::get_button_view_edit_html( $woo_product_id ) );
										} else {

											if ( $status !== 'trash' ) {
												?>
                                                <span class="vi-ui mini black button tbds-button-trash"
                                                      title="<?php esc_attr_e( 'This product is trashed from your WooCommerce store.', 'chinads' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="">
	                                                <?php esc_html_e( 'Trash', 'chinads' ) ?>
                                                </span>
                                                <span class="vi-ui mini button negative tbds-button-delete"
                                                      title="<?php esc_attr_e( 'Delete this product permanently', 'chinads' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>">
	                                                <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                                </span>
												<?php
											} else {
												?>
                                                <span class="vi-ui mini button positive tbds-button-restore"
                                                      title="<?php esc_attr_e( 'Restore this product', 'chinads' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>">
	                                                <?php esc_html_e( 'Restore', 'chinads' ) ?>
                                                </span>
                                                <span class="vi-ui mini button negative tbds-button-delete"
                                                      title="<?php esc_attr_e( 'Delete this product permanently', 'chinads' ) ?>"
                                                      data-product_title="<?php echo esc_attr( $title ) ?>"
                                                      data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                      data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>">
	                                                <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                                </span>
												<?php
											}
										}
									} else {
										if ( $status !== 'trash' ) {
											?>
                                            <span class="vi-ui mini black button tbds-button-trash"
                                                  title="<?php esc_attr_e( 'This product is deleted from your WooCommerce store.', 'chinads' ) ?>"
                                                  data-product_title="<?php echo esc_attr( $title ) ?>"
                                                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                  data-woo_product_id="">
	                                            <?php esc_html_e( 'Trash', 'chinads' ) ?>
                                            </span>
                                            <span class="vi-ui mini button negative tbds-button-delete"
                                                  title="<?php esc_attr_e( 'Delete this product permanently', 'chinads' ) ?>"
                                                  data-product_title="<?php echo esc_attr( $title ) ?>"
                                                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                  data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>">
	                                            <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                            </span>
											<?php
										} else {
											?>
                                            <span class="vi-ui button mini negative tbds-button-delete"
                                                  title="<?php esc_attr_e( 'Delete this product permanently', 'chinads' ) ?>"
                                                  data-product_title="<?php echo esc_attr( $title ) ?>"
                                                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                  data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>">
	                                            <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                            </span>
											<?php
										}
									}
									?>
                                    <span class="vi-ui button negative mini loading tbds-button-deleting">
	                                    <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="content <?php echo esc_attr( $accordion_active ) ?>">
							<?php
							if ( $overriding_product ) {
								$overriding_product_title = Taobao_Post::get_the_title( $overriding_product );
								?>
                                <div class="vi-ui message">
                                    <span>
                                        <?php
                                        printf( "%s: <strong>%s</strong>. %s <a target='_blank' href='%s'>%s</a>",
	                                        esc_html__( 'This product is being overridden by:', 'chinads' ),
	                                        esc_html( $overriding_product_title ),
	                                        esc_html__( 'Please go to', 'chinads' ),
	                                        esc_url( admin_url( 'admin.php?page=tbds-import-list&tbds_search=' . urlencode( $overriding_product_title ) ) ),
	                                        esc_html__( 'Import list', 'chinads' )
                                        )
                                        ?>
                                    </span>
                                </div>
								<?php
							}
							?>
                            <div class="tbds-message"></div>
                            <form class="vi-ui form tbds-product-container"
                                  method="post">
                                <div class="field">
                                    <div class="fields">
                                        <div class="three wide field">
                                            <div class="tbds-product-image">
                                                <img style="width: 100%" class="tbds-import-data-image"
                                                     src="<?php echo esc_url( $image ? $image : wc_placeholder_img_src() ) ?>">
                                                <input type="hidden" name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][image]' ) ?>"
                                                       value="<?php echo esc_attr( $image ? $image : wc_placeholder_img_src() ) ?>">
                                            </div>
                                        </div>
                                        <div class="thirteen wide field">
                                            <div class="field">
                                                <label><?php esc_html_e( 'WooCommerce product title' ) ?></label>
                                                <input type="text" value="<?php echo esc_attr( $woo_product_name ) ?>" readonly
                                                       name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][title]' ) ?>"
                                                       class="tbds-import-data-title">
                                            </div>
                                            <div class="field">
                                                <div class="equal width fields">
                                                    <div class="field">
                                                        <label><?php esc_html_e( 'Sku', 'chinads' ) ?></label>
                                                        <input type="text" value="<?php echo esc_attr( $woo_sku ) ?>" readonly
                                                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][sku]' ) ?>"
                                                               class="tbds-import-data-sku">
                                                    </div>
                                                    <div class="field">
                                                        <label><?php esc_html_e( 'Cost', 'chinads' ) ?></label>
                                                        <div class="tbds-price-field">
															<?php
															if ( count( $variations ) == 1 ) {
																$variation_sale_price    = ( $variations[0]['sale_price'] );
																$variation_regular_price = ( $variations[0]['regular_price'] );
																$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
																echo wp_kses_post( wc_price( $price, [ 'currency' => 'CNY', 'price_format' => '%1$s&nbsp;%2$s' ] ) );
															} else {
																$min_price = 0;
																$max_price = 0;
																foreach ( $variations as $variation_k => $variation_v ) {
																	$variation_sale_price    = ( $variation_v['sale_price'] ?? '' );
																	$variation_regular_price = ( $variation_v['regular_price'] ?? '' );
																	$price                   = $variation_sale_price ? $variation_sale_price : $variation_regular_price;
																	if ( ! $min_price ) {
																		$min_price = $price;
																	}
																	if ( $price < $min_price ) {
																		$min_price = $price;
																	}
																	if ( $price > $max_price ) {
																		$max_price = $price;
																	}
																}
																if ( $min_price && $min_price != $max_price ) {
																	echo wp_kses_post( wc_price( $min_price, [ 'currency' => 'CNY', 'price_format' => '%1$s&nbsp;%2$s' ] )
																	                   . ' - ' . wc_price( $max_price, [ 'currency' => 'CNY', 'price_format' => '%1$s&nbsp;%2$s' ] ) );
																} elseif ( $max_price ) {
																	echo wp_kses_post( wc_price( $max_price, [ 'currency' => 'CNY', 'price_format' => '%1$s&nbsp;%2$s' ] ) );
																}
															}
															?>
                                                        </div>
                                                    </div>
													<?php
													if ( $woo_product && $woo_product_status !== 'trash' ) {
														?>
                                                        <div class="field">
                                                            <label><?php esc_html_e( 'WooCommerce Price', 'chinads' ) ?></label>
                                                            <div class="tbds-price-field">
																<?php echo wp_kses_post( $woo_product->get_price_html() ); ?>
                                                            </div>
                                                        </div>
														<?php
													}
													?>
                                                </div>
                                            </div>

                                            <div class="field">
                                                <div class="equal width fields">
                                                    <div class="field">
                                                        <div class="tbds-button-override-container">
															<?php
															if ( $status !== 'trash' ) {
																if ( $woo_product && $woo_product_status !== 'trash' ) {
																	?>
                                                                    <span class="vi-ui mini button negative tbds-button-delete"
                                                                          title="<?php esc_attr_e( 'Delete this product permanently', 'chinads' ) ?>"
                                                                          data-product_title="<?php echo esc_attr( $title ) ?>"
                                                                          data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                                          data-woo_product_id="<?php echo esc_attr( $woo_product ? $woo_product_id : '' ) ?>">
	                                                                    <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                                                    </span>
																	<?php
																	if ( ! $overriding_product ) {
																		?>
                                                                        <span class="vi-ui mini button positive tbds-button-override"
                                                                              title="<?php esc_attr_e( 'Override this product', 'chinads' ) ?>"
                                                                              data-product_title="<?php echo esc_attr( $title ) ?>"
                                                                              data-product_id="<?php echo esc_attr( $product_id ) ?>"
                                                                              data-woo_product_id="<?php echo esc_attr( $woo_product_id ) ?>">
                                                                            <?php esc_html_e( 'Override', 'chinads' ) ?>
                                                                        </span>
																		<?php
																	} else {
//																		echo self::button_override_html( $product_id, $overriding_product );
																	}
																}
															}
															?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
					<?php
					$key ++;
				}
				echo wp_kses( $pagination_html, $allow_html );
			}

			?>
        </div>
		<?php
		$this->delete_product_options();
	}

	public function delete_product_options() {
		?>
        <div class="tbds-delete-product-options-container tbds-hidden">
            <div class="tbds-overlay"></div>
            <div class="tbds-delete-product-options-content">
                <div class="tbds-delete-product-options-content-header">
                    <h2 class="tbds-delete-product-options-content-header-delete tbds-hidden">
						<?php esc_html_e( 'Delete: ', 'chinads' ) ?>
                        <span class="tbds-delete-product-options-product-title"> </span>
                    </h2>
                    <span class="tbds-delete-product-options-close"> </span>
                    <h2 class="tbds-delete-product-options-content-header-override tbds-hidden">
						<?php esc_html_e( 'Override: ', 'chinads' ) ?>
                        <span class="tbds-delete-product-options-product-title"> </span>
                    </h2>
                </div>
                <div class="tbds-delete-product-options-content-body">
                    <div class="tbds-delete-product-options-content-body-row">
                        <div class="tbds-delete-product-options-delete-woo-product-wrap tbds-hidden">
                            <input type="checkbox" <?php checked( $this->settings->get_param( 'delete_woo_product' ), 1 ) ?>
                                   value="1"
                                   id="tbds-delete-product-options-delete-woo-product"
                                   class="tbds-delete-product-options-delete-woo-product">
                            <label for="tbds-delete-product-options-delete-woo-product">
								<?php esc_html_e( 'Also delete product from your WooCommerce store.', 'chinads' ) ?>
                            </label>
                        </div>
                        <div class="tbds-delete-product-options-override-product-wrap tbds-hidden">
                            <label for="tbds-delete-product-options-override-product">
								<?php esc_html_e( 'Taobao Product URL/ID:', 'chinads' ) ?></label>
                            <input type="text"
                                   id="tbds-delete-product-options-override-product"
                                   class="tbds-delete-product-options-override-product">
                            <div class="tbds-delete-product-options-override-product-new-wrap tbds-hidden">
                                <span class="tbds-delete-product-options-override-product-new-close"> </span>
                                <div class="tbds-delete-product-options-override-product-new-image">
                                    <img src="<?php echo esc_url( TBDS_CONST['img_url'] . 'loading.gif' ) ?>">
                                </div>
                                <div class="tbds-delete-product-options-override-product-new-title"></div>
                            </div>
                        </div>
                        <div class="tbds-delete-product-options-override-product-message"></div>
                    </div>
                </div>
                <div class="tbds-delete-product-options-content-footer">
                    <span class="vi-ui button positive mini tbds-delete-product-options-button-override tbds-hidden" data-product_id="" data-woo_product_id="">
                            <?php esc_html_e( 'Check', 'chinads' ) ?>
                        </span>
                    <span class="vi-ui button mini negative tbds-delete-product-options-button-delete tbds-hidden" data-product_id="" data-woo_product_id="">
                            <?php esc_html_e( 'Delete', 'chinads' ) ?>
                        </span>
                    <span class="vi-ui button mini tbds-delete-product-options-button-cancel">
                            <?php esc_html_e( 'Cancel', 'chinads' ) ?>
                        </span>
                </div>
            </div>
            <div class="tbds-saving-overlay"></div>
        </div>
		<?php
	}

	public function delete() {
		check_ajax_referer( 'tbds_security' );
		Utils::set_time_limit();
		$product_id         = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : '';
		$woo_product_id     = isset( $_POST['woo_product_id'] ) ? absint( $_POST['woo_product_id'] ) : '';
		$delete_woo_product = isset( $_POST['delete_woo_product'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_woo_product'] ) ) : '';
		if ( $delete_woo_product != $this->settings->get_param( 'delete_woo_product' ) ) {
			$args                       = $this->settings->get_params();
			$args['delete_woo_product'] = $delete_woo_product;
			update_option( 'tbds_params', $args );
		}
		$response = array(
			'status'  => 'success',
			'message' => '',
		);
		if ( $product_id ) {
			if ( Taobao_Post::get_post( $product_id ) ) {
				$delete = Taobao_Post::delete_post( $product_id, true );
				if ( false === $delete ) {
					$response['status']  = 'error';
					$response['message'] = esc_html__( 'Can not delete product', 'chinads' );
				}
			}

			if ( $woo_product_id && wc_get_product( $woo_product_id ) ) {
				$product = wc_get_product($woo_product_id);
				$product->delete_meta_data('_tbds_taobao_product_id');
				$product->save();
				if ( 1 == $delete_woo_product ) {
					$delete = $product->delete(true);
					if ( false === $delete ) {
						$response['status']  = 'error';
						$response['message'] = esc_html__( 'Can not delete product', 'chinads' );
					}
				}
			}
		}
		wp_send_json( $response );
	}

	/**
	 * Delete imported products
	 */
	public function trash() {
		check_ajax_referer( 'tbds_security' );
		Utils::set_time_limit();
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : '';
		$response   = array(
			'status'  => 'success',
			'message' => '',
		);
		if ( $product_id ) {
			$reslut = Taobao_Post::trash_post( $product_id );
			if ( ! $reslut ) {
				$response['status']  = 'error';
				$response['message'] = esc_html__( 'Can not delete product', 'chinads' );
			}
		}
		wp_send_json( $response );
	}

	public function override_product() {
		check_ajax_referer( 'tbds_security' );
		Utils::set_time_limit();

		$override_product_url = isset( $_POST['override_product_url'] ) ? sanitize_text_field( wp_unslash( $_POST['override_product_url'] ) ) : '';
		$step                 = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';
		$product_id           = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : '';
		$response             = array(
			'status'           => 'error',
			'message'          => '',
			'image'            => '',
			'title'            => '',
			'data'             => '',
			'exist_product_id' => '',
		);

		$product_sku = $redirect_url = '';
		if ( wc_is_valid_url( $override_product_url ) ) {
			preg_match( '/\/item?.*\.htm\?(?:id|.*&id)=(\d{1,})/im', $override_product_url, $match );
			if ( $match && ! empty( $match[1] ) ) {
				$product_sku = $match[1];
			}

		} else {
			$product_sku = $override_product_url;
		}

		if ( $product_sku ) {
			if ( $product_sku == Taobao_Post::get_post_meta( $product_id, '_tbds_sku', true ) ) {
				$response['message'] = esc_html__( 'Can not override itself', 'chinads' );
			} else {
				$exist_product_id = Taobao_Post::get_post_id_by_taobao_id( $product_sku );

				if ( $step === 'check' ) {
					if ( $exist_product_id ) {
						$exist_product                = Taobao_Post::get_post( $exist_product_id );
						$response['exist_product_id'] = $exist_product_id;
						$response['title']            = $exist_product->post_title;
						$gallery                      = Taobao_Post::get_post_meta( $exist_product_id, '_tbds_gallery', true );
						$response['image']            = ( is_array( $gallery ) && count( $gallery ) ) ? $gallery[0] : wc_placeholder_img_src();
						if ( $exist_product->post_status === 'draft' ) {
							$response['status'] = 'success';
						} else if ( $exist_product->post_status === 'publish' ) {
							$response['status']  = 'exist';
							$response['message'] = esc_html__( 'This product has already been imported', 'chinads' );
						} else {
							$response['status']  = 'override';
							$response['message'] = esc_html__( 'This product is overriding an other product.', 'chinads' );
						}
					} else {
						$parsed = wp_parse_url( $override_product_url );
						$url    = $parsed['host'] . $parsed['path'];

						$params = [
							"id={$product_sku}",
							"tbds_action=override",
							"tbds_from_domain=" . urlencode( site_url() ),
							"tbds_return_url=" . urlencode( admin_url( 'admin.php?page=tbds-import-list' ) ),
							"tbds_draft_product_id={$product_id}",
						];

						$redirect_url = "https://{$url}?" . implode( '&', $params );

						$response['status']       = 'redirect';
						$response['redirect_url'] = $redirect_url;
						$response['message']      = esc_html__( 'Go to Taobao', 'chinads' );
					}
				}
			}
		} else {
			$response['message'] = esc_html__( 'Not found', 'chinads' );
		}

		wp_send_json( $response );
	}

}