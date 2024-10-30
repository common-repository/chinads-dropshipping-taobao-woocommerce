<?php

namespace TaobaoDropship\Admin;

use TaobaoDropship\Inc\Data;

defined( 'ABSPATH' ) || exit;

class Settings_Helper {
	protected static $instance = null;
	protected static $prefix = 'tbds';

	public static function instance() {
		return self::$instance == null ? self::$instance = new self() : self::$instance;
	}

	public static function output() {
		include dirname( __FILE__ ) . '/views/html-admin-settings.php';
	}

	public static function parse_number_input_attr( $type, $value ) {
		if ( ! $value && $value !== 0 ) {
			return '';
		}

		return is_numeric( $value ) ? " {$type}='{$value}'" : " {$type}={$value}";
	}

	public static function output_fields( $options ) {
		$prefix = self::$prefix;

		if ( empty( $options ) || ! is_array( $options ) ) {
			return;
		}

		$data = Data::instance();

		foreach ( $options as $option ) {

			// Custom attribute handling.
			$custom_attributes = array();

			if ( ! empty( $option['custom_attributes'] ) && is_array( $option['custom_attributes'] ) ) {
				foreach ( $option['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '=' . esc_attr( $attribute_value );
				}
			}
			$custom_attributes = implode( ' ', $custom_attributes );

			$type        = $option['type'] ?? '';
			$id          = $option['id'] ?? $type;
			$required    = $option['require'] ?? '';
			$title       = $option['title'] ?? '';
			$description = $option['desc'] ?? '';
			$multiple    = $type == 'multiselect' ? '[]' : '';

			$field_id      = isset( $option['id'] ) ? $prefix . '-' . str_replace( '_', '-', $id ) : '';
			$class         = $option['class'] ?? '';
			$name          = isset( $option['id'] ) ? "{$prefix}_{$id}{$multiple}" : '';
			$placeholder   = $option['placeholder'] ?? '';
			$value         = $data->get_param( $id );
			$labeled_class = ! empty( $option['unit'] ) ? 'vi-ui right labeled input fluid ' : '';

			// Switch based on type.
			switch ( $type ) {

				// Section Titles.
				case 'section_start':
					if ( ! empty( $option['accordion'] ) ) {
						printf( '<div class="vi-ui styled fluid accordion">
                                            <div class="title">
                                                <i class="dropdown icon"> </i>
                                                %s
                                            </div>
                                        <div class="content">', esc_html( $title ) );
					} else {
						echo ! empty( $title ) ? '<h3>' . esc_html( $title ) . '</h3>' : '';
					}

					if ( ! empty( $description ) ) {
						echo '<div id="' . esc_attr( sanitize_title( $id ) ) . '-description">';
						echo wp_kses_post( wpautop( wptexturize( $description ) ) );
						echo '</div>';
					}

					echo '<table class="form-table">';
					break;

				// Section Ends.
				case 'section_end':
					echo '</table>';
					if ( ! empty( $option['accordion'] ) ) {
						echo '</div></div>';
					}
					break;

				// Standard text inputs and subtypes like 'number'.
				case 'text':
				case 'password':
				case 'datetime':
				case 'datetime-local':
				case 'date':
				case 'month':
				case 'time':
				case 'week':
				case 'number':
				case 'email':
				case 'url':
				case 'tel':
				case 'color':

					if ( $type == 'color' ) {
						$type  = 'text';
						$class .= " {$prefix}-color-picker";
					}

					if ( $type == 'number' ) {
						$custom_attributes .= self::parse_number_input_attr( 'min', $option['min'] ?? '' );
						$custom_attributes .= self::parse_number_input_attr( 'max', $option['max'] ?? '' );
						$custom_attributes .= self::parse_number_input_attr( 'step', $option['step'] ?? '' );
					}
					?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $title ); ?></label>
                        </th>
                        <td class="<?php echo esc_attr( sanitize_title( $type ) ); ?>">
                            <div class="<?php echo esc_attr( $labeled_class . $field_id ) ?>-field ">
								<?php

								printf(
									"<input type='%s' id='%s' class='%s' name='%s' value='%s' %s %s %s>",
									esc_attr( $type ),
									esc_attr( $field_id ),
									esc_attr( $class ),
									esc_attr( $name ),
									esc_attr( $value ),
									$placeholder ? "placeholder='" . esc_attr( $placeholder ) . "'" : '',
									$required ? 'required=true' : '',
									wp_kses_post( $custom_attributes )
								);

								self::unit( $option );

								do_action( "{$prefix}_after_field_" . $id );
								?>

                            </div>
							<?php
							if ( $description ) {
								printf( "<p class='%s-description'>%s</p>", esc_attr( $prefix ), wp_kses_post( $description ) );
							}
							?>
                        </td>
                    </tr>
					<?php
					break;

				//Checkbox
				case 'checkbox':
					?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label>
                        </th>
                        <td class="<?php echo esc_attr( sanitize_title( $type ) ); ?>">
                            <div class="vi-ui toggle checkbox">
								<?php
								printf(
									"<input type='%s' id='%s' class='%s' name='%s' value='1' %s %s>",
									esc_attr( $type ),
									esc_attr( $field_id ),
									esc_attr( $class ),
									esc_attr( $name ),
									wp_kses_post($custom_attributes),
									$value == 1 ? 'checked' : ''
								);
								do_action( "{$prefix}_after_field_" . $id );
								?>
                                <label> </label>
                            </div>

							<?php
							if ( $description ) {
								printf( "<p class='%s-description'>%s</p>", esc_attr( $prefix ), wp_kses_post( $description ) );
							}
							?>

                        </td>
                    </tr>
					<?php
					break;

				case 'select':
				case 'multiselect':
					$custom_attributes .= 'multiselect' === $type ? ' multiple' : '';
					$value = 'multiselect' === $type && $value == '' ? [] : $value;
					?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label>
                        </th>
                        <td class="<?php echo esc_attr( sanitize_title( $type ) ); ?>">
                            <div class="<?php echo esc_attr( $labeled_class . $field_id ) ?>-field">

								<?php
								printf( '<select name="%s" id="%s" class="%s" %s>', esc_attr( $name ), esc_attr( $field_id ), esc_attr( $class ), wp_kses_post( $custom_attributes ) );
								if ( ! empty( $option['options'] ) && is_array( $option['options'] ) ) {

									foreach ( $option['options'] as $key => $page_name ) {
										$selected = is_array( $value ) ? ( in_array( trim( $key ), $value ) ? 'selected' : '' ) : ( $key == $value ? 'selected' : '' );
										printf( "<option value='%s' %s >%s</option>", esc_attr( $key ), esc_attr( $selected ), esc_html( $page_name ) );
									}
								}
								printf( '</select>' );
								?>
                            </div>

							<?php
							if ( $description ) {
								printf( "<p class='%s-description'>%s</p>", esc_attr( $prefix ), wp_kses_post( $description ) );
							}
							?>
                        </td>
                    </tr>
					<?php
					break;

				case 'texteditor':
					?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label>
                        </th>
                        <td class="<?php echo esc_attr( sanitize_title( $type ) ); ?>">
                            <div class="<?php echo esc_attr( $labeled_class . $field_id ) ?>-field">
								<?php
								wp_editor( stripslashes( $value ), $field_id, array( 'editor_height' => 200, 'textarea_name' => $name ) );
								if ( $description ) {
									printf( "<p class='%s-description'>%s</p>", esc_attr( $prefix ), wp_kses_post( $description ) );
								}
								?>
                            </div>
                        </td>
                    </tr>
					<?php
					break;

				case 'pro_version':
                    $button_text = $option['button_text'] ?? esc_html__('Unlock this feature','chinads');
                    $button_url  = $option['url'] ?? 'https://1.envato.market/PyAWE6';
					?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label>
                        </th>
                        <td class="<?php echo esc_attr( sanitize_title( $type ) ); ?>">
                            <div>
                                <a href="<?php echo esc_attr( $button_url ) ?>" class="vi-ui button">
									<?php echo esc_html( $button_text ) ?>
                                </a>
                            </div>
							<?php
							if ( $description ) {
								printf( "<p class='description'>%s</p>",  wp_kses_post( $description ) );
							}
							?>
                        </td>
                    </tr>
					<?php
					break;

				case 'do_action':
					if ( ! empty( $option['id'] ) ) {
						do_action( "{$prefix}_{$option['id']}" );
					}
					break;
				// Default: run an action.
				default:
					?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></label>
                        </th>
                        <td>
							<?php do_action( "{$prefix}_admin_field_" . $id, $value, $option ); ?>
							<?php
							if ( $description ) {
								printf( "<p class='%s-description'>%s</p>", esc_attr( $prefix ), wp_kses_post( $description ) );
							}
							?>
                        </td>
                    </tr>
					<?php
					break;
			}
		}
	}

	protected static function unit( $option ) {
		if ( empty( $option['unit'] ) ) {
			return;
		}

		$unit = $option['unit'];

		if ( is_array( $unit ) ) {
			if ( empty( $unit['id'] ) || empty( $unit['options'] ) || ! is_array( $unit['options'] ) ) {
				return;
			}

			$saved = Data::instance()->get_param( $unit['id'] );

			$classes = [ $unit['id'], self::$prefix . '-dropdown', 'label' ];

			printf( '<select name="%s" class="%s">', esc_attr( $unit['id'] ), esc_attr( implode( ' ', $classes ) ) );

			foreach ( $unit['options'] as $value => $text ) {
				printf( "<option value='%s' %s>%s</option>", esc_attr( $value ), selected( $saved, $value ), esc_html( $text ) );
			}

			echo '</select>';

		} else {
			printf( "<div class='vi-ui basic label'>%s</div>", wp_kses_post( $unit ) );
		}
	}

	public static function save_fields( $options, $data = null ) {

		if ( is_null( $data ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data = $_POST; // WPCS: input var okay, CSRF ok.
		}

		if ( empty( $data ) ) {
			return false;
		}

		// Options to update will be stored here and saved later.
		$update_options   = array();
		$autoload_options = array();
		$prefix           = self::$prefix;

		// Loop options and get values to save.
		foreach ( $options as $option ) {
			if ( ! isset( $option['id'] ) ) {
				continue;
			}

			// Get posted value.
			if ( strstr( $option['id'], '[' ) ) {
				parse_str( $option['id'], $option_name_array );
				$option_name  = current( array_keys( $option_name_array ) );
				$setting_name = key( $option_name_array[ $option_name ] );
				$raw_value    = isset( $data[ $option_name ][ $setting_name ] ) ? wp_unslash( $data[ $option_name ][ $setting_name ] ) : null;
			} else {
				$option_name      = $option['id'] ?? '';
				$full_option_name = "{$prefix}_{$option_name}";
				$setting_name     = '';
				$raw_value        = isset( $data[ $full_option_name ] ) ? wp_unslash( $data[ $full_option_name ] ) : null;
			}

			$type = $option['type'] ?? '';

			// Format the value based on option type.
			switch ( $type ) {
				case 'number':
					$value = $raw_value ? floatval( $raw_value ) : $raw_value;
					break;
				case 'checkbox':
					$value = '1' === $raw_value || 'yes' === $raw_value;
					break;
				case 'textarea':
				case 'texteditor':
					$value = wp_kses_post( trim( $raw_value ) );
					break;
				case 'multiselect':
					$value = array_filter( wc_clean( (array) $raw_value ) );
					break;
				case 'select':
					$allowed_values = empty( $option['options'] ) ? array() : array_map( 'strval', array_keys( $option['options'] ) );
					if ( empty( $option['default'] ) && empty( $allowed_values ) ) {
						$value = null;
						break;
					}
					$default = ( empty( $option['default'] ) ? $allowed_values[0] : $option['default'] );
					$value   = in_array( $raw_value, $allowed_values, true ) ? $raw_value : $default;
					break;
				default:
					$value = wc_clean( $raw_value );
					break;
			}

			/**
			 * Sanitize the value of an option.
			 */
			$value = apply_filters( "{$prefix}_admin_settings_sanitize_option", $value, $option, $raw_value );

			/**
			 * Sanitize the value of an option by option name.
			 */
			$value = apply_filters( "{$prefix}_admin_settings_sanitize_option_$option_name", $value, $option, $raw_value );


			// Check if option is an array and handle that differently to single values.
			if ( $option_name && $setting_name ) {
				if ( ! isset( $update_options[ $option_name ] ) ) {
					$update_options[ $option_name ] = get_option( $option_name, array() );
				}
				if ( ! is_array( $update_options[ $option_name ] ) ) {
					$update_options[ $option_name ] = array();
				}
				$update_options[ $option_name ][ $setting_name ] = $value;
			} else {
				$update_options[ $option_name ] = $value;
			}

			$autoload_options[ $option_name ] = isset( $option['autoload'] ) ? (bool) $option['autoload'] : true;

			if ( ! empty( $option['unit'] ) ) {
				$unit             = $option['unit'];
				$option_unit_name = $unit['id'] ?? '';
				if ( $option_unit_name ) {
					$raw_unit_value = isset( $data[ $option_unit_name ] ) ? sanitize_text_field( wp_unslash( $data[ $option_unit_name ] ) ) : null;

					$update_options[ $option_unit_name ]   = $raw_unit_value;
					$autoload_options[ $option_unit_name ] = isset( $option['autoload'] ) ? (bool) $option['autoload'] : true;

				}
			}

		}

		$update_options = apply_filters( "{$prefix}_pre_update_settings", $update_options );

		update_option( "{$prefix}_params", $update_options, 'yes' );

		Data::instance()->init_params();

		return true;
	}

}

