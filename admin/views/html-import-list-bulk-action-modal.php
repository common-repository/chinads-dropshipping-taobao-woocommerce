<?php

namespace TaobaoDropship\Admin\Views;

defined( 'ABSPATH' ) || exit;
?>
    <div class="tbds-modal-popup-container tbds-hidden">
        <div class="tbds-overlay"></div>
        <div class="tbds-modal-popup-content tbds-modal-popup-content-set-price">
            <div class="tbds-modal-popup-header">
                <h2><?php esc_html_e( 'Set price', 'chinads' ) ?></h2>
                <span class="tbds-modal-popup-close"> </span>
            </div>
            <div class="tbds-modal-popup-content-body">
                <div class="tbds-modal-popup-content-body-row">
                    <div class="tbds-set-price-action-wrap">
                        <label for="tbds-set-price-action"><?php esc_html_e( 'Action', 'chinads' ) ?></label>
                        <select id="tbds-set-price-action"
                                class="tbds-set-price-action">
                            <option value="set_new_value"><?php esc_html_e( 'Set to this value', 'chinads' ) ?></option>
                            <option value="increase_by_fixed_value">
								<?php esc_html_e( 'Increase by fixed value', 'chinads' );
								echo esc_html( '(' . get_woocommerce_currency_symbol() . ')' ) ?>
                            </option>
                            <option value="increase_by_percentage"><?php esc_html_e( 'Increase by percentage(%)', 'chinads' ) ?></option>
                        </select>
                    </div>
                    <div class="tbds-set-price-amount-wrap">
                        <label for="tbds-set-price-amount"><?php esc_html_e( 'Amount', 'chinads' ) ?></label>
                        <input type="text"
                               id="tbds-set-price-amount"
                               class="tbds-set-price-amount">
                    </div>
                </div>
            </div>
            <div class="tbds-modal-popup-content-footer">
                        <span class="button button-primary tbds-set-price-button-set">
                            <?php esc_html_e( 'Set', 'chinads' ) ?>
                        </span>
                <span class="button tbds-set-price-button-cancel">
                            <?php esc_html_e( 'Cancel', 'chinads' ) ?>
                        </span>
            </div>
        </div>
        <div class="tbds-modal-popup-content tbds-modal-popup-content-remove-attribute">
            <div class="tbds-modal-popup-header">
                <h2><?php esc_html_e( 'Please select default value to fulfill orders after this attribute is removed', 'chinads' ) ?></h2>
                <span class="tbds-modal-popup-close"> </span>
            </div>
            <div class="tbds-modal-popup-content-body">
                <div class="tbds-modal-popup-content-body-row tbds-modal-popup-select-attribute">
                </div>
            </div>
        </div>
        <div class="tbds-modal-popup-content tbds-modal-popup-content-set-categories">
            <div class="tbds-modal-popup-header">
                <h2><?php esc_html_e( 'Bulk set product categories', 'chinads' ) ?></h2>
                <span class="tbds-modal-popup-close"> </span>
            </div>
            <div class="tbds-modal-popup-content-body">
                <div class="tbds-modal-popup-content-body-row tbds-modal-popup-set-categories">
                    <div class="tbds-modal-popup-set-categories-select-wrap">
                        <select name="tbds_bulk_set_categories" class="'vi-ui dropdown fluid search tbds-modal-popup-set-categories-select" multiple>
							<?php
							if ( ! empty( $category_options ) ) {
								foreach ( $category_options as $cat_id => $cat_name ) {
									printf( "<option value='%s'>%s</option>", esc_attr( $cat_id ), esc_html( $cat_name ) );
								}
							}
							?>
                        </select>
                        <span class="vi-ui black button mini tbds-modal-popup-set-categories-clear">
	                        <?php esc_html_e( 'Clear selected', 'chinads' ) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="tbds-modal-popup-content-footer">
                    <span class="button button-primary tbds-set-categories-button-add"
                          title="<?php esc_attr_e( 'Add selected and keep existing categories', 'chinads' ) ?>">
	                    <?php esc_html_e( 'Add', 'chinads' ) ?>
                    </span>
                <span class="button button-primary tbds-set-categories-button-set"
                      title="<?php esc_attr_e( 'Remove existing categories and add selected', 'chinads' ) ?>">
	                <?php esc_html_e( 'Set', 'chinads' ) ?>
                </span>
                <span class="button tbds-set-categories-button-cancel">
	                <?php esc_html_e( 'Cancel', 'chinads' ) ?>
                </span>
            </div>
        </div>
        <div class="tbds-modal-popup-content tbds-modal-popup-content-set-tags">
            <div class="tbds-modal-popup-header">
                <h2><?php esc_html_e( 'Bulk set product tags', 'chinads' ) ?></h2>
                <span class="tbds-modal-popup-close"> </span>
            </div>
            <div class="tbds-modal-popup-content-body">
                <div class="tbds-modal-popup-content-body-row tbds-modal-popup-set-tags">
                    <div class="tbds-modal-popup-set-tags-select-wrap">
                        <select name="tbds_bulk_set_tags" class="vi-ui dropdown fluid search tbds-modal-popup-set-tags-select" multiple>
							<?php
							if ( ! empty( $tags_options ) ) {
								foreach ( $tags_options as $tag ) {
									printf( "<option value='%s'>%s</option>", esc_attr( $tag ), esc_html( $tag ) );
								}
							}
							?>
                        </select>
                        <span class="vi-ui black button mini tbds-modal-popup-set-tags-clear">
	                        <?php esc_html_e( 'Clear selected', 'chinads' ) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="tbds-modal-popup-content-footer">
                    <span class="button button-primary tbds-set-tags-button-add"
                          title="<?php esc_attr_e( 'Add selected and keep existing tags', 'chinads' ) ?>">
	                    <?php esc_html_e( 'Add', 'chinads' ) ?>
                    </span>
                <span class="button button-primary tbds-set-tags-button-set"
                      title="<?php esc_attr_e( 'Remove existing tags and add selected', 'chinads' ) ?>">
	                <?php esc_html_e( 'Set', 'chinads' ) ?>
                </span>
                <span class="button tbds-set-tags-button-cancel">
	                <?php esc_html_e( 'Cancel', 'chinads' ) ?>
                </span>
            </div>
        </div>
        <div class="tbds-saving-overlay tbds-hidden"></div>
    </div>
<?php