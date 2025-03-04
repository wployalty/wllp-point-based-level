<?php
/**
 * Plugin Name: WPLoyalty - Points based level
 * Plugin URI: https://www.wployalty.net
 * Description: Loyalty Rules and Referrals for WooCommerce. Turn your hard-earned sales into repeat purchases by rewarding your customers and building loyalty.
 * Version: 1.0.0
 * Author: Wployalty
 * Slug: wllp-point-based-level
 * Text Domain: wllp-point-based-level
 * Domain Path: /i18n/languages/
 * Requires Plugins: woocommerce
 * Requires at least: 4.9.0
 * WC requires at least: 6.5
 * WC tested up to: 9.6
 * Contributors: Wployalty
 * Author URI: https://wployalty.net/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) or die;

if ( ! function_exists( 'isWpLoyaltyActive' ) ) {
    function isWpLoyaltyActive() {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', []));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
        }
        return in_array('wployalty/wp-loyalty-rules.php', $active_plugins) || array_key_exists('wployalty/wp-loyalty-rules.php', $active_plugins);
    }
}

if (!isWpLoyaltyActive()) {
    return;
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

defined( 'WLLP_PLUGIN_NAME' ) or define( 'WLLP_PLUGIN_NAME', 'WPLoyalty - Points based level' );
defined( 'WLLP_MINIMUM_PHP_VERSION' ) or define( 'WLLP_MINIMUM_PHP_VERSION', '7.4.0' );
defined( 'WLLP_MINIMUM_WP_VERSION' ) or define( 'WLLP_MINIMUM_WP_VERSION', '4.9' );
defined( 'WLLP_MINIMUM_WC_VERSION' ) or define( 'WLLP_MINIMUM_WC_VERSION', '6.0' );
defined( 'WLLP_MINIMUM_WLR_VERSION' ) or define( 'WLLP_MINIMUM_WLR_VERSION', '1.3.1' );
defined( 'WLLP_PLUGIN_VERSION' ) or define( 'WLLP_PLUGIN_VERSION', '1.0.0' );
defined( 'WLLP_PLUGIN_SLUG' ) or define( 'WLLP_PLUGIN_SLUG', 'wllp-point-based-level' );
defined( 'WLLP_PLUGIN_FILE' ) or define( 'WLLP_PLUGIN_FILE', __FILE__ );
defined( 'WLLP_PLUGIN_DIR' ) or define( 'WLLP_PLUGIN_DIR', str_replace( '\\', '/', __DIR__ ) );
defined( 'WLLP_PLUGIN_PATH' ) or define( 'WLLP_PLUGIN_PATH', str_replace( '\\', '/', __DIR__ ) . '/' );
defined( 'WLLP_PLUGIN_URL' ) or define( 'WLLP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// to load composer autoload.
if (file_exists(WLLP_PLUGIN_PATH . '/vendor/autoload.php')) {
    require WLLP_PLUGIN_PATH . '/vendor/autoload.php';
}

add_action('wlr_before_init', function () {
    if (defined('WLLP_PLUGIN_VERSION') && defined('WLLP_MINIMUM_WLR_VERSION')
        && version_compare(WLLP_PLUGIN_VERSION, WLLP_MINIMUM_WLR_VERSION, '<=')
    ) {
        if (method_exists('\WLLP\App\Route', 'init')) {
            do_action('wllp_before_init');
            \WLLP\App\Route::init();
            do_action('wllp_after_init');
        }
    }
});
