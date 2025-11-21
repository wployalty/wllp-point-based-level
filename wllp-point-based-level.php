<?php
/**
 * Plugin Name: WPLoyalty - Level Options
 * Plugin URI: https://www.wployalty.net
 * Description: The add-on helps you customize levels or tiers based on specific options.
 * Version: 1.0.2
 * Author: Wployalty
 * Slug: wllp-point-based-level
 * Requires Plugins: woocommerce,wp-loyalty-rules
 * Text Domain: wllp-point-based-level
 * Domain Path: /i18n/languages/
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * WC requires at least: 6.5
 * WC tested up to: 10.3
 * Contributors: Wployalty
 * Author URI: https://wployalty.net/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) or die;

if ( ! function_exists( 'wllpIsWpLoyaltyActive' ) ) {
	function wllpIsWpLoyaltyActive() {
		$active_plugins = apply_filters( 'wllp_active_plugins', get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}

		return in_array( 'wp-loyalty-rules/wp-loyalty-rules.php',
				$active_plugins ) || array_key_exists( 'wp-loyalty-rules/wp-loyalty-rules.php', $active_plugins );
	}
}

if ( ! wllpIsWpLoyaltyActive() ) {
	return;
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

defined( 'WLLP_PLUGIN_NAME' ) or define( 'WLLP_PLUGIN_NAME', 'WPLoyalty - Level Options' );
defined( 'WLLP_MINIMUM_PHP_VERSION' ) or define( 'WLLP_MINIMUM_PHP_VERSION', '7.4.0' );
defined( 'WLLP_MINIMUM_WP_VERSION' ) or define( 'WLLP_MINIMUM_WP_VERSION', '6.0' );
defined( 'WLLP_MINIMUM_WC_VERSION' ) or define( 'WLLP_MINIMUM_WC_VERSION', '6.5' );
defined( 'WLLP_MINIMUM_WLR_VERSION' ) or define( 'WLLP_MINIMUM_WLR_VERSION', '1.4.3' );
defined( 'WLLP_PLUGIN_VERSION' ) or define( 'WLLP_PLUGIN_VERSION', '1.0.2' );
defined( 'WLLP_PLUGIN_SLUG' ) or define( 'WLLP_PLUGIN_SLUG', 'wllp-point-based-level' );
defined( 'WLLP_PLUGIN_FILE' ) or define( 'WLLP_PLUGIN_FILE', __FILE__ );
defined( 'WLLP_PLUGIN_PATH' ) or define( 'WLLP_PLUGIN_PATH', str_replace( '\\', '/', __DIR__ ) . '/' );
defined( 'WLLP_VIEW_PATH' ) or define( 'WLLP_VIEW_PATH', str_replace( "\\", '/', __DIR__ ) . '/App/Views' );
defined( 'WLLP_PLUGIN_URL' ) or define( 'WLLP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// to load composer autoload.
if ( file_exists( WLLP_PLUGIN_PATH . '/vendor/autoload.php' ) ) {
	require WLLP_PLUGIN_PATH . '/vendor/autoload.php';
}

add_action( 'plugins_loaded', function () {
	$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/wployalty/wllp-point-based-level',
		__FILE__,
		'wllp-point-based-level'
	);
	$myUpdateChecker->getVcsApi()->enableReleaseAssets();

	if ( method_exists( '\WLLP\App\Helpers\Plugin', 'checkDependencies' )
	     && \WLLP\App\Helpers\Plugin::checkDependencies()
	     && method_exists( '\WLLP\App\Route', 'init' )
	) {
		do_action( 'wllp_before_init' );
		\WLLP\App\Route::init();
		do_action( 'wllp_after_init' );
	}
}, 5 );
if ( class_exists( \WLLP\App\Helpers\Plugin::class ) ) {
	\WLLP\App\Setup::init();
}

