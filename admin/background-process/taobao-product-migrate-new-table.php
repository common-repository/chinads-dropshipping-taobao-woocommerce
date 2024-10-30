<?php
namespace TaobaoDropship\Admin\Background_Process;
use TaobaoDropship\Admin\Taobao_Products_Table;
defined('ABSPATH') || exit;

class Taobao_Product_Migrate_New_Table extends \WP_Background_Process {
	protected static $instance = null;
	protected $action = 'tbds_product_migrate_new_table';
	protected $page = 0;
	protected $step = '';
	protected $continue = false;

	public static function instance($start=false)
	{
		return self::$instance == null || $start ? self::$instance = new self() : self::$instance;
	}
	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		if ( ! empty( $item ) ) {
			$step        = $item['step'] ?? 'move';
			$numberposts = WP_DEBUG ? 5 : 30;
			$this->step  = $step;

			switch ( $step ) {
				case 'move':
					$posts = get_posts( [
						'post_type'   => 'tbds_draft_product',
						'numberposts' => $numberposts,
						'orderby'     => 'ID',
						'order'       => 'ASC',
						'post_status' => 'any',
						'meta_query'  => [//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							[
								'key'     => '_tbds_migrated_to_new_table',
								'compare' => 'NOT EXISTS',
							]
						]
					] );

					if ( ! empty( $posts ) ) {
						foreach ( $posts as $post ) {
							$post_id = $post->ID;

							$clone       = (array) $post;
							$clone['ID'] = 0;
							$meta        = get_post_meta( $post_id );

							if ( ! empty( $meta ) ) {
								foreach ( $meta as $m_key => $m ) {
									if ( ! empty( $m[0] ) ) {
										$clone['meta_input'][ $m_key ] = maybe_unserialize( $m[0] );
									}
								}
							}

							$new_id = Taobao_Products_Table::insert_post( $clone );

							if ( $new_id ) {
								update_post_meta( $post_id, '_tbds_migrated_to_new_table', $new_id );
								self::wc_log(  "Success: $post_id to $new_id", 'migrate-taobao-products' );
							} else {
								self::wc_log( 'Error: ' . $post_id, 'migrate-taobao-products' );
							}
						}

						$this->continue = true;
					}

					break;

				case 'delete':
					$posts = get_posts( [
						'post_type'   => 'tbds_draft_product',
						'numberposts' => $numberposts,
						'post_status' => 'any',
					] );
					if ( ! empty( $posts ) ) {
						foreach ( $posts as $post ) {
							wp_delete_post( $post->ID, true );
						}
						$this->continue = true;
					}
					break;
			}
		}

		return false;
	}
	/**
	 * Is the updater running?
	 *
	 * @return boolean
	 */
	public function is_process_running()
	{
		return parent::is_process_running();
	}

	/**
	 * Is the queue empty
	 *
	 * @return boolean
	 */
	public function is_queue_empty()
	{
		return parent::is_queue_empty();
	}
	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete()
	{
		if ($this->is_queue_empty() && !$this->is_process_running()) {
			if ( $this->continue ) {
				$this->push_to_queue( [ 'step' => $this->step ] );
				$this->save()->dispatch();
			} else {
				switch ( $this->step ) {
					case 'move':
						update_option( 'tbds_migrated_to_new_table', true );
						$settings              = get_option( 'tbds_params' );
						$settings['use_tbds_table'] = 1;
						update_option( 'tbds_params', $settings );
						break;
					case 'delete':
						update_option( 'tbds_deleted_old_posts_data', true );
						break;
				}
			}
		}
		// Show notice to user or perform some other arbitrary task...
		parent::complete();
	}
	/**
	 * Delete all batches.
	 *
	 * @return Taobao_Product_Migrate_New_Table
	 */
	public function delete_all_batches() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

		return $this;
	}

	/**
	 * Kill process.
	 *
	 * Stop processing queue items, clear cronjob and delete all batches.
	 */
	public function kill_process() {
		if ( ! $this->is_queue_empty() ) {
			$this->delete_all_batches();
			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}
	}
	public static function wc_log( $content, $source = 'debug', $level = 'info' ) {
		$content = wp_strip_all_tags( $content );
		$log     = wc_get_logger();
		$log->log( $level,
			$content,
			array(
				'source' => 'CHINADS-' . $source,
			)
		);
	}
}
