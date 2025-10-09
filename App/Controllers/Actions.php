<?php

namespace WLLP\App\Controllers;

use Wlr\App\Models\Users;

defined( 'ABSPATH' ) or die;

use WLLP\App\Models\GracePeriod;
use Wlr\App\Helpers\Settings;

class Actions {

	/**
	 * To change the points based on the settings.
	 *
	 * @param   int    $points
	 * @param   array  $user_fields
	 *
	 * @return int
	 */
	public static function changePointsToGetLevel( int $points, array $user_fields ): int {
		$grace_period_enabled = Controller::getSetting( 'grace_period_enabled', 0 ) == 1;
		if ( ! $grace_period_enabled ) {
			$points = self::resolvePointsBySetting( $points, $user_fields );
		} else {
			$points = self::getPointsBasedOnGracePeriod( $points, $user_fields );
		}

		return $points;
	}

	/**
	 * Resolve points according to the configured source.
	 * Falls back to the incoming $points when needed.
	 *
	 * @param   int    $points
	 * @param   array  $user_fields
	 *
	 * @return int
	 */
	private static function resolvePointsBySetting( int $points, $fields ): int {
		$setting = Controller::getSetting( 'levels_from_which_point_based', '' );

		if ( $setting == 'from_current_balance' && self::hasField( $fields, 'points' ) ) {
			return (int) self::getFieldValue( $fields, 'points', $points );
		} elseif ( $setting == 'from_points_redeemed' && self::hasField( $fields, 'used_total_points' ) ) {
			return (int) self::getFieldValue( $fields, 'used_total_points', $points );
		} elseif ( $setting == 'from_order_total' ) {
			return (int) self::getOrderTotal( $fields );
		}

		return (int) $points;
	}

	/**
	 * Safely check whether a field exists on array|object.
	 *
	 * @param   array|object  $fields
	 */
	private static function hasField( $fields, string $key ): bool {
		if ( is_array( $fields ) ) {
			return isset( $fields[ $key ] );
		}
		if ( is_object( $fields ) ) {
			return isset( $fields->$key );
		}

		return false;
	}

	/**
	 * Safely get a field value from array|object.
	 *
	 * @param   array|object  $fields
	 */
	private static function getFieldValue( $fields, string $key, $default = null ) {
		if ( is_array( $fields ) ) {
			return isset( $fields[ $key ] ) ? $fields[ $key ] : $default;
		}
		if ( is_object( $fields ) ) {
			return isset( $fields->$key ) ? $fields->$key : $default;
		}

		return $default;
	}

	public static function getPointsBasedOnGracePeriod( int $points, array $user_fields ) {
		$user_email = $user_fields['user_email'] ?? '';
		if ( empty( $user_email ) ) {
			return $points;
		}
		$grace_model     = new GracePeriod();
		$existing_record = $grace_model->getLatestRecordByEmail( $user_email );
		$now             = strtotime( gmdate( 'Y-m-d H:i:s' ) );
		//wc_get_logger()->add('wllp','Current timestamp: '. $now);
		if ( is_object( $existing_record ) && ! empty( $existing_record ) && isset( $existing_record->level_valid_until ) && $existing_record->level_valid_until > $now ) {
			//wc_get_logger()->add('wllp','Grace period is active for user: '. $user_email);
			// Grace period active
			$sorted_levels = Controller::sortActiveLevels();
			if ( ! is_array( $sorted_levels ) || empty( $sorted_levels ) ) {
				return $points;
			}
			$rank_by_id = [];
			foreach ( $sorted_levels as $index => $level ) {
				$rank_by_id[ $level->id ] = $index;
			}

			// Compute points to evaluate current level based on config
			$points_to_eval = self::resolvePointsBySetting( $points, $user_fields );

			$levels_model     = new \Wlr\App\Models\Levels();
			$current_level_id = $levels_model->getCurrentLevelId( (int) $points_to_eval );

			$current_level_rank  = $rank_by_id[ $current_level_id ] ?? - 1;
			$upgraded_level_rank = $rank_by_id[ $existing_record->upgraded_level_id ] ?? - 1;

			if ( $current_level_rank >= 0 && $upgraded_level_rank >= 0 && $current_level_rank < $upgraded_level_rank ) {
				// Below locked level: enforce minimum points to maintain
				if ( isset( $existing_record->minimum_points_to_maintain ) ) {
					$points = (int) $existing_record->minimum_points_to_maintain;
				}
			} elseif ( $current_level_rank === $upgraded_level_rank ) {
				// At locked level: keep evaluated points
				$points = (int) $points_to_eval;
			}
		} elseif ( ! empty( $existing_record ) && isset( $existing_record->level_valid_until ) && $existing_record->level_valid_until < $now ) {
			// Grace period expired - delete the record and fall back to normal calculation
			$grace_model->deleteRow( [ 'id' => (int) $existing_record->id ] );
			$points = self::resolvePointsBySetting( $points, $user_fields );
		} else {
			// NO GRACE PERIOD RECORD EXISTS - should fall back to settings
			$points = self::resolvePointsBySetting( $points, $user_fields );
		}

		//wc_get_logger()->add('wllp','Returning points: '. $points);
		return $points;
	}

	public static function afterUserLevelChanged( $old_level_id, $user_data ) {

		if ( ! is_array( $user_data ) || empty( $user_data['user_email'] ) ) {
			return;
		}

		// Check if email exists it the grace period table
		$levels = Controller::sortActiveLevels();

		if ( ! isset( $levels ) || ! is_array( $levels ) || empty( $levels ) ) {
			return;
		}

		$rank_by_id = [];
		foreach ( $levels as $index => $level ) {
			$rank_by_id[ $level->id ] = $index;
		}

		$new_level_id = isset( $user_data['level_id'] ) ? (int) $user_data['level_id'] : 0;

		$old_level_rank = isset( $rank_by_id[ $old_level_id ] ) ? $rank_by_id[ $old_level_id ] : - 1;
		$new_level_rank = $rank_by_id[ $new_level_id ];

		$direction = 'same';

		if ( $old_level_rank >= 0 && $new_level_rank > $old_level_rank ) {
			$direction = 'up';
		} elseif ( $old_level_rank >= 0 && $new_level_rank < $old_level_rank ) {
			$direction = 'down';
		} elseif ( $old_level_rank < 0 ) {
			$direction = 'up';  // no prev lvl, so treat as up
		}

		// $levels variable has all the levels sorted in ascending order. If the current changed level is lower than the new level then we need to check for grace period

		$grace_model     = new GracePeriod();
		$existing_record = $grace_model->getLatestRecordByEmail( $user_data['user_email'] );

		// On upgrade, compute once then update-if-exists else insert
		if ( $direction === 'up' && $old_level_id !== $new_level_id ) {
			$grace_period_days = (int) Controller::getSetting( 'grace_period_days', 30 );
			if ( $grace_period_days <= 0 ) {
				return;
			}

			$new_level_obj = null;
			foreach ( $levels as $level ) {
				if ( (int) $level->id === $new_level_id ) {
					$new_level_obj = $level;
					break;
				}
			}
			if ( ! $new_level_obj ) {
				return;
			}

			$minimum_points_to_maintain = isset( $new_level_obj->from_points ) ? (int) $new_level_obj->from_points : 0;
			$now                        = strtotime( gmdate( 'Y-m-d H:i:s' ) );
			$valid_until                = $now + ( $grace_period_days * DAY_IN_SECONDS );
			$data                       = [
				'user_email'                 => sanitize_email( $user_data['user_email'] ),
				'upgraded_level_id'          => (int) $new_level_id,
				'previous_level_id'          => (int) $old_level_id,
				'level_valid_until'          => (int) $valid_until,
				'minimum_points_to_maintain' => (int) $minimum_points_to_maintain,
			];

			if ( is_object( $existing_record ) && ! empty( $existing_record ) && isset( $existing_record->id ) ) {
				$data['updated_at'] = (int) $now;
				$data['created_at'] = (int) $existing_record->created_at;
				$grace_model->updateRow( $data, [ 'id' => (int) $existing_record->id ] );
			} else {
				$data['created_at'] = (int) $now;
				$grace_model->saveData( $data );
			}
		}
	}

	/**
	 * To change the points based on the settings.
	 *
	 * @param   int  $points
	 * @param        $user
	 *
	 * @return int
	 */
	public static function changePointsForMyAccountRewardPage( int $points, $user ): int {
		return self::resolvePointsBySetting( $points, $user );
	}

	/**
	 * To change the points based on the settings.
	 *
	 * @param   int  $points
	 * @param        $loyalty_user
	 *
	 * @return int
	 */
	public static function changePointsForCampaignsList( int $points, $loyalty_user ): int {
		return self::resolvePointsBySetting( $points, $loyalty_user );
	}

	/**
	 * To change the points based on the settings in launcher.
	 *
	 * @param   int  $points
	 * @param        $user
	 *
	 * @return int|mixed
	 */
	public static function changePointsToGetLevelInLauncher( int $points, $user ) {
		return self::resolvePointsBySetting( $points, $user );
	}

	/**
	 * To get total revenue.
	 *
	 * @param $fields
	 *
	 * @return int
	 */
	public static function getOrderTotal( $fields ): int {
		if ( is_object( $fields ) && isset( $fields->user_email ) ) {
			$billing_email = $fields->user_email;
		} else {
			if ( isset( $fields['user_email'] ) ) {
				$billing_email = $fields['user_email'];
			}
		}

		if ( empty( $billing_email ) ) {
			return 0;
		}


		$order_duration = Controller::getSetting( 'order_duration', '' );
		$order_status   = Settings::get( 'wlr_earning_status' );

		$status_string = "'";

		if ( ! empty( $order_status ) && is_string( $order_status ) ) {
			$order_status = explode( ',', $order_status );
			foreach ( $order_status as $status ) {
				$separator     = next( $order_status ) ? "', '" : "'";
				$status_string .= 'wc-' . $status . $separator;
			}
		}

		$time_stamp_from = self::getDateByString( str_replace( '_', ' ', $order_duration ), 'Y-m-d 00:00:00' );
		$time_stamp_to   = self::getDateByString( 'now' );

		if ( Controller::customOrdersTableIsEnabled() ) {

			$query = "SELECT SUM(wp_wc_orders.total_amount)
                        FROM wp_wc_orders
                        WHERE billing_email LIKE '$billing_email'
                        AND status IN ({$status_string})
                        AND date_created_gmt BETWEEN '$time_stamp_from' AND '$time_stamp_to'";

		} else {

			$query = "SELECT SUM(meta.meta_value) AS order_total
                        FROM wp_posts AS orders
                        JOIN wp_postmeta AS meta ON orders.ID = meta.post_id
                        JOIN wp_postmeta AS email_meta ON orders.ID = email_meta.post_id
                        WHERE orders.post_type = 'shop_order'
                        AND orders.post_status IN ($status_string)
                        AND meta.meta_key = '_order_total'
                        AND email_meta.meta_key = '_billing_email'
                        AND email_meta.meta_value = '$billing_email'
                        AND orders.post_date BETWEEN '$time_stamp_from' AND '$time_stamp_to'";
		}

		global $wpdb;

		return (int) $wpdb->get_var( $query );
	}


	/**
	 * Get date by a date or time string.
	 *
	 * @param   string  $modifier
	 * @param   string  $format
	 *
	 * @return string|false
	 */
	public static function getDateByString( $modifier, $format = 'Y-m-d H:i:s' ) {
		try {
			$datetime = new \DateTime( 'now', wp_timezone() );
			$datetime->modify( $modifier );

			return $datetime->format( $format );
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * After level save.
	 *
	 * @param   mixed  $post_data
	 * @param   mixed  $level_id
	 *
	 * @return void
	 */
	public static function afterLevelSave( $post_data, $level_id ) {
		$grace_period_enabled = Controller::getSetting( 'grace_period_enabled', 0 ) == 1;
		if ( ! $grace_period_enabled ) {
			return;
		}

		$is_edit = ! empty( $post_data['id'] ) && $post_data['id'] > 0;

		if ( $is_edit ) {
			// Get current level data
			$levels_model  = new \Wlr\App\Models\Levels();
			$current_level = $levels_model->getQueryData(
				[ 'id' => [ 'operator' => '=', 'value' => $level_id ] ],
				'*', [], true
			);

			if ( ! $current_level ) {
				return;
			}


			$stored_metadata = Controller::getSetting( 'level_metadata', [] );
			$stored_level    = Controller::findLevelById( $stored_metadata, $level_id );

			if ( ! $stored_level ) {
				// Level not in stored metadata, reset grace periods
				$grace_model   = new GracePeriod();
				$affected_rows = $grace_model->truncateAllGracePeriods();

				wc_get_logger()->add( 'wllp_grace_period', sprintf(
					'Grace period table truncated after level edit (ID: %d) - level not in stored metadata. %d records removed.',
					$level_id,
					$affected_rows
				) );
				self::updateLevelMetadata();

				return;
			}

			// Check if critical properties changed
			$active_changed      = (int) $stored_level['active'] !== (int) $current_level->active;
			$from_points_changed = (int) $stored_level['from_points'] !== (int) $current_level->from_points;
			$to_points_changed   = (int) $stored_level['to_points'] !== (int) $current_level->to_points;

			if ( $active_changed || $from_points_changed || $to_points_changed ) {
				$grace_model   = new GracePeriod();
				$affected_rows = $grace_model->truncateAllGracePeriods();

				wc_get_logger()->add( 'wllp_grace_period', sprintf(
					'Grace period table truncated after critical level edit (ID: %d). Changes: active=%s, from_points=%s, to_points=%s. %d records removed.',
					$level_id,
					$active_changed ? 'yes' : 'no',
					$from_points_changed ? 'yes' : 'no',
					$to_points_changed ? 'yes' : 'no',
					$affected_rows
				) );
				self::updateLevelMetadata();
			}
		}
	}

	/**
	 * After level delete.
	 *
	 * @param   mixed  $level_id
	 *
	 * @return void
	 */
	public static function afterLevelDelete( $level_id ) {
		$grace_period_enabled = Controller::getSetting( 'grace_period_enabled', 0 ) == 1;
		if ( ! $grace_period_enabled ) {
			return;
		}

		$grace_model    = new GracePeriod();
		$affected_count = $grace_model->truncateAllGracePeriods();

		wc_get_logger()->add( 'wllp_grace_period', sprintf(
			'Grace period table truncated after level delete (ID: %d). %d records removed.',
			$level_id,
			$affected_count
		) );
		self::updateLevelMetadata();
	}

	/**
	 * After level toggle.
	 *
	 * @param   mixed  $level_id
	 * @param   mixed  $active
	 *
	 * @return void
	 */
	public static function afterLevelToggle( $level_id, $active ) {
		$grace_period_enabled = Controller::getSetting( 'grace_period_enabled', 0 ) == 1;
		if ( ! $grace_period_enabled ) {
			return;
		}

		$grace_model    = new GracePeriod();
		$affected_count = $grace_model->truncateAllGracePeriods();

		wc_get_logger()->add( 'wllp_grace_period', sprintf(
			'Grace period table truncated after level toggle (ID: %d, Active: %d). %d records removed.',
			$level_id,
			$active,
			$affected_count
		) );
		self::updateLevelMetadata();
	}

	/**
	 * After level bulk action.
	 *
	 * @param   mixed  $action_mode
	 * @param   mixed  $level_id
	 *
	 * @return void
	 */
	public static function afterLevelBulkAction( $action_mode, $level_id ) {
		$grace_period_enabled = Controller::getSetting( 'grace_period_enabled', 0 ) == 1;
		if ( ! $grace_period_enabled ) {
			return;
		}

		$grace_model    = new GracePeriod();
		$affected_count = $grace_model->truncateAllGracePeriods();

		wc_get_logger()->add( 'wllp_grace_period', sprintf(
			'Grace period table truncated after bulk action (%s, ID: %d). %d records removed.',
			$action_mode,
			$level_id,
			$affected_count
		) );
	}

	/**
	 * Update level metadata after grace period reset
	 *
	 * @return void
	 */
	private static function updateLevelMetadata() {
		$current_settings = get_option( 'wllp_settings_data', [] );
		$updated_settings = Controller::addLevelsMetaData( $current_settings );
		update_option( 'wllp_settings_data', $updated_settings );

		wc_get_logger()->add( 'wllp_grace_period', 'Level metadata updated after grace period reset.' );
	}

	/**
	 * Display grace period to user
	 *
	 * @return void
	 */

	public static function displayGracePeriodToUser() {
		$grace_period_enabled = Controller::getSetting( 'grace_period_enabled', 0 ) == 1;
		if ( ! $grace_period_enabled ) {
			return;
		}
		$user = wp_get_current_user();
		if ( empty( $user ) ) {
			return;
		}
		$user_email = $user->user_email;
		if ( empty( $user_email ) ) {
			return;
		}
		$user_model = new Users();
		$where      = [
			'user_email' => [
				'operator' => '=',
				'value'    => $user->user_email
			]
		];

		$loyalty_user = $user_model->getQueryData( $where, '*', [], true );
		if ( ! is_object( $loyalty_user ) || ! isset( $loyalty_user->id ) || (int) $loyalty_user->id <= 0 ) {
			return;
		}

		$grace_period_model = new GracePeriod();
		$existing_record    = $grace_period_model->getLatestRecordByEmail( $user_email );
		if ( ! is_object( $existing_record ) || ! isset( $existing_record->id ) || (int) $existing_record->id <= 0 ) {
			return;
		}

		$now         = strtotime( gmdate( 'Y-m-d H:i:s' ) );
		$valid_until = (int) $existing_record->level_valid_until;

		if ( $valid_until <= $now ) {
			//Grace period has expired
			return;
		}

		$remaining_seconds = $valid_until - $now;
		$remaining_days    = floor( $remaining_seconds / DAY_IN_SECONDS );
		
		if ( $remaining_days == 0 ) {
			$remaining_hours = floor( $remaining_seconds / HOUR_IN_SECONDS );
		}

		$levels_model  = new \Wlr\App\Models\Levels();
		$where         = [
			'id' => [
				'operator' => '=',
				'value'    => (int) $existing_record->upgraded_level_id
			]
		];
		$current_level = $levels_model->getQueryData( $where, '*', [], true );

		$current_level_name = is_object( $current_level ) && isset( $current_level->name ) ? $current_level->name : '';

		if ( ! empty( $remaining_hours ) && $remaining_days == 0 ) {
			$time_display = sprintf( _n( '%d hour', '%d hours', $remaining_hours, 'wllp-point-based-level' ),
				$remaining_hours );
		} else {
			$time_display = sprintf( _n( '%d day', '%d days', $remaining_days, 'wllp-point-based-level' ),
				$remaining_days );
		}

		$template_data = [
			'current_level_name' => $current_level_name,
			'time_display'       => $time_display,
			'minimum_points'     => (int) $existing_record->minimum_points_to_maintain,
			'level_id'           => (int) $existing_record->upgraded_level_id,
			'level_data'         => $current_level,
		];

		self::loadGracePeriodTemplate( $template_data );
	}

	/**
	 * Load grace period template
	 *
	 * @param   array  $data  Template data
	 *
	 * @return void
	 */
	private static function loadGracePeriodTemplate( $data ) {
		$file_path = get_theme_file_path( 'wllp-point-based-level/grace_period_display.php' );
		if ( ! file_exists( $file_path ) ) {
			$file_path = WLLP_PLUGIN_PATH . 'App/Views/Site/grace_period_display.php';
		}
		Controller::renderTemplate( $file_path, $data );
	}
}