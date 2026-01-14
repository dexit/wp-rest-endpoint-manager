<?php
/**
 * Queue Manager - Manages dispatch queue
 *
 * @package WP_REST_Endpoint_Manager
 */

namespace WP_REST_Endpoint_Manager\Dispatch;

/**
 * Queue manager using Action Scheduler or WordPress cron.
 */
class Queue_Manager {

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool True if Action Scheduler is available.
	 */
	private function has_action_scheduler() {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Enqueue a webhook for processing.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $context Context data.
	 * @return bool|int Success or action ID.
	 */
	public function enqueue( $webhook_id, $context ) {
		if ( $this->has_action_scheduler() ) {
			// Use Action Scheduler for better reliability (like dio-cron).
			$action_id = as_schedule_single_action(
				time(),
				'wp_rem_process_dispatch_queue',
				array( $webhook_id, $context ),
				'wp-rem-dispatch'
			);
			return $action_id !== false;
		} else {
			// Fallback to WordPress cron.
			$scheduled = wp_schedule_single_event(
				time(),
				'wp_rem_process_dispatch_queue',
				array( $webhook_id, $context )
			);
			return $scheduled !== false;
		}
	}

	/**
	 * Get queue size.
	 *
	 * @return int Queue size.
	 */
	public function get_queue_size() {
		if ( $this->has_action_scheduler() ) {
			// Count pending actions.
			return as_get_scheduled_actions(
				array(
					'hook' => 'wp_rem_process_dispatch_queue',
					'status' => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => -1,
				),
				'ids'
			) ? count( as_get_scheduled_actions(
				array(
					'hook' => 'wp_rem_process_dispatch_queue',
					'status' => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => -1,
				),
				'ids'
			) ) : 0;
		} else {
			// Count WordPress cron events.
			$cron = _get_cron_array();
			$count = 0;

			foreach ( $cron as $timestamp => $hooks ) {
				if ( isset( $hooks['wp_rem_process_dispatch_queue'] ) ) {
					$count += count( $hooks['wp_rem_process_dispatch_queue'] );
				}
			}

			return $count;
		}
	}

	/**
	 * Clear all queued items.
	 *
	 * @return int Number of items cleared.
	 */
	public function clear_queue() {
		$cleared = 0;

		if ( $this->has_action_scheduler() ) {
			// Cancel all pending actions.
			$actions = as_get_scheduled_actions(
				array(
					'hook' => 'wp_rem_process_dispatch_queue',
					'status' => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => -1,
				),
				'ids'
			);

			if ( $actions ) {
				foreach ( $actions as $action_id ) {
					as_unschedule_action( 'wp_rem_process_dispatch_queue', array(), 'wp-rem-dispatch' );
					$cleared++;
				}
			}
		} else {
			// Clear WordPress cron events.
			$cron = _get_cron_array();

			foreach ( $cron as $timestamp => $hooks ) {
				if ( isset( $hooks['wp_rem_process_dispatch_queue'] ) ) {
					foreach ( $hooks['wp_rem_process_dispatch_queue'] as $key => $event ) {
						wp_unschedule_event( $timestamp, 'wp_rem_process_dispatch_queue', $event['args'] );
						$cleared++;
					}
				}
			}
		}

		return $cleared;
	}
}
