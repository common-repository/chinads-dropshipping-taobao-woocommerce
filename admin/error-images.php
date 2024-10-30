<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Inc\Data;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;

class Error_Images {
	protected static $instance = null;
	protected $settings;

	public function __construct() {
		$this->settings = Data::instance();

		add_action( 'wp_ajax_tbds_download_error_product_images', [ $this, 'download_error_product_images' ] );
		add_action( 'wp_ajax_tbds_delete_error_product_images', array( $this, 'delete_error_product_images' ) );
		add_action( 'admin_init', array( $this, 'empty_list' ) );

	}

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public function page_callback() {
		$user     = get_current_user_id();
		$screen   = get_current_screen();
		$option   = $screen->get_option( 'per_page', 'option' );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty ( $per_page ) || $per_page < 1 ) {
			$per_page = $screen->get_option( 'per_page', 'default' );
		}

		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'All failed images', 'chinads' ) ?></h2>
			<?php
			$tbds_search_product_id = isset( $_GET['tbds_search_product_id'] ) ? sanitize_text_field( wp_unslash( $_GET['tbds_search_product_id'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count                  = Error_Images_Query::get_rows( 0, 0, true, $tbds_search_product_id );
			$results                = Error_Images_Query::get_rows( $per_page, ( $paged - 1 ) * $per_page, false, $tbds_search_product_id );
			if ( count( $results ) ) {
				if ( $this->settings->get_param( 'use_external_image' ) || ! $this->settings->get_param( 'download_description_images' ) ) {
					?>
                    <div class="vi-ui negative message">
                        <div><?php esc_html_e( 'Please disable "Use external links for images" and enable "Import description images" to make Import button available for Description images', 'chinads' ); ?></div>
                    </div>
					<?php
				}
				ob_start();
				?>
                <form class="vi-ui form">
                    <table class="vi-ui celled table">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Index', 'chinads' ) ?></th>
                            <th><?php esc_html_e( 'Product ID', 'chinads' ) ?></th>
                            <th><?php esc_html_e( 'Product Title', 'chinads' ) ?></th>
                            <th><?php esc_html_e( 'Product/Variation IDs', 'chinads' ) ?></th>
                            <th><?php esc_html_e( 'Image url', 'chinads' ) ?></th>
                            <th><?php esc_html_e( 'Used for', 'chinads' ) ?></th>
                            <th><?php esc_html_e( 'Actions', 'chinads' ) ?></th>
                        </tr>
                        </thead>
                        <tbody>
						<?php
						foreach ( $results as $key => $result ) {
							$product = wc_get_product( $result['product_id'] );
							if ( ! $product ) {
								?>
                                <tr>
                                    <td>
                                        <span class="tbds-index"><?php echo esc_html( $key + 1 ) ?></span>
                                    </td>

									<?php
									foreach ( $result as $result_k => $result_v ) {
										if ( $result_k === 'id' ) {
											continue;
										}
										?>
                                        <td>
										<span>
                                            <?php
                                            switch ( $result_k ) {
	                                            case 'image_src':
		                                            ?>
                                                    <img width="48" height="48" src="<?php echo esc_attr( $result_v ) ?>">
		                                            <?php
		                                            break;
	                                            case 'product_ids':
		                                            echo esc_html( str_replace( ',', ', ', $result_v ) );
		                                            break;
	                                            case 'set_gallery':
		                                            if ( $result_v == 2 ) {
			                                            esc_attr_e( 'Description', 'chinads' );
		                                            } elseif ( $result_v == 1 ) {
			                                            esc_attr_e( 'Gallery', 'chinads' );
		                                            } else {
			                                            esc_attr_e( 'Product/variation image', 'chinads' );
		                                            }
		                                            break;
	                                            default:
		                                            echo esc_html( $result_v );
                                            }
                                            ?>
                                        </span>
                                        </td>
										<?php
										if ( $result_k === 'product_id' ) {
											echo '<td>-</td>';
										}
									}
									?>
                                    <td>
                                        <div class="tbds-actions-container">
                                            <span><?php esc_html_e( 'The product this image belongs to was deleted so this image is now removed from list', 'chinads' ) ?></span>
                                        </div>
                                    </td>
                                </tr>
								<?php
								Error_Images_Query::delete( $result['id'] );
								continue;
							} else {
								?>
                                <tr>
                                    <td>
                                        <span class="tbds-index"><?php echo esc_html( $key + 1 ) ?></span>
                                    </td>
									<?php
									$hide_import_button = false;
									foreach ( $result as $result_k => $result_v ) {
										if ( $result_k === 'id' ) {
											continue;
										}
										?>
                                        <td>
										<span>
                                            <?php
                                            switch ( $result_k ) {
	                                            case 'image_src':
		                                            ?>
                                                    <img width="48" height="48" src="<?php echo esc_attr( $result_v ) ?>">
		                                            <?php
		                                            break;
	                                            case 'product_ids':
		                                            echo esc_html( str_replace( ',', ', ', $result_v ) );
		                                            break;
	                                            case 'set_gallery':
		                                            if ( $result_v == 2 ) {
			                                            esc_attr_e( 'Description', 'chinads' );
			                                            if ( $this->settings->get_param( 'use_external_image' ) || ! $this->settings->get_param( 'download_description_images' ) ) {
				                                            $hide_import_button = true;
			                                            }
		                                            } elseif ( $result_v == 1 ) {
			                                            esc_attr_e( 'Gallery', 'chinads' );
		                                            } else {
			                                            esc_attr_e( 'Product/variation image', 'chinads' );
		                                            }
		                                            break;
	                                            default:
		                                            echo esc_html( $result_v );
                                            }
                                            ?>
                                        </span>
                                        </td>
										<?php
										if ( $result_k === 'product_id' ) {
											?>
                                            <td>
                                                <a class="tbds-product-title" target="_blank"
                                                   href="<?php echo esc_attr( admin_url( 'post.php?action=edit&post=' . $result['product_id'] ) ) ?>">
													<?php echo esc_html( $product->get_title() ) ?>
                                                </a>
                                            </td>
											<?php
										}
									}
									?>
                                    <td>
                                        <div class="tbds-actions-container">
											<?php
											if ( ! $hide_import_button ) {
												?>
                                                <span class="vi-ui positive button tbds-action-download" data-item_id="<?php echo esc_attr( $result['id'] ) ?>">
                                                    <?php esc_html_e( 'Import', 'chinads' ) ?>
                                                </span>
												<?php
											}
											?>
                                            <span class="vi-ui negative button tbds-action-delete" data-item_id="<?php echo esc_attr( $result['id'] ) ?>">
                                                <?php esc_html_e( 'Delete', 'chinads' ) ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
								<?php
							}
						}
						?>
                        </tbody>
                    </table>
                </form>

				<?php
				$image_list = ob_get_clean();

				ob_start();
				?>

                <form method="get">
                    <input type="hidden" name="page" value="tbds-error-images">
                    <div class="tablenav top">
                        <div class="tbds-button-all-container">
                            <span class="vi-ui button positive tbds-action-download-all"><?php esc_html_e( 'Import All', 'chinads' ) ?></span>
                            <span class="vi-ui button negative tbds-action-delete-all"><?php esc_html_e( 'Delete All', 'chinads' ) ?></span>
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'tbds_empty_error_images', 1 ) ) ) ?>"
                               class="vi-ui button negative tbds-action-empty-error-images"
                               title="<?php esc_attr_e( 'Remove all failed images from database', 'chinads' ) ?>"><?php esc_html_e( 'Empty List', 'chinads' ) ?></a>
                        </div>
                        <div class="tablenav-pages">
                            <div class="pagination-links">
								<?php
								$total_page = ceil( $count / $per_page );

								/*Previous button*/
								$p_paged = $per_page * $paged > $per_page ? $paged - 1 : 0;

								if ( $p_paged ) {
									$p_url = add_query_arg(
										[ 'page' => 'tbds-error-images', 'paged' => $p_paged, 'tbds_search_product_id' => $tbds_search_product_id, ],
										admin_url( 'admin.php' ) );
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
								$n_paged = $per_page * $paged < $count ? $paged + 1 : 0;
								if ( $n_paged ) {
									$n_url = add_query_arg( [ 'page' => 'tbds-error-images', 'paged' => $n_paged, 'tbds_search_product_id' => $tbds_search_product_id ], admin_url( 'admin.php' ) ); ?>
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
								?>
                            </div>
                        </div>

						<?php
						$products = Error_Images_Query::get_products_ids();

						if ( count( $products ) < 100 ) {
							if ( count( $products ) > 1 ) {
								$product_options = [];

								foreach ( $products as $product_id ) {
									$product = wc_get_product( $product_id );
									if ( $product ) {
										$product_options[ $product_id ] = "(#{$product_id}){$product->get_title()}";
									}
								}

								if ( ! empty( $product_options ) ) {
									?>
                                    <p class="search-box">
                                        <select name="tbds_search_product_id" class="tbds-search-product-id">
                                            <option value=""><?php esc_html_e( 'Filter by product', 'chinads' ) ?></option>
											<?php
											foreach ( $product_options as $pid => $title ) {
												printf( "<option value='%s' %s>%s</option>", esc_attr( $pid ), selected( $pid, $tbds_search_product_id, false ), esc_html( $title ) );
											}
											?>
                                        </select>
                                    </p>
									<?php
								}
							}
						} else {
							?>
                            <p class="search-box">
                                <select name="tbds_search_product_id" class="tbds-search-product-id-ajax">
									<?php
									if ( $tbds_search_product_id ) {
										$product = wc_get_product( $tbds_search_product_id );
										if ( $product ) {
											?>
                                            <option value="<?php echo esc_attr( $tbds_search_product_id ) ?>" selected>
												<?php echo esc_html( "(#{$tbds_search_product_id}){$product->get_title()}" ) ?>
                                            </option>
											<?php
										}
									}
									?>
                                </select>
                            </p>
							<?php
						}
						?>
                    </div>
                </form>

				<?php
				$pagination_html = ob_get_clean();
				echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $image_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				?>
                <div class="vi-ui segment">
                    <p>
						<?php esc_html_e( "You don't have any failed images.", 'chinads' ) ?>
                    </p>
                </div>
				<?php
			}
			wp_reset_postdata();
			?>
        </div>
		<?php
	}

	public function download_error_product_images() {
        $response = [ 'status' => 'error', 'message' => 'Error' ];
        if (!wp_verify_nonce($_POST['_ajax_nonce']??'', 'tbds_security')){
            $response['message'] = 'invalid nonce';
            wp_send_json($response);
        }
		Utils::set_time_limit();

		$id       = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';


		if ( $id ) {
			$data = Error_Images_Query::get_row( $id );

			if ( ! empty( $data ) ) {
				$product_id = $data['product_id'];
				$post       = get_post( $product_id );

				if ( $post && $post->post_type === 'product' ) {
					if ( $data['set_gallery'] != 2 || ( ! $this->settings->get_param( 'use_external_image' ) && $this->settings->get_param( 'download_description_images' ) ) ) {
						$thumb_id = Utils::download_image( $image_id, $data['image_src'], $product_id );

						if ( $thumb_id && ! is_wp_error( $thumb_id ) ) {
							if ( $data['set_gallery'] == 2 ) {
								$downloaded_url = wp_get_attachment_url( $thumb_id );
								$description    = html_entity_decode( $post->post_content, ENT_QUOTES | ENT_XML1, 'UTF-8' );
								$description    = preg_replace( '/[^"]{0,}' . preg_quote( $image_id, '/' ) . '[^"]{0,}/U', $downloaded_url, $description );
								$description    = str_replace( $data['image_src'], $downloaded_url, $description );
								wp_update_post( array( 'ID' => $product_id, 'post_content' => $description ) );
							} else {
								if ( $data['product_ids'] ) {
									$product_ids = explode( ',', $data['product_ids'] );
									foreach ( $product_ids as $v_id ) {
										if ( in_array( get_post_type( $v_id ), [ 'product', 'product_variation' ] ) ) {
											update_post_meta( $v_id, '_thumbnail_id', $thumb_id );
										}
									}
								}

								if ( 1 == $data['set_gallery'] ) {
									$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
									if ( $gallery ) {
										$gallery_array = explode( ',', $gallery );
									} else {
										$gallery_array = array();
									}
									$gallery_array[] = $thumb_id;
									update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_unique( $gallery_array ) ) );
								}
							}
							$response['status'] = 'success';
							Error_Images_Query::delete( $id );
						} else {
							$response['message'] = $thumb_id->get_error_message();
						}
					} else {
						$response['message'] = esc_html__( 'Please disable "Use external links for images" and enable "Import description images"', 'chinads' );
					}
				} else {
					$response['message'] = esc_html__( 'Product does not exist', 'chinads' );
				}
			} else {
				$response['message'] = esc_html__( 'Not found', 'chinads' );
			}
		}
		wp_send_json( $response );
	}

	public function delete_error_product_images() {
		$response = [ 'status' => 'error', 'message' => 'Error' ];
		if (!wp_verify_nonce($_POST['_ajax_nonce']??'', 'tbds_security')){
			$response['message'] = 'invalid nonce';
			wp_send_json($response);
		}
		Utils::set_time_limit();

		$id       = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : '';
		if ( $id ) {
			$delete = Error_Images_Query::delete( $id );
			if ( $delete ) {
				$response['status'] = 'success';
			} else {
				$response['message'] = esc_html__( 'Can not remove image from list', 'chinads' );
			}
		} else {
			$response['message'] = esc_html__( 'Not found', 'chinads' );
		}
		wp_send_json( $response );
	}

	public function empty_list() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! empty( $_GET['tbds_empty_error_images'] ) && $page === 'tbds-error-images' ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ) ) ) {
				global $wpdb;
				$wpdb->query( "DELETE from {$wpdb->prefix}tbds_error_product_images" );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery , WordPress.DB.DirectDatabaseQuery.NoCaching
				wp_safe_redirect( admin_url( "admin.php?page={$page}" ) );
				exit();
			}
		}
	}
}
