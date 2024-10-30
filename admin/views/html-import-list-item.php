<?php
namespace TaobaoDropship\Admin\Views;

use TaobaoDropship\Admin\Taobao_Products_Table;
use TaobaoDropship\Inc\Taobao_Post;
use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;

?>

<div class="<?php echo esc_attr( implode( ' ', $accordion_class ) ) ?>" id="tbds-product-item-id-<?php echo esc_attr( $product_id ) ?>">
    <div class="title active">
        <input type="checkbox" class="tbds-accordion-bulk-item-check">
        <i class="dropdown icon tbds-accordion-title-icon"> </i>

        <div class="tbds-accordion-product-image-title-container">
            <div class="tbds-accordion-product-image-title">
                <img src="<?php echo esc_url( $image ? $image : wc_placeholder_img_src() ) ?>" class="tbds-accordion-product-image">
                <div class="tbds-accordion-product-title-container">
                    <div class="tbds-accordion-product-title" title="<?php echo esc_attr( $product->post_title ) ?>"><?php echo esc_html( $product->post_title ) ?></div>
					<?php
					if ( ! empty( $store_info['name'] ) ) {
						$store_name = $store_info['name'];
						if ( ! empty( $store_info['url'] ) ) {
							$store_name = '<a class="tbds-accordion-store-url" href="' . esc_attr( $store_info['url'] ) . '" target="_blank">' . $store_name . '</a>';
						}
						?>
                        <div>
							<?php
							esc_html_e( 'Store: ', 'chinads' );
							echo wp_kses_post( $store_name );
							?>
                        </div>
						<?php
					}
					?>
                    <div class="tbds-accordion-product-date">
						<?php esc_html_e( 'Date: ', 'chinads' ) ?>
                        <span><?php echo esc_html( $product->post_date ) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="tbds-button-view-and-edit">
            <span class="vi-ui button mini blue icon tbds-translate-title-n-attributes" title="<?php esc_html_e( 'Translate both title & attributes', 'chinads' ); ?>">
                <i class="tbds-g_translate"> </i>
            </span>

            <a href="<?php echo esc_url( "{$host}/item.htm?id={$sku}" ); ?>"
               target="_blank" class="vi-ui button mini" rel="nofollow"
               title="<?php esc_attr_e( 'View this product on Taobao.com', 'chinads' ) ?>">
				<?php esc_html_e( 'View on Taobao', 'chinads' ) ?></a>
            <span class="vi-ui button mini negative tbds-button-remove"
                  data-product_id="<?php echo esc_attr( $product_id ) ?>"
                  title="<?php esc_attr_e( 'Remove this product from import list', 'chinads' ) ?>"><?php esc_html_e( 'Remove', 'chinads' ) ?></span>
			<?php
			if ( $override_product ) {
				?>
                <span class="vi-ui button mini positive tbds-button-override" data-product_id="<?php echo esc_attr( $product_id ) ?>" data-override_product_id="<?php echo esc_attr( $override_product_id ) ?>">
                    <?php esc_html_e( 'Import & Override', 'chinads' ) ?>
                </span>
				<?php
			} else {
				?>
                <span class="vi-ui button mini positive tbds-button-import" data-product_id="<?php echo esc_attr( $product_id ) ?>"
                      title="<?php esc_attr_e( 'Import this product to your WooCommerce store', 'chinads' ) ?>">
                    <?php esc_html_e( 'Import Now', 'chinads' ) ?>
                </span>
                <span class="vi-ui button mini positive tbds-button-override tbds-button-map-existing tbds-hidden"
                      title="<?php esc_attr_e( 'Import this product to your WooCommerce store', 'chinads' ) ?>"
                      data-product_id="<?php echo esc_attr( $product_id ) ?>" data-override_product_id="<?php echo esc_attr( $override_product_id ) ?>">
                    <?php esc_html_e( 'Import & Map', 'chinads' ) ?>
                </span>
				<?php
			}
			?>
        </div>
    </div>

    <div class="content active">
		<?php
		if ( $override_product ) {
			?>
            <div class="vi-ui message tbds-override-product-message">
				<?php esc_html_e( 'This product will override: ', 'chinads' ) ?>
                <strong class="tbds-override-product-product-title">
					<?php echo esc_html( $override_product->post_title ) ?>
                </strong>
            </div>
			<?php
		}
		?>
        <div class="tbds-message"></div>
		<?php
		if ( $price_alert ) {
			?>
            <div class="vi-ui warning message">
				<?php esc_html_e( 'First-purchase discount may apply to this product, please check its price carefully or import with consideration.', 'chinads' ); ?>
            </div>
			<?php
		}
		do_action( 'tbds_import_list_product_message', $product );
		?>
        <form class="vi-ui form tbds-product-container" method="post">
            <div class="vi-ui attached tabular menu">
                <div class="item active" data-tab="<?php echo esc_attr( 'product-' . $key ) ?>">
					<?php esc_html_e( 'Product', 'chinads' ) ?>
                </div>
                <div class="item tbds-description-tab-menu"
                     data-tab="<?php echo esc_attr( 'description-' . $key ) ?>">
					<?php esc_html_e( 'Description', 'chinads' ) ?>
                </div>

				<?php
				if ( $is_variable ) {
					?>
                    <div class="item tbds-attributes-tab-menu"
                         data-tab="<?php echo esc_attr( 'attributes-' . $key ) ?>">
						<?php esc_html_e( 'Attributes', 'chinads' ) ?>
                    </div>
                    <div class="item tbds-variations-tab-menu"
                         data-tab="<?php echo esc_attr( 'variations-' . $key ) ?>">
						<?php
						printf( '%s(<span class="tbds-selected-variation-count">%s</span>)', esc_html__( 'Variations', 'chinads' ), esc_html( count( $variations ) ) ); ?>
                    </div>
					<?php
				}

				if ( ! empty( $gallery ) ) {
					$gallery_count = $default_select_image ? count( $gallery ) : 0;
					?>
                    <div class="item tbds-lazy-load tbds-gallery-tab-menu" data-tab="<?php echo esc_attr( 'gallery-' . $key ) ?>">
						<?php
						printf( '%s(<span class="tbds-selected-gallery-count">%s</span>)', esc_html__( 'Gallery', 'chinads' ), esc_html( $gallery_count ) );
						?>
                    </div>
					<?php
				}
				?>
            </div>
            <div class="vi-ui bottom attached tab segment active tbds-product-tab" data-tab="<?php echo esc_attr( 'product-' . $key ) ?>">
                <div class="field">
                    <div class="fields">
                        <div class="three wide field">
                            <div class="tbds-product-image <?php echo( $default_select_image ? 'tbds-selected-item' : '' ); ?>">
                                <span class="tbds-selected-item-icon-check"> </span>
								<?php
								if ( $image ) {
									?>
                                    <img style="width: 100%" src="<?php echo esc_url( $image ) ?>" class="tbds-import-data-image">
                                    <input type="hidden" name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][image]' ) ?>" value="<?php echo esc_attr( $default_select_image ? $image : '' ) ?>">
									<?php
								} else {
									?>
                                    <img style="width: 100%" src="<?php echo esc_url( wc_placeholder_img_src() ) ?>" class="tbds-import-data-image">
                                    <input type="hidden" name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][image]' ) ?>" value="">
									<?php
								}
								?>

                            </div>
                        </div>
                        <div class="thirteen wide field">
                            <div class="field">
                                <label><?php esc_html_e( 'Product title', 'chinads' ) ?></label>
                                <div class="vi-ui right labeled input">
                                    <input type="text" value="<?php echo esc_attr( $product->post_title ) ?>" class="tbds-import-data-title"
                                           name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][title]' ) ?>">
                                    <div class="vi-ui basic label">
                                        <span class="tbds-title-translate-btn">
                                            <i class="tbds-g_translate"> </i>
                                        </span>
                                    </div>
                                </div>

                            </div>
                            <div class="field tbds-import-data-sku-status-visibility">
                                <div class="equal width fields">
                                    <div class="field">
                                        <label><?php esc_html_e( 'Sku', 'chinads' ) ?></label>
                                        <input type="text" value="<?php echo esc_attr( $sku ) ?>" class="tbds-import-data-sku"
                                               name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][sku]' ) ?>">
                                    </div>
                                    <div class="field">
                                        <label><?php esc_html_e( 'Product status', 'chinads' ) ?></label>
                                        <select name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][status]' ) ?>" class="tbds-import-data-status vi-ui fluid dropdown">
											<?php
											foreach ( $product_status_options as $value => $text ) {
												printf( "<option value='%s' %s>%s</option>", esc_attr( $value ), selected( $product_status, $value, false ), esc_html( $text ) );
											}
											?>
                                        </select>

                                    </div>
                                    <div class="field">
                                        <label><?php esc_html_e( 'Catalog visibility', 'chinads' ) ?></label>
                                        <select name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][catalog_visibility]' ) ?>" class="tbds-import-data-catalog-visibility vi-ui fluid dropdown">
											<?php
											foreach ( $catalog_visibility_options as $value => $text ) {
												printf( "<option value='%s' %s>%s</option>", esc_attr( $value ), selected( $catalog_visibility, $value, false ), esc_html( $text ) );
											}
											?>
                                        </select>
                                    </div>
                                </div>
                            </div>
							<?php
							if ( ! $is_variable ) {
								$this->simple_product_price_field_html( $key, $manage_stock, $variations, $use_different_currency, $currency, $product_id, $woocommerce_currency_symbol, $decimals );
							}
							?>
                            <div class="field">
                                <div class="equal width fields">
                                    <div class="field">
                                        <label><?php esc_html_e( 'Categories', 'chinads' ) ?></label>
                                        <select name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][categories][]' ) ?>" multiple class="'vi-ui dropdown search tbds-import-data-categories">
											<?php
											if ( ! empty( $category_options ) ) {
												foreach ( $category_options as $cat_id => $cat_name ) {
													printf( "<option value='%s' %s>%s</option>",
														esc_attr( $cat_id ),
														selected( in_array( $cat_id, $product_categories ), 1, false ),
														esc_html( $cat_name ) );
												}
											}
											?>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label><?php esc_html_e( 'Tags', 'chinads' ) ?></label>
                                        <select name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][tags][]' ) ?>" class="vi-ui dropdown search tbds-import-data-tags" multiple>
											<?php
											if ( ! empty( $tags_options ) ) {
												foreach ( $tags_options as $tag ) {
													printf( "<option value='%s' %s>%s</option>",
														esc_attr( $tag ),
														selected( in_array( $tag, $product_tags ), 1, false ),
														esc_html( $tag ) );
												}
											}
											?>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label><?php esc_html_e( 'Shipping class', 'chinads' ) ?></label>
                                        <select name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][shipping_class]' ) ?>" class="vi-ui dropdown search tbds-import-data-shipping-class">
											<?php
											if ( ! empty( $shipping_class_options ) ) {
												foreach ( $shipping_class_options as $term_id => $shipping_class ) {
													printf( "<option value='%s' %s>%s</option>",
														esc_attr( $term_id ), selected( $term_id, $product_shipping_class, false ), esc_html( $shipping_class ) );
												}
											}
											?>
                                        </select>
                                    </div>
                                </div>
                            </div>
							<?php
							if ( ! $override_product ) {
								?>
                                <div class="field">
                                    <div class="equal width fields">
                                        <div class="field">
                                            <label><?php esc_html_e( 'Map existing Woo product', 'chinads' ) ?></label>
                                            <select name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][override_woo_id]' ) ?>" class="search-product tbds-override-woo-id">
                                            </select>
                                        </div>
                                    </div>
                                </div>
								<?php
							}
							?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="vi-ui bottom attached tab segment tbds-description-tab" data-tab="<?php echo esc_attr( 'description-' . $key ) ?>">
				<?php
				$desc_url    = Taobao_Post::get_post_meta( $product_id, '_tbds_delay_desc_url', true );
				$description = $product->post_content;
				if ( $desc_url && $description === 'delay_desc' ) {
					$description = '';
					$request     = wp_safe_remote_get( $desc_url );
					if ( ! is_wp_error( $request ) && ! empty( $request['body'] ) ) {
						global $wpdb;
						$description = preg_replace( '/<script\>[\s\S]*?<\/script>/im', '', $request['body'] );
						$description = preg_replace( '/<div class="hlg_rand.*?<\/div>/i', '', $description );
						$description = Taobao_Products_Table::strip_invalid_text( $wpdb->tbds_posts, 'post_content', wp_kses_post( $description ) );
					}
				}
				wp_editor( $description, 'tbds-product-description-' . $product_id,
					[
						'default_editor' => 'html',
						'media_buttons'  => false,
						'editor_class'   => 'tbds-import-data-description',
						'textarea_name'  => 'tbds_product[' . esc_attr( $product_id ) . '][description]',
					]
				);
				?>
            </div>
			<?php
			if ( $is_variable ) {

				?>
                <div class="vi-ui bottom attached tab segment tbds-attributes-tab" data-tab="<?php echo esc_attr( 'attributes-' . $key ) ?>" data-product_id="<?php echo esc_attr( $product_id ) ?>">
                    <table class="vi-ui celled table">
                        <thead>
                        <tr>
                            <th class="tbds-attributes-attribute-col-position">
								<?php esc_html_e( 'Position', 'chinads' ) ?>
                            </th>
                            <th class="tbds-attributes-attribute-col-name">
								<?php esc_html_e( 'Name', 'chinads' ) ?>
                            </th>
                            <th class="tbds-attributes-attribute-col-slug">
								<?php esc_html_e( 'Slug', 'chinads' ) ?>
                            </th>
                            <th class="tbds-attributes-attribute-col-values">
								<?php esc_html_e( 'Values', 'chinads' ) ?>
                            </th>
                            <th class="tbds-attributes-attribute-col-action">
								<?php esc_html_e( 'Action', 'chinads' ) ?>
                            </th>
                        </tr>
                        </thead>
                        <tbody class="ui sortable">
						<?php
						$position = 1;
						foreach ( $attributes as $attributes_key => $attribute ) {
							$attribute_name = isset( $attribute['name'] ) ? $attribute['name'] : Utils::get_attribute_name_by_slug( $attribute['slug'] );
							?>
                            <tr class="tbds-attributes-attribute-row">
                                <td><?php echo esc_html( $position ) ?></td>
                                <td>
                                    <input type="text" class="tbds-attributes-attribute-name" value="<?php echo esc_attr( $attribute_name ) ?>"
                                           data-attribute_name="<?php echo esc_attr( $attribute_name ) ?>"
                                           name="<?php echo esc_attr( "tbds_product[{$product_id}][attributes][{$attributes_key}][name]" ) ?>">
                                </td>
                                <td>
                                    <span class="tbds-attributes-attribute-slug" data-attribute_slug="<?php echo esc_attr( $attribute['slug'] ) ?>">
                                        <?php echo esc_html( $attribute['slug'] ) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="tbds-attributes-attribute-values">
										<?php
										foreach ( $attribute['values'] as $values_k => $values_v ) {
											?>
                                            <input type="text" class="tbds-attributes-attribute-value"
                                                   value="<?php echo esc_attr( $values_v ) ?>" data-attribute_value="<?php echo esc_attr( $values_v ) ?>"
                                                   name="<?php echo esc_attr( "tbds_product[{$product_id}][attributes][{$attributes_key}][values][{$values_k}]" ) ?>">
											<?php
										}
										?>
                                    </div>
                                </td>
                                <td>
                                        <span class="vi-ui button mini blue icon tbds-attributes-button-trans" title="<?php esc_attr_e( 'Translate', 'chinads' ) ?>">
                                            <i class="tbds-g_translate"> </i>
                                        </span>
                                    <span class="vi-ui button mini green icon tbds-attributes-button-save" title="<?php esc_attr_e( 'Save', 'chinads' ) ?>">
                                            <i class="icon save"> </i>
                                        </span>
                                    <span class="vi-ui button mini negative icon tbds-attributes-attribute-remove"
                                          title="<?php esc_attr_e( 'Remove this attribute', 'chinads' ) ?>">
                                            <i class="icon trash"> </i>
                                        </span>
                                </td>
                            </tr>
							<?php
							$position ++;
						}
						?>
                        </tbody>
                    </table>
                </div>
                <div class="vi-ui bottom attached tab segment tbds-variations-tab" data-tab="<?php echo esc_attr( 'variations-' . $key ) ?>" data-product_id="<?php echo esc_attr( $product_id ) ?>">
					<?php
					if ( count( $variations ) ) {
						?>
                        <div class="vi-ui positive message">
                            <div class="header">
                                <p><?php esc_html_e( 'You can edit product attributes on Attributes tab', 'chinads' ) ?></p>
                            </div>
                        </div>
                        <table class="form-table tbds-variations-table tbds-table-fix-head tbds-variation-table-attributes-count-<?php echo esc_attr( count( $attributes ) ) ?>">
                        </table>
						<?php
					}
					?>
                </div>
				<?php
			}
			$gallery = array_merge( $gallery, $desc_images );
			if ( count( $gallery ) ) {
				?>
                <div class="vi-ui bottom attached tab segment tbds-product-gallery tbds-lazy-load-tab-data" data-tab="gallery-<?php echo esc_attr( $key ) ?>">
                    <div class="segment ui-sortable">
						<?php
						if ( $default_select_image ) {
							foreach ( $gallery as $gallery_k => $gallery_v ) {
								if ( ! in_array( $gallery_v, $desc_images ) ) {
									$item_class = '';
									if ( $gallery_k === 0 ) {
										$item_class = 'tbds-is-product-image';
									}
									?>
                                    <div class="tbds-product-gallery-item tbds-selected-item <?php echo esc_attr( $item_class ) ?>">
                                        <span class="tbds-selected-item-icon-check"> </span>
                                        <i class="tbds-set-product-image star icon"> </i>
                                        <i class="tbds-set-variation-image hand outline up icon" title="<?php esc_attr_e( 'Set image for selected variation(s)', 'chinads' ) ?>"> </i>
                                        <img src="<?php echo esc_url( TBDS_CONST['img_url'] . 'loading.gif' ) ?>" data-image_src="<?php echo esc_url( $gallery_v ) ?>" class="tbds-product-gallery-image">
                                        <input type="hidden" name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][gallery][]' ) ?>" value="<?php echo esc_attr( $gallery_v ) ?>">
                                    </div>
									<?php
								} else {
									?>
                                    <div class="tbds-product-gallery-item">
                                        <span class="tbds-selected-item-icon-check"> </span>
                                        <i class="tbds-set-product-image star icon"> </i>
                                        <i class="tbds-set-variation-image hand outline up icon" title="<?php esc_attr_e( 'Set image for selected variation(s)', 'chinads' ) ?>"> </i>
                                        <img src="<?php echo esc_url( TBDS_CONST['img_url'] . 'loading.gif' ) ?>" data-image_src="<?php echo esc_url( $gallery_v ) ?>" class="tbds-product-gallery-image">
                                        <input type="hidden" name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][gallery][]' ) ?>" value="">
                                    </div>
									<?php
								}
							}
						} else {
							foreach ( $gallery as $gallery_k => $gallery_v ) {
								?>
                                <div class="tbds-product-gallery-item">
                                    <span class="tbds-selected-item-icon-check"> </span>
                                    <i class="tbds-set-product-image star icon"> </i>
                                    <i class="tbds-set-variation-image hand outline up icon" title="<?php esc_attr_e( 'Set image for selected variation(s)', 'chinads' ) ?>"> </i>
                                    <img src="<?php echo esc_url( TBDS_CONST['img_url'] . 'loading.gif' ) ?>" data-image_src="<?php echo esc_url( $gallery_v ) ?>" class="tbds-product-gallery-image">
                                    <input type="hidden" name="<?php echo esc_attr( 'tbds_product[' . $product_id . '][gallery][]' ) ?>" value="">
                                </div>
								<?php
							}
						}
						?>
                    </div>
                </div>
				<?php
			}
			?>
        </form>
    </div>
    <div class="tbds-product-overlay tbds-hidden"></div>
</div>

