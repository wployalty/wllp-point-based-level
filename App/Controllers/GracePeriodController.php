<?php

namespace WLLP\App\Controllers;

defined( 'ABSPATH' ) or die;

use WLLP\App\Helpers\Util;
use WLLP\App\Models\GracePeriod;
use Wlr\App\Helpers\Base;
use Wlr\App\Models\Levels;

class GracePeriodController {

	/**
	 * Filter points based on grace period logic
	 *
	 * @param   int    $points
	 * @param   array  $user_fields
	 *
	 * @return int
	 */
	public static function filterPoints( int $points, array $user_fields ): int {
		$user_email = $user_fields['user_email'] ?? '';
		if ( empty( $user_email ) ) {
			return $points;
		}

		// Prepare level data for comparisons
		$rank_by_id         = self::buildRankMapping();
		$points_to_eval     = Actions::resolvePointsBySetting( $points, $user_fields );
		$levels_model       = new \Wlr\App\Models\Levels();
		$current_level_id   = $levels_model->getCurrentLevelId( (int) $points_to_eval ); // 0 for no level
		$current_level_rank = isset( $rank_by_id[ $current_level_id ] ) ? $rank_by_id[ $current_level_id ] : - 1; // -1 for no level
		$grace_record       = self::getGracePeriodRecord( $user_email );
		// No grace record found, create one (inactive) based on current level, return normal points
		if ( ! $grace_record ) {
			return self::handleNoGraceRecord( $user_email, $current_level_id, $points_to_eval );
		}

		// Grace active → always maintain locked level
		if ( self::isGracePeriodActive( $grace_record ) ) {
			return self::handleActiveGracePeriod( $grace_record, $rank_by_id, $current_level_id, $current_level_rank,
				$points_to_eval );
		}

		// If expired, clean up and proceed as inactive (treated like no grace)
		if ( self::isGracePeriodExpired( $grace_record ) ) {
			return self::handleExpiredGracePeriod( $grace_record, $user_email, $current_level_id, $points_to_eval );
		}

		// Inactive record exists
		if ( $grace_record ) {
			return self::handleInactiveGracePeriod( $grace_record, $rank_by_id, $current_level_id, $current_level_rank,
				$points_to_eval );
		}

		//fallback: no active grace and not degrading → return evaluated points
		return (int) $points_to_eval;
	}

	private static function handleNoGraceRecord( $user_email, $current_level_id, $points_to_eval ): int {
		if ( $current_level_id > 0 ) {
			self::createGracePeriodRecord( [
				'user_email'                 => $user_email,
				'upgraded_level_id'          => (int) $current_level_id,
				'level_valid_until'          => 0, // inactive until an actual degrade happens
				'minimum_points_to_maintain' => (int) self::getLevelMinPoints( $current_level_id ),
			] );
		}

		return (int) $points_to_eval;
	}

	private static function handleActiveGracePeriod(
		$grace_record,
		$rank_by_id,
		$current_level_id,
		$current_level_rank,
		$points_to_eval
	): int {
		if ( self::isAboveMaxLevel( $points_to_eval ) ) {
			self::deleteGracePeriodRecord( $grace_record->id );

			return (int) $points_to_eval;
		}
		$locked_rank = isset( $rank_by_id[ $grace_record->upgraded_level_id ] ) ? $rank_by_id[ $grace_record->upgraded_level_id ] : - 1; // -1 for no level or level rank is returned
		if ( $locked_rank < 0 ) {
			self::deleteGracePeriodRecord( $grace_record->id );

			return (int) $points_to_eval;
		}
		// If upgraded above locked during active grace, update locked and reset grace (inactive)
		if ( self::shouldUpdateLockedLevel( $current_level_rank, $locked_rank ) ) {
			self::updateGracePeriodRecord( $grace_record->id, [
				'upgraded_level_id'          => (int) $current_level_id,
				'level_valid_until'          => 0, // reset; new grace will start on next degrade
				'minimum_points_to_maintain' => (int) self::getLevelMinPoints( $current_level_id ),
			] );

			// Return required points to maintain the new locked level
			return (int) self::getLevelMinPoints( $current_level_id );
		}

		// At or below locked during active grace → maintain locked level
		return (int) $grace_record->minimum_points_to_maintain;
	}

	private static function handleExpiredGracePeriod(
		$grace_record,
		$user_email,
		$current_level_id,
		$points_to_eval
	): int {
		self::deleteGracePeriodRecord( $grace_record->id );
		$grace_record = null;

		if ( $current_level_id > 0 ) {
			self::createGracePeriodRecord( [
				'user_email'                 => $user_email,
				'upgraded_level_id'          => (int) $current_level_id,
				'level_valid_until'          => 0, // inactive until an actual degrade happens
				'minimum_points_to_maintain' => (int) self::getLevelMinPoints( $current_level_id ),
			] );
		}

		return (int) $points_to_eval;
	}

	private static function handleInactiveGracePeriod(
		$grace_record,
		$rank_by_id,
		$current_level_id,
		$current_level_rank,
		$points_to_eval
	): int {
		if ( self::isAboveMaxLevel( $points_to_eval ) ) {
			self::deleteGracePeriodRecord( $grace_record->id );

			return (int) $points_to_eval;
		}
		$locked_rank = isset( $rank_by_id[ $grace_record->upgraded_level_id ] ) ? $rank_by_id[ $grace_record->upgraded_level_id ] : - 1;
		if ( $locked_rank < 0 ) {
			self::deleteGracePeriodRecord( $grace_record->id );

			return (int) $points_to_eval;
		}
		$locked_level_min_points = self::getLevelMinPoints( $grace_record->upgraded_level_id );
		//if degrading below locked, activate and maintain locked
		if ( self::shouldActivateGracePeriod( $current_level_rank,
				$locked_rank ) && (int) $points_to_eval < $locked_level_min_points ) {
			$grace_period_days = (int) Controller::getSetting( 'grace_period_days',
				Util::getDefaults( 'grace_period_days' ) );
			if ( $grace_period_days > 0 ) {
				$valid_until = strtotime( gmdate( "Y-m-d H:i:s" ) ) + ( $grace_period_days * DAY_IN_SECONDS );
				self::updateGracePeriodRecord( $grace_record->id, [ 'level_valid_until' => (int) $valid_until ] );
			}

			return (int) $grace_record->minimum_points_to_maintain;
		} elseif ( self::shouldMaintainLockedLevel( $current_level_rank, $locked_rank ) ) {
			// If at locked level, maintain locked level
			return (int) $grace_record->minimum_points_to_maintain;
		} elseif ( self::shouldUpdateLockedLevel( $current_level_rank, $locked_rank ) ) {
			// If upgraded above locked, update locked and reset grace (inactive)
			self::updateGracePeriodRecord( $grace_record->id, [
				'upgraded_level_id'          => (int) $current_level_id,
				'level_valid_until'          => 0, // reset; new grace will start
				'minimum_points_to_maintain' => (int) self::getLevelMinPoints( $current_level_id ),
			] );

			// Return required points to maintain the new locked level
			return (int) self::getLevelMinPoints( $current_level_id );
		}

		return (int) $points_to_eval;
	}

	private static function isGracePeriodExpired( $grace_record ): bool {
		if ( ! $grace_record || ! isset( $grace_record->level_valid_until ) ) {
			return false;
		}

		$now = strtotime( gmdate( "Y-m-d H:i:s" ) );

		return $grace_record->level_valid_until > 0 && $grace_record->level_valid_until < $now;
	}

	private static function shouldActivateGracePeriod( $current_rank, $locked_rank ): bool {
		return $current_rank >= 0 && $locked_rank >= 0 && $current_rank < $locked_rank;
	}

	private static function shouldUpdateLockedLevel( $current_rank, $locked_rank ): bool {
		return $current_rank >= 0 && $locked_rank >= 0 && $current_rank > $locked_rank;
	}

	private static function shouldMaintainLockedLevel( $current_rank, $locked_rank ): bool {
		return $current_rank >= 0 && $locked_rank >= 0 && $current_rank === $locked_rank;
	}

	private static function isAboveMaxLevel( $points ): bool {
		$highest_level = self::getHighestLevel();
		if ( ! $highest_level ) {
			return $points;
		}
		$max_points = $highest_level->to_points ?? null;

		return ! empty( $max_points ) && $points > $max_points;
	}

	/**
	 * Get grace period record for user
	 */
	private static function getGracePeriodRecord( $user_email ) {
		$grace_model = new GracePeriod();

		return $grace_model->getLatestRecordByEmail( $user_email );
	}

	/**
	 * Create new grace period record
	 */
	private static function createGracePeriodRecord( $data ) {
		$grace_model = new GracePeriod();
		$now         = strtotime( gmdate( "Y-m-d H:i:s" ) );

		$record_data = [
			'user_email'                 => sanitize_email( $data['user_email'] ),
			'upgraded_level_id'          => (int) $data['upgraded_level_id'],
			'level_valid_until'          => (int) $data['level_valid_until'],
			'minimum_points_to_maintain' => (int) $data['minimum_points_to_maintain'],
			'created_at'                 => $now,
			'updated_at'                 => $now,
		];

		return $grace_model->saveData( $record_data );
	}

	/**
	 * Update existing grace period record
	 */
	private static function updateGracePeriodRecord( $id, $data ) {
		$grace_model = new GracePeriod();
		$now         = strtotime( gmdate( "Y-m-d H:i:s" ) );

		$update_data = [
			'updated_at' => $now,
		];

		// Add only provided fields
		if ( isset( $data['upgraded_level_id'] ) ) {
			$update_data['upgraded_level_id'] = (int) $data['upgraded_level_id'];
		}
		if ( isset( $data['level_valid_until'] ) ) {
			$update_data['level_valid_until'] = (int) $data['level_valid_until'];
		}
		if ( isset( $data['minimum_points_to_maintain'] ) ) {
			$update_data['minimum_points_to_maintain'] = (int) $data['minimum_points_to_maintain'];
		}

		return $grace_model->updateRow( $update_data, [ 'id' => (int) $id ] );
	}

	/**
	 * Delete grace period record
	 */
	private static function deleteGracePeriodRecord( $id ) {
		$grace_model = new GracePeriod();

		return $grace_model->deleteRow( [ 'id' => (int) $id ] );
	}

	/**
	 * Get minimum points for a level
	 */
	private static function getLevelMinPoints( $level_id ) {
		$levels = self::sortActiveLevels();
		if ( ! is_array( $levels ) || empty( $levels ) ) {
			return 0;
		}

		foreach ( $levels as $level ) {
			if ( (int) $level->id === (int) $level_id ) {
				return (int) $level->from_points;
			}
		}

		return 0;
	}

	/**
	 * Check if grace period is active
	 */
	private static function isGracePeriodActive( $grace_record ) {
		if ( ! $grace_record || ! isset( $grace_record->level_valid_until ) ) {
			return false;
		}

		$now       = strtotime( gmdate( "Y-m-d H:i:s" ) );
		$is_active = $grace_record->level_valid_until > 0 && $grace_record->level_valid_until > $now;

		return $is_active;
	}

	/**
	 * Build rank mapping from active levels
	 *
	 * @return array
	 */
	private static function buildRankMapping() {
		$levels        = self::sortActiveLevels();
		$rank_by_id    = [];
		$rank_by_id[0] = 0;
		if ( is_array( $levels ) && ! empty( $levels ) ) {
			foreach ( $levels as $index => $level ) {
				$rank_by_id[ $level->id ] = $index + 1;
			}
		}

		return $rank_by_id;
	}

	public static function sortActiveLevels( $sort_order = 'asc' ) {
		if ( ! in_array( strtolower( $sort_order ), [ 'asc', 'desc' ] ) ) {
			return false;
		}

		$level_helper = new Levels();
		$base_helper  = new Base();

		if ( ! $base_helper->isPro() ) {
			return false;
		}

		global $wpdb;

		$sort_direction = strtolower( $sort_order ) === 'asc' ? 'ASC' : 'DESC';

		if ( $sort_direction === 'ASC' ) {
			// For ascending order: sort by to_points ASC, but put to_points = 0 at the end
			$where = $wpdb->prepare( '
            active = %d
            ORDER BY
                CASE WHEN to_points = 0 THEN 1 ELSE 0 END ASC,
                to_points ASC
        ', 1 );
		} else {
			// For descending order: sort by to_points DESC, but put to_points = 0 at the beginning
			$where = $wpdb->prepare( '
            active = %d
            ORDER BY
                CASE WHEN to_points = 0 THEN 0 ELSE 1 END ASC,
                to_points DESC
        ', 1 );
		}

		$levels = $level_helper->getWhere( $where, '*', false );

		return $levels;
	}

	public static function getHighestLevel() {
		$levels = self::sortActiveLevels();

		if ( ! is_array( $levels ) || empty( $levels ) ) {
			return null;
		}

		return end( $levels );
	}


}
