<?php

namespace TaobaoDropship\Admin\Views;

defined( 'ABSPATH' ) || exit;

$options = [
	'fixed'    => esc_html__( 'Increase by Fixed amount(¥)', 'chinads' ),
	'percent'  => esc_html__( 'Increase by Percentage(%)', 'chinads' ),
	'multiply' => esc_html__( 'Multiply with', 'chinads' ),
	'set_to'   => esc_html__( 'Set to', 'chinads' ),
];
?>
    <div class="vi-ui segment">
        <div class="vi-ui positive small message">
			<?php
			esc_html_e( 'For each price, first matched rule(from top to bottom) will be applied. If no rules match, the default will be used.', 'chinads' )
			?>
        </div>

        <table class="vi-ui table price-rule">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Price range', 'chinads' ) ?></th>
                <th><?php esc_html_e( 'Actions', 'chinads' ) ?></th>
                <th>
					<?php esc_html_e( 'Sale price', 'chinads' ) ?>
                    <div class="tbds-description">
						<?php esc_html_e( '(Set -1 to not use sale price)', 'chinads' ) ?>
                    </div>
                </th>
                <th style="min-width: 135px"><?php esc_html_e( 'Regular price', 'chinads' ) ?></th>
                <th>
                </th>
            </tr>
            </thead>
            <tbody class="tbds-price-rule-container ui-sortable">
			<?php
			$decimals      = wc_get_price_decimals();
			$decimals_unit = 1;

			if ( $decimals > 0 ) {
				$decimals_unit = pow( 10, ( - 1 * $decimals ) );
			}

			$price_from       = $this->settings->get_param( 'price_from' );
			$price_default    = $this->settings->get_param( 'price_default' );
			$price_to         = $this->settings->get_param( 'price_to' );
			$plus_value       = $this->settings->get_param( 'plus_value' );
			$plus_sale_value  = $this->settings->get_param( 'plus_sale_value' );
			$plus_value_type  = $this->settings->get_param( 'plus_value_type' );
			$price_from_count = count( $price_from );

			if ( $price_from_count > 0 ) {
				/*adjust price rules since version 1.0.1.1*/
				if ( ! is_array( $price_to ) || count( $price_to ) !== $price_from_count ) {
					if ( $price_from_count > 1 ) {
						$price_to   = array_values( array_slice( $price_from, 1 ) );
						$price_to[] = '';
					} else {
						$price_to = array( '' );
					}
				}
				for ( $i = 0; $i < count( $price_from ); $i ++ ) {
					switch ( $plus_value_type[ $i ] ) {
						case 'fixed':
							$value_label_left  = '+';
							$value_label_right = '¥';
							break;
						case 'percent':
							$value_label_left  = '+';
							$value_label_right = '%';
							break;
						case 'multiply':
							$value_label_left  = 'x';
							$value_label_right = '';
							break;
						default:
							$value_label_left  = '=';
							$value_label_right = '¥';
					}
					?>
                    <tr class="tbds-price-rule-row">
                        <td>
                            <div class="equal width fields">
                                <div class="field">
                                    <div class="vi-ui left labeled input fluid">
                                        <label for="amount" class="vi-ui label">¥</label>
                                        <input step="any" type="number" min="0" name="tbds_price_from[]" class="tbds-price-from" value="<?php echo esc_attr( $price_from[ $i ] ); ?>">
                                    </div>
                                </div>
                                <span class="tbds-price-from-to-separator">-</span>
                                <div class="field">
                                    <div class="vi-ui left labeled input fluid">
                                        <label for="amount" class="vi-ui label">¥</label>
                                        <input step="any" type="number" min="0" value="<?php echo esc_attr( $price_to[ $i ] ); ?>" name="tbds_price_to[]" class="tbds-price-to">
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <select name="tbds_plus_value_type[]" class="vi-ui fluid dropdown tbds-dropdown tbds-plus-value-type">
								<?php
								foreach ( $options as $value => $text ) {
									printf( "<option value='%s' %s>%s</option>", esc_attr( $value ), selected( $plus_value_type[ $i ], $value, false ), esc_html( $text ) );
								}
								?>
                            </select>
                        </td>
                        <td>
                            <div class="vi-ui right labeled input fluid">
                                <label for="amount" class="vi-ui label tbds-value-label-left"><?php echo esc_html( $value_label_left ) ?></label>
                                <input type="number" min="-1" step="any" value="<?php echo esc_attr( $plus_sale_value[ $i ] ); ?>" name="tbds_plus_sale_value[]" class="tbds-plus-sale-value">
                                <div class="vi-ui basic label tbds-value-label-right"><?php echo esc_html( $value_label_right ) ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="vi-ui right labeled input fluid">
                                <label for="amount" class="vi-ui label tbds-value-label-left"><?php echo esc_html( $value_label_left ) ?></label>
                                <input type="number" min="0" step="any" value="<?php echo esc_attr( $plus_value[ $i ] ); ?>" name="tbds_plus_value[]" class="tbds-plus-value">
                                <div class="vi-ui basic label tbds-value-label-right"><?php echo esc_html( $value_label_right ) ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="">
                                <span class="vi-ui button icon negative mini tbds-price-rule-remove" title="<?php esc_attr_e( 'Remove', 'chinads' ) ?>">
                                    <i class="icon trash"> </i>
                                </span>
                            </div>
                        </td>
                    </tr>
					<?php
				}
			}
			?>
            </tbody>
            <tfoot>
			<?php
			$plus_value_type_d = isset( $price_default['plus_value_type'] ) ? $price_default['plus_value_type'] : 'multiply';
			$plus_sale_value_d = isset( $price_default['plus_sale_value'] ) ? $price_default['plus_sale_value'] : 1;
			$plus_value_d      = isset( $price_default['plus_value'] ) ? $price_default['plus_value'] : 2;
			switch ( $plus_value_type_d ) {
				case 'fixed':
					$value_label_left  = '+';
					$value_label_right = '¥';
					break;
				case 'percent':
					$value_label_left  = '+';
					$value_label_right = '%';
					break;
				case 'multiply':
					$value_label_left  = 'x';
					$value_label_right = '';
					break;
				default:
					$value_label_left  = '=';
					$value_label_right = '¥';
			}
			?>
            <tr class="tbds-price-rule-row-default">
                <th><?php esc_html_e( 'Default', 'chinads' ) ?></th>
                <th>
                    <select name="tbds_price_default[plus_value_type]" class="vi-ui fluid dropdown tbds-plus-value-type tbds-dropdown">
						<?php
						foreach ( $options as $value => $text ) {
							printf( "<option value='%s' %s>%s</option>", esc_attr( $value ), selected( $plus_value_type_d, $value, false ), esc_html( $text ) );
						}
						?>
                    </select>
                </th>
                <th>
                    <div class="vi-ui right labeled input fluid">
                        <label for="amount" class="vi-ui label tbds-value-label-left"><?php echo esc_html( $value_label_left ) ?></label>
                        <input type="number" min="-1" step="any" value="<?php echo esc_attr( $plus_sale_value_d ); ?>" name="tbds_price_default[plus_sale_value]" class="tbds-plus-sale-value">
                        <div class="vi-ui basic label tbds-value-label-right"><?php echo esc_html( $value_label_right ) ?></div>
                    </div>
                </th>
                <th>
                    <div class="vi-ui right labeled input fluid">
                        <label for="amount" class="vi-ui label tbds-value-label-left"><?php echo esc_html( $value_label_left ) ?></label>
                        <input type="number" min="0" step="any" value="<?php echo esc_attr( $plus_value_d ); ?>" name="tbds_price_default[plus_value]" class="tbds-plus-value">
                        <div class="vi-ui basic label tbds-value-label-right"><?php echo esc_html( $value_label_right ) ?></div>
                    </div>
                </th>
                <th>
                </th>
            </tr>
            </tfoot>
        </table>

        <span class="tbds-price-rule-add vi-ui button icon positive mini"
              title="<?php esc_attr_e( 'Add a new range', 'chinads' ) ?>">
                <i class="icon add"> </i>
        </span>
    </div>
<?php