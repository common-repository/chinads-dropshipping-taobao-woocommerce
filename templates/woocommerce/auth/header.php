<?php
/**
 * Auth header
 */

defined( 'ABSPATH' ) || exit;
$app_name = isset( $_REQUEST['app_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['app_name'] ) ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta name="viewport" content="width=device-width"/>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="robots" content="noindex, nofollow"/>
    <title><?php esc_html_e( 'Application authentication request', 'chinads' ); ?></title>
	<?php wp_admin_css( 'install', true ); ?>
    <link rel="stylesheet" href="<?php echo esc_url( str_replace( [ 'http:', 'https:' ], '', WC()->plugin_url() ) . '/assets/css/auth.css' ); ?>" type="text/css"/>
</head>
<body class="wc-auth wp-core-ui">
<!--Waiting LOGO-->
<div class="wc-auth-content">
