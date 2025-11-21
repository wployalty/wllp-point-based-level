<?php

namespace WLLP\App\Models;

use Wlr\App\Models\Base;

class GracePeriod extends Base {
	function __construct() {
		parent::__construct();
		$this->table       = self::$db->prefix . "wllp_grace_period";
		$this->primary_key = "id";
		$this->fields      = [
			'user_email'                 => '%s',
			'upgraded_level_id'          => '%d',
			'level_valid_until'          => '%d',
			'minimum_points_to_maintain' => '%d',
			'created_at'                 => '%d',
			'updated_at'                 => '%d',
		];
	}

	function beforeTableCreation() {
		//Silence is golden
	}

	function runTableCreation() {
		$create_table_query = "CREATE TABLE IF NOT EXISTS {$this->table} (
				`{$this->getPrimaryKey()}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_email` varchar(180) DEFAULT NULL,
				`upgraded_level_id` BIGINT DEFAULT 0,
				`level_valid_until` BIGINT DEFAULT 0,
				`minimum_points_to_maintain` BIGINT DEFAULT 0,
				`created_at` BIGINT DEFAULT 0,
    			`updated_at` BIGINT DEFAULT 0,
				PRIMARY KEY (`{$this->getPrimaryKey()}`)
			)";
		$this->createTable( $create_table_query );
	}

	function afterTableCreation() {
		$index_fields = [
			'user_email',
			'upgraded_level_id',
			'level_valid_until'
		];
		$this->insertIndex( $index_fields );
	}

	function getAllRecordByEmail( $email ) {
		if ( empty( $email ) ) {
			return false;
		}
		global $wpdb;
		$where  = $wpdb->prepare( 'user_email = %s', [ sanitize_email( $email ) ] );
		$result = $this->getWhere( $where, '*', false );

		return $result ? $result : false;
	}

	public function getLatestRecordByEmail( $email ) {
		$where = self::$db->prepare( 'user_email = %s ORDER BY created_at DESC LIMIT 1', sanitize_email( $email ) );

		return $this->getWhere( $where, '*', true );
	}

	public function truncateAllGracePeriods() {
		$count = $this->getActiveGracePeriodCount();
		$query = self::$db->prepare( "TRUNCATE TABLE {$this->table}" );
		self::$db->query( $query );

		return $count;
	}

	public function getActiveGracePeriodCount() {
		$now    = strtotime( gmdate( 'Y-m-d H:i:s' ) );
		$where  = self::$db->prepare( 'level_valid_until > %d', [ $now ] );
		$result = $this->getWhere( $where, 'COUNT(*) as count', true );

		return isset( $result->count ) ? (int) $result->count : 0;
	}

}