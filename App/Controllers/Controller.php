<?php

namespace WLLP\App\Controllers;

defined( 'ABSPATH' ) or die;

use Wlr\App\Controllers\Admin\Labels;
use Wlr\App\Helpers\Input;

defined( 'ABSPATH' ) or die;

class Controller
{
    /**
     * To load admin menu.
     *
     * @return void
     */
    public static function addMenu()
    {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            add_menu_page(
                WLLP_PLUGIN_NAME,
                WLLP_PLUGIN_NAME,
                'manage_woocommerce',
                WLLP_PLUGIN_SLUG,
                [ self::class, 'displayMenuContent' ],
                'dashicons-megaphone',
                57
            );
        }
    }

    /**
     * To hide menu.
     *
     * @return void
     */
    public static function hideMenu()
    {
        ?>
        <style>
            #toplevel_page_wllp-point-based-level {
                display: none !important;
            }
        </style>
        <?php
    }

    /**
     * To load menu page.
     *
     * @return void
     */
    public static function displayMenuContent()
    {
        $data = [
            'options' => get_option('wllp_settings_data', []),
            'app_url' => admin_url( 'admin.php?' . http_build_query( array( 'page' => WLR_PLUGIN_SLUG ) ) ) . '#/apps',
        ];

        ob_start();
        extract($data);
        include WLLP_PLUGIN_PATH . 'App/Views/Settings.php';
        $html = ob_get_clean();
        echo $html;
    }

    /**
     * To get settings.
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     */
    public static function getSetting(string $key, $default = null)
    {
        $settings = get_option('wllp_settings_data', []);
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        return $default;
    }

    /**
     * To load assets.
     *
     * @return void
     */
    public static function loadAssets()
    {
        if (!isset($_GET['page']) || empty($_GET['page'] || $_GET['page'] != WLLP_PLUGIN_SLUG)) {
            return;
        }

        $suffix = '.min';
        if ( defined( 'SCRIPT_DEBUG' ) ) {
            $suffix = SCRIPT_DEBUG ? '' : '.min';
        }

        wp_enqueue_style( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify' . $suffix . '.css', array(), WLR_PLUGIN_VERSION );
        wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js', array(), WLR_PLUGIN_VERSION . '&t=' . time() );

        wp_enqueue_style(WLLP_PLUGIN_SLUG, WLLP_PLUGIN_URL . 'Assets/Admin/Css/wllp-admin.css', [], WLLP_PLUGIN_VERSION);

        wp_enqueue_script(WLLP_PLUGIN_SLUG, WLLP_PLUGIN_URL . 'Assets/Admin/Js/wllp-admin.js', [], WLLP_PLUGIN_VERSION, true);
        $localize_data = apply_filters('wllp_localize_data', [
            'home_url'            => get_home_url(),
            'admin_url'           => admin_url(),
            'ajax_url'            => admin_url( 'admin-ajax.php' ),
            'nonce'               => wp_create_nonce(WLLP_PLUGIN_SLUG),
            'saving_button_label' => __( 'Saving...', 'wllp-point-based-level' ),
            'saved_button_label'  => __( 'Save Changes', 'wllp-point-based-level' ),
        ]);
        wp_localize_script(WLLP_PLUGIN_SLUG, 'wllp_localize_data', $localize_data);
    }

    /**
     * To save settings.
     *
     * @return void
     */
    public static function saveSettings()
    {
        $response = [];
        $input = new Input();
        if (!wp_verify_nonce($input->post('nonce', ''), WLLP_PLUGIN_SLUG)) {
            $response['error'] = true;
            $response['message'] = esc_html__( 'Settings not saved!', 'wllp-point-based-level' );
            wp_send_json( $response );
        }

        $settings_data = $input->post('data', '');

        if (!empty($settings_data)) {
            parse_str($settings_data, $settings);
            if (empty($settings['levels_from_which_point_based'])) {
                $response['error']       = true;
                $response['field_error'] = [
                    'levels_from_which_point_based' => esc_html__('This field have invalid strings', 'wllp-point-based-level')
                ];
                $response['message']     = esc_html__('Settings not saved!', 'wllp-point-based-level');
                wp_send_json($response);
            }

            if ($settings['levels_from_which_point_based'] != 'from_order_total') {
                unset($settings['order_duration']);
            }

            $data = apply_filters('wllp_settings_data', [
                'levels_from_which_point_based' => $settings['levels_from_which_point_based'],
            ]);

            update_option('wllp_settings_data', $settings);

            $response['error']   = false;
            $response['message'] = esc_html__('Settings saved successfully!', 'wllp-point-based-level');


        } else {
            $response['error']   = true;
            $response['message'] = esc_html__('Settings not saved!', 'wllp-point-based-level');
        }

        wp_send_json( $response );

    }

    /**
     * To get the level options.
     *
     * @return array
     */
    public static function levelBasedOnOptions(): array
    {
        return apply_filters('wllp_level_based_on_options', [
            'from_current_balance' => __('Current balance', 'wllp-point-based-level'),
            'from_total_earned_points' => __('Total earned points', 'wllp-point-based-level'),
            'from_points_redeemed' => __('Points redeemed', 'wllp-point-based-level'),
            //'from_order_total' => __('Order total', 'wllp-point-based-level'),
        ]);
    }

    /**
     * To get the purchase time list.
     *
     * @return array
     */
    public static function purchaseTimeList(): array
    {
        return Labels::getPurchaseTimeList();
    }

    /**
     * Check Custom Orders Table feature (HPOS) is enabled or not.
     *
     * @return bool
     */
    public static function customOrdersTableIsEnabled()
    {
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            if (method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            }
        }
        return false;
    }
}