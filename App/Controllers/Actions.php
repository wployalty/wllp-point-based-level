<?php

namespace WLLP\App\Controllers;

use WLLP\App\Helpers\Util;
use Wlr\App\Models\Users;

defined( 'ABSPATH' ) or die;

use WLLP\App\Models\GracePeriod;
use Wlr\App\Helpers\Settings;

class Actions {

	private const SOURCE_MAP = [
		'from_current_balance'     => 'points',
		'from_points_redeemed'     => 'used_total_points',
		'from_total_earned_points' => 'earn_total_point',
	];

	/**
	 * Check if grace period is enabled
	 *
	 * @return bool
	 */
	public static function isGracePeriodEnabled(): bool {
		return Controller::getSetting( 'grace_period_enabled', Util::getDefaults( 'grace_period_enabled' ) ) == 1;
	}

	/**
	 * Check if grace period is both enabled and applicable for current point type
	 *
	 * @return bool
	 */
	private static function isGracePeriodApplicable(): bool {
		if ( ! self::isGracePeriodEnabled() ) {
			return false;
		}

		$levels_from_which_point_based = Controller::getSetting( 'levels_from_which_point_based',
			Util::getDefaults( 'levels_from_which_point_based' ) );

		return in_array( $levels_from_which_point_based,
			[ 'from_current_balance', 'from_points_redeemed', 'from_total_earned_points' ] );
	}

	/**
	 * To change the points based on the settings.
	 *
	 * @param   int    $points
	 * @param   array  $user_fields
	 *
	 * @return int
	 */
	public static function changePointsToGetLevel( int $points, array $user_fields ): int {
		if ( ! self::isGracePeriodApplicable() ) {
			return self::resolvePointsBySetting( $points, $user_fields );
		} else {
			return GracePeriodController::filterPoints( $points, $user_fields );
		}
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
	public static function resolvePointsBySetting( int $points, $fields ): int {
		$setting = Controller::getSetting( 'levels_from_which_point_based',
			Util::getDefaults( 'levels_from_which_point_based' ) );
		$setting = apply_filters( 'wllp_levels_point_source', $setting, $fields, $points );

		if ( $setting === 'from_order_total' ) {
			return (int) self::getOrderTotal( $fields );
		}

		$map = self::SOURCE_MAP;

		if ( isset( $map[ $setting ] ) && self::hasField( $fields, $map[ $setting ] ) ) {
			$resolved_point = self::getFieldValue( $fields, $map[ $setting ], $points );

			return apply_filters( 'wllp_resolved_points_by_setting', (int) $resolved_point, $setting, $fields,
				$points );
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

		//TODO: When this method is getting into usage, need to rewrite the query to use prepared statements to avoid SQL injection.
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
		if ( ! self::isGracePeriodEnabled() ) {
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


			$stored_metadata = Controller::getSetting( 'level_metadata', Util::getDefaults( 'level_metadata' ) );
			$stored_level    = Controller::findLevelById( $stored_metadata, $level_id );

			if ( ! $stored_level ) {
				// Level not in stored metadata, reset grace periods
				$grace_model   = new GracePeriod();
				$affected_rows = $grace_model->truncateAllGracePeriods();
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
		if ( ! self::isGracePeriodEnabled() ) {
			return;
		}

		$grace_model    = new GracePeriod();
		$affected_count = $grace_model->truncateAllGracePeriods();
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
		if ( ! self::isGracePeriodEnabled() ) {
			return;
		}

		$grace_model    = new GracePeriod();
		$affected_count = $grace_model->truncateAllGracePeriods();
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
		if ( ! self::isGracePeriodEnabled() ) {
			return;
		}

		$grace_model    = new GracePeriod();
		$affected_count = $grace_model->truncateAllGracePeriods();
	}

	/**
	 * Update level metadata after grace period reset
	 *
	 * @return void
	 */
	private static function updateLevelMetadata() {
		$current_settings = get_option( 'wllp_settings_data', Util::getDefaults( 'wllp_settings_data' ) );
		$updated_settings = Controller::addLevelsMetaData( $current_settings );
		update_option( 'wllp_settings_data', $updated_settings );
	}

	/**
	 * Display grace period to user
	 *
	 * @return void
	 */

	public static function displayGracePeriodToUser() {
		if ( ! self::isGracePeriodApplicable() ) {
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

		$levels_model  = new \Wlr\App\Models\Levels();
		$where         = [
			'id' => [
				'operator' => '=',
				'value'    => (int) $existing_record->upgraded_level_id
			]
		];
		$current_level = $levels_model->getQueryData( $where, '*', [], true );

		if ( ! is_object( $current_level ) || ! isset( $current_level->id ) || (int) $current_level->id <= 0 ) {
			return;
		}

		if ( ! isset( $current_level->active ) || (int) $current_level->active !== 1 ) {
			return;
		}

		$current_level_name = is_object( $current_level ) && isset( $current_level->name ) ? $current_level->name : '';
		$expiry_date        = Util::beforeDisplayDate( $valid_until, get_option( 'date_format' ) );
		$expiry_time        = Util::beforeDisplayDate( $valid_until, get_option( 'time_format' ) );

		$template_data = [
			'current_level_name' => $current_level_name,
			'expiry_date'        => $expiry_date,
			'expiry_time'        => $expiry_time,
			'minimum_points'     => (int) $current_level->from_points,
			'maximum_points'     => (int) $current_level->to_points,
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