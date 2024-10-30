<?php
$all_options = array(
	'override-keep-product'   => esc_html__( 'Keep Woo product', 'chinads' ),
	'override-find-in-orders' => esc_html__( 'Find in unfulfilled orders', 'chinads' ),
	'override-title'          => esc_html__( 'Replace product title', 'chinads' ),
	'override-images'         => esc_html__( 'Replace product image and gallery', 'chinads' ),
	'override-description'    => esc_html__( 'Replace description and short description', 'chinads' ),
	'override-hide'           => wp_kses_post( __( 'Save my choices and do not show these options again(you can still change this in <a target="_blank" href="admin.php?page=tbds-settings#/override">plugin settings</a>).', 'chinads' ) ),
);
?>
<div class="tbds-override-product-options-container tbds-hidden">
    <div class="tbds-override-product-overlay"></div>
    <div class="tbds-override-product-options-content">
        <div class="tbds-override-product-options-content-header">
            <h2>
                <span class="tbds-override-product-text-override">
                    <?php esc_html_e( 'Override: ', 'chinads' ) ?>
                </span>
                <span class="tbds-override-product-text-reimport">
                    <?php esc_html_e( 'Reimport: ', 'chinads' ) ?>
                </span>
                <span class="tbds-override-product-text-map-existing">
                    <?php esc_html_e( 'Import & map existing Woo product: ', 'chinads' ) ?>
                </span>
                <span class="tbds-override-product-title"> </span>
            </h2>
            <span class="tbds-override-product-options-close"> </span>
            <div class="vi-ui message warning tbds-override-product-remove-warning">
				<?php esc_html_e( 'Overridden product and all of its data(including variations, reviews, metadata...) will be deleted. Please make sure you had backed up those kinds of data before continuing!', 'chinads' ) ?>
            </div>
        </div>
		<?php
		if ( ! $settings->get_param( 'override_hide' ) ) {
			?>
            <div class="tbds-override-product-options-content-body tbds-override-product-options-content-body-option">
				<?php
				foreach ( $all_options as $option_key => $option_value ) {
					?>
                    <div class="tbds-override-product-options-content-body-row tbds-override-product-options-content-body-row-<?php echo esc_attr( $option_key ) ?>">
                        <div class="tbds-override-product-options-option-wrap">
                            <input type="checkbox" data-order_option="<?php echo esc_attr( $option_key ) ?>"
                                   value="1" <?php checked( 1, $settings->get_param( str_replace( '-', '_', $option_key ) ) ) ?>
                                   id="tbds-override-product-options-<?php echo esc_attr( $option_key ) ?>"
                                   class="override-product-options-option tbds-override-product-options-<?php echo esc_attr( $option_key ) ?>">
                            <label for="tbds-override-product-options-<?php echo esc_attr( $option_key ) ?>"><?php echo wp_kses_post( $option_value ) ?></label>
                        </div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		}
		?>
        <div class="tbds-override-product-options-content-body tbds-override-product-options-content-body-override-old">
        </div>
        <div class="tbds-override-product-options-content-footer">
                    <span class="vi-ui button mini positive tbds-override-product-options-button-override" data-override_product_id="">
                            <span class="tbds-override-product-text-override">
                                <?php esc_html_e( 'Override', 'chinads' ) ?>
                            </span>
                        <span class="tbds-override-product-text-map-existing">
                            <?php esc_html_e( 'Import & Map', 'chinads' ) ?>
                        </span>
                        <span class="tbds-override-product-text-reimport">
                            <?php esc_html_e( 'Reimport', 'chinads' ) ?>
                        </span>
                        </span>
            <span class="vi-ui button mini tbds-override-product-options-button-cancel">
                <?php esc_html_e( 'Cancel', 'chinads' ) ?>
            </span>
        </div>
    </div>
    <div class="tbds-override-product-overlay"></div>
</div>
