<?php

namespace WLLP\App\Controllers;

defined( 'ABSPATH' ) or die;

use Wlr\App\Helpers\Settings;

class Actions
{

    /**
     * To change the points based on the settings.
     *
     * @param int $points
     * @param array $user_fields
     *
     * @return int
     */
    public static function changePointsToGetLevel(int $points, array $user_fields): int
    {
        $setting = Controller::getSetting('levels_from_which_point_based', '');

        if ($setting == 'from_current_balance' && isset($user_fields['points'])) {
            $points = $user_fields['points'];
        } else if ($setting == 'from_points_redeemed' && isset($user_fields['used_total_points'])) {
            $points = $user_fields['used_total_points'];
        } else if ($setting == 'from_order_total') {
            $points = self::getOrderTotal($user_fields);
        }

        return $points;
    }

    /**
     * To change the points based on the settings.
     *
     * @param int $points
     * @param $user
     *
     * @return int
     */
    public static function changePointsForMyAccountRewardPage(int $points, $user): int
    {
        $setting = Controller::getSetting('levels_from_which_point_based', '');

        if ($setting == 'from_current_balance' && isset($user->points)) {
            $points = $user->points;
        } else if ($setting == 'from_points_redeemed' && isset($user->used_total_points)) {
            $points = $user->used_total_points;
        } else if ($setting == 'from_order_total') {
            $points = self::getOrderTotal($user);
        }

        return $points;
    }

    /**
     * To change the points based on the settings.
     *
     * @param int $points
     * @param $loyalty_user
     *
     * @return int
     */
    public static function changePointsForCampaignsList(int $points, $loyalty_user): int
    {
        $setting = Controller::getSetting('levels_from_which_point_based', '');

        if ($setting == 'from_current_balance' && isset($loyalty_user->points)) {
            $points = $loyalty_user->points;
        } else if ($setting == 'from_points_redeemed' && isset($loyalty_user->used_total_points)) {
            $points = $loyalty_user->used_total_points;
        } else if ($setting == 'from_order_total') {
            $points = self::getOrderTotal($loyalty_user);
        }

        return $points;
    }

    /**
     * To get total revenue.
     *
     * @param $fields
     * @return int
     */
    public static function getOrderTotal($fields): int
    {
        if (is_object($fields) && isset($fields->user_email)) {
            $billing_email = $fields->user_email;
        } else {
            if (isset($fields['user_email'])) {
                $billing_email = $fields['user_email'];
            }
        }

        if (empty($billing_email)) {
            return 0;
        }


        $order_duration = Controller::getSetting('order_duration', '');
        $order_status = Settings::get('wlr_earning_status');

        $status_string = "'";

        if (!empty($order_status) && is_string($order_status)) {
            $order_status = explode(',', $order_status);
            foreach ($order_status as $status) {
                $separator = next($order_status) ? "', '" : "'";
                $status_string .= 'wc-'. $status . $separator;
            }
        }

        $time_stamp_from = self::getDateByString(str_replace('_', ' ', $order_duration), 'Y-m-d 00:00:00');
        $time_stamp_to = self::getDateByString('now');

        if (Controller::customOrdersTableIsEnabled()) {

            $query = "SELECT SUM(wp_wc_orders.total_amount)
                        FROM wp_wc_orders
                        WHERE billing_email LIKE '$billing_email'
                        AND status IN ({$status_string})
                        AND date_created_gmt BETWEEN '$time_stamp_from' AND '$time_stamp_to'" ;

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
                        AND orders.post_date BETWEEN '$time_stamp_from' AND '$time_stamp_to'" ;
        }

        global $wpdb;
        return (int) $wpdb->get_var($query);
    }



    /**
     * Get date by a date or time string.
     *
     * @param string $modifier
     * @param string $format
     * @return string|false
     */
    public static function getDateByString($modifier, $format = 'Y-m-d H:i:s')
    {
        try {
            $datetime = new \DateTime('now', wp_timezone());
            $datetime->modify($modifier);
            return $datetime->format($format);
        } catch (\Exception $e) {
            return false;
        }
    }

}