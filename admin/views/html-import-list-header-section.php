<?php
namespace TaobaoDropship\Admin\Views;

use TaobaoDropship\Inc\Utils;

defined( 'ABSPATH' ) || exit;

$count      = $the_query->found_posts;
$total_page = $the_query->max_num_pages;

$bulk_options = [
	""                       => esc_html__( 'Bulk Action', 'chinads' ),
	"set_categories"         => esc_html__( 'Set categories', 'chinads' ),
	"set_tags"               => esc_html__( 'Set tags', 'chinads' ),
	"set_status_publish"     => esc_html__( 'Set status - Publish', 'chinads' ),
	"set_status_pending"     => esc_html__( 'Set status - Pending', 'chinads' ),
	"set_status_draft"       => esc_html__( 'Set status - Draft', 'chinads' ),
	"set_visibility_visible" => esc_html__( 'Set visibility - Shop and search results', 'chinads' ),
	"set_visibility_catalog" => esc_html__( 'Set visibility - Shop only', 'chinads' ),
	"set_visibility_search"  => esc_html__( 'Set visibility - Search results only', 'chinads' ),
	"set_visibility_hidden"  => esc_html__( 'Set visibility - Hidden', 'chinads' ),
	"import"                 => esc_html__( 'Import selected', 'chinads' ),
	"remove"                 => esc_html__( 'Remove selected', 'chinads' ),
];
?>

<form method="get" class="vi-ui segment <?php echo esc_attr( Utils::set_class_name( 'pagination-form' ) ) ?>">
    <input type="hidden" name="page" value="tbds-import-list">
    <div class="tablenav top">
        <div class="<?php echo esc_attr( Utils::set_class_name( 'button-import-all-container' ) ) ?>">
            <input type="checkbox" class="<?php echo esc_attr( Utils::set_class_name( 'accordion-bulk-item-check-all' ) ) ?>">
            <span class="vi-ui button mini primary <?php echo esc_attr( Utils::set_class_name( 'button-import-all' ) ) ?>"
                  title="<?php esc_attr_e( 'Import all products on this page', 'chinads' ) ?>">
	            <?php esc_html_e( 'Import All', 'chinads' ) ?>
            </span>
            <a class="vi-ui button negative mini <?php echo esc_attr( Utils::set_class_name( 'button-empty-import-list' ) ) ?>"
               href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'tbds_empty_product_list', 1 ) ) ) ?>"
               title="<?php esc_attr_e( 'Remove all products(except overriding products) from Import list', 'chinads' ) ?>">
				<?php esc_html_e( 'Empty List', 'chinads' ) ?>
            </a>
            <span class="<?php echo esc_attr( Utils::set_class_name( 'accordion-bulk-actions-container' ) ) ?>">
                <select name="<?php echo esc_attr( 'tbds_bulk_actions' ) ?>"
                        class="vi-ui dropdown <?php echo esc_attr( Utils::set_class_name( 'accordion-bulk-actions' ) ) ?>">
                    <?php
                    foreach ( $bulk_options as $value => $text ) {
	                    printf( "<option value='%s'>%s</option>", esc_attr( $value ), esc_html( $text ) );
                    }
                    ?>
                </select>
            </span>
        </div>
        <div class="tablenav-pages">
            <div class="pagination-links">
				<?php
				if ( $paged > 2 ) {
					?>
                    <a class="prev-page button" href="<?php echo esc_url( add_query_arg(
						array(
							'page'        => 'tbds-import-list',
							'paged'       => 1,
							'tbds_search' => $keyword,
						), admin_url( 'admin.php' )
					) ) ?>"><span
                                class="screen-reader-text"><?php esc_html_e( 'First Page', 'chinads' ) ?></span><span
                                aria-hidden="true">«</span></a>
					<?php
				} else {
					?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
					<?php
				}
				/*Previous button*/
				$p_paged = $per_page * $paged > $per_page ? $paged - 1 : 0;

				if ( $p_paged ) {
					$p_url = add_query_arg(
						array(
							'page'        => 'tbds-import-list',
							'paged'       => $p_paged,
							'tbds_search' => $keyword,
						), admin_url( 'admin.php' )
					);
					?>
                    <a class="prev-page button" href="<?php echo esc_url( $p_url ) ?>"><span
                                class="screen-reader-text"><?php esc_html_e( 'Previous Page', 'chinads' ) ?></span><span
                                aria-hidden="true">‹</span></a>
					<?php
				} else {
					?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
					<?php
				}
				?>
                <span class="screen-reader-text"><?php esc_html_e( 'Current Page', 'chinads' ) ?></span>
                <span id="table-paging" class="paging-input">
                                    <input class="current-page" type="text" name="paged" size="1"
                                           value="<?php echo esc_html( $paged ) ?>"><span class="tablenav-paging-text"> of <span
                                class="total-pages"><?php echo esc_html( $total_page ) ?></span></span>

							</span>
				<?php /*Next button*/
				$n_paged = $per_page * $paged < $count ? $paged + 1 : 0;
				if ( $n_paged ) {
					$n_url = add_query_arg(
						array(
							'page'        => 'tbds-import-list',
							'paged'       => $n_paged,
							'tbds_search' => $keyword,
						), admin_url( 'admin.php' )
					); ?>
                    <a class="next-page button" href="<?php echo esc_url( $n_url ) ?>"><span
                                class="screen-reader-text"><?php esc_html_e( 'Next Page', 'chinads' ) ?></span><span
                                aria-hidden="true">›</span></a>
					<?php
				} else {
					?>
                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
					<?php
				}
				if ( $total_page > $paged + 1 ) {
					$next_page_url = add_query_arg( [
						'page'        => 'tbds-import-list',
						'paged'       => $total_page,
						'tbds_search' => $keyword,
					], admin_url( 'admin.php' ) );
					?>
                    <a class="next-page button" href="<?php echo esc_url( $next_page_url ) ?>">
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
            <input type="search" class="text short" name="tbds_search"
                   placeholder="<?php esc_attr_e( 'Search product in import list', 'chinads' ) ?>"
                   value="<?php echo esc_attr( $keyword ) ?>">
            <input type="submit" name="submit" class="button"
                   value="<?php echo esc_attr__( 'Search product', 'chinads' ) ?>">
        </p>
    </div>
</form>
