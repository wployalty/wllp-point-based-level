<?php

namespace WLLP\App;

use WLLP\App\Helpers\Plugin;
use WLLP\App\Models\GracePeriod;

defined( 'ABSPATH' ) || exit();

class Setup {
	public static function init() {
		register_activation_hook( WLLP_PLUGIN_FILE, [ __CLASS__, 'activate' ] );
		register_deactivation_hook( WLLP_PLUGIN_FILE, [ __CLASS__, 'deactivate' ] );
		register_uninstall_hook( WLLP_PLUGIN_FILE, [ __CLASS__, 'uninstall' ] );

		add_filter( 'plugin_row_meta', [ __CLASS__, 'getPluginRowMeta' ], 10, 2 );
		add_filter( 'plugins_loaded', [ __CLASS__, 'maybeRunMigration' ] );
		add_filter( 'upgrader_process_complete', [ __CLASS__, 'maybeRunMigration' ] );
	}

	/**
	 * Run plugin activation scripts.
	 */
	public static function activate() {
		Plugin::checkDependencies( true );
	}

	/**
	 * Maybe run database migration.
	 */
	public static function maybeRunMigration() {
		$db_version = get_option( 'wllp_version', '0.0.1' );
		if ( version_compare( $db_version, WLLP_PLUGIN_VERSION, '<' ) ) {
			self::runMigration();
			update_option( 'wllp_version', WLLP_PLUGIN_VERSION );
		}
	}

	/**
	 * Run database migration.
	 */
	private static function runMigration() {
		$models = [
			new GracePeriod()
		];
		foreach ( $models as $model ) {
			if ( is_a( $model, '\Wlr\App\Models\Base' ) ) {
				$model->create();
			}
		}
	}

	/**
	 * Run plugin deactivation scripts.
	 */
	public static function deactivate() {
		// Silence is golden.
	}

	/**
	 * Run plugin uninstall scripts.
	 */
	public static function uninstall() {
		// Silence is golden.
	}

	/**
	 * Retrieves the plugin row meta to be displayed on the Woocommerce appointments plugin page.
	 *
	 * @param   array   $links  The existing plugin row meta links.
	 * @param   string  $file   The path to the plugin file.
	 *
	 * @return array
	 */
	public static function getPluginRowMeta( $links, $file ) {
		if ( $file != plugin_basename( WLLP_PLUGIN_FILE ) ) {
			return $links;
		}
		$row_meta = [
			'support' => '<a href="' . esc_url( 'https://wployalty.net/support/' ) . '" aria-label="' . esc_attr__( 'Support',
					'wllp-point-based-level' ) . '">' . esc_html__( 'Support', 'wllp-point-based-level' ) . '</a>',
		];

		return array_merge( $links, $row_meta );
	}
}