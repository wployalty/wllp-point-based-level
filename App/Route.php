<?php

namespace WLLP\App;

defined( 'ABSPATH' ) or die;

use WLLP\App\Controllers\Actions;
use WLLP\App\Controllers\Controller;

class Route
{

    /**
     * To load hooks.
     *
     * @return void
     */
    public static function init()
    {
        if (is_admin()) { // to load admin hooks.
            self::loadAdminHooks();
        }

        if (Controller::getSetting('levels_from_which_point_based', 'from_total_earned_points') != 'from_total_earned_points') {
            self::loadCommonHooks();
        }
    }

    /**
     * To load common hooks.
     *
     * @return void
     */
    private static function loadCommonHooks()
    {
        add_filter('wlr_points_to_get_level_id', [Actions::class, 'changePointsToGetLevel'], 5, 3);
        add_filter('wlr_points_for_my_account_reward_page', [Actions::class, 'changePointsForMyAccountRewardPage'], 10, 2);
        add_filter('wlr_points_for_campaigns_list', [Actions::class, 'changePointsForCampaignsList'], 10, 2);
    }

    /**
     * To load admin hooks.
     *
     * @return void
     */
    private static function loadAdminHooks()
    {
        add_action('admin_menu', [Controller::class, 'addMenu']);
        add_action('wp_footer', [Controller::class, 'hideMenu']);
        add_action('admin_enqueue_scripts', [Controller::class, 'loadAssets']);
        add_action('wp_ajax_wllp_save_settings', [Controller::class, 'saveSettings']);
    }
}