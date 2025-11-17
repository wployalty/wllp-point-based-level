<?php

namespace WLLP\App;

defined( 'ABSPATH' ) or die;

use WLLP\App\Controllers\Actions;
use WLLP\App\Controllers\Controller;
use WLLP\App\Helpers\Util;

class Route {

	/**
	 * To load hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) { // to load admin hooks.
			self::loadAdminHooks();
		}
		$level_based_on = Controller::getSetting( 'levels_from_which_point_based',
			Util::getDefaults( 'levels_from_which_point_based' ) );
		if ( Actions::isGracePeriodEnabled() || $level_based_on != 'from_total_earned_points' ) {
			self::loadCommonHooks();
		}
	}

	/**
	 * To load common hooks.
	 *
	 * @return void
	 */
	private static function loadCommonHooks() {
		add_filter( 'wlr_points_to_get_level_id', [ Actions::class, 'changePointsToGetLevel' ], 5, 3 );
		add_filter( 'wlr_points_for_my_account_reward_page', [ Actions::class, 'changePointsForMyAccountRewardPage' ],
			10, 2 );
		add_filter( 'wlr_points_for_campaigns_list', [ Actions::class, 'changePointsForCampaignsList' ], 10, 2 );
		add_filter( 'wll_points_to_get_level', [ Actions::class, 'changePointsToGetLevelInLauncher' ], 10, 2 );
		add_action( 'wlr_before_customer_reward_page_referral_url_content',
			[ Actions::class, 'displayGracePeriodToUser' ], 10 );
		add_action( 'wp_enqueue_scripts', [ Controller::class, 'loadSiteAssets' ] );
	}

	/**
	 * To load admin hooks.
	 *
	 * @return void
	 */
	private static function loadAdminHooks() {
		add_action( 'admin_menu', [ Controller::class, 'addMenu' ] );
		add_action( 'admin_footer', [ Controller::class, 'hideMenu' ] );
		add_action( 'admin_enqueue_scripts', [ Controller::class, 'loadAssets' ] );
		add_action( 'wp_ajax_wllp_save_settings', [ Controller::class, 'saveSettings' ] );
		// Level admin action hooks
		add_action( 'wlr_after_level_save', [ Actions::class, 'afterLevelSave' ], 10, 2 );
		add_action( 'wlr_after_level_delete', [ Actions::class, 'afterLevelDelete' ], 10, 1 );
		add_action( 'wlr_after_level_toggle', [ Actions::class, 'afterLevelToggle' ], 10, 2 );
		add_action( 'wlr_after_level_bulk_action', [ Actions::class, 'afterLevelBulkAction' ], 10, 2 );
	}
}