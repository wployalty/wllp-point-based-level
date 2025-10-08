<?php

namespace WLLP\App\Controllers;

defined( 'ABSPATH' ) or die;

use Wlr\App\Controllers\Admin\Labels;
use Wlr\App\Helpers\Base;
use Wlr\App\Helpers\Input;
use Wlr\App\Models\Levels;

defined( 'ABSPATH' ) or die;

class Controller {
	/**
	 * To load admin menu.
	 *
	 * @return void
	 */
	public static function addMenu() {
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
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
	public static function hideMenu() {
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
	public static function displayMenuContent() {
		$data = [
			'options'              => get_option( 'wllp_settings_data', [] ),
			'app_url'              => admin_url( 'admin.php?' . http_build_query( array( 'page' => WLR_PLUGIN_SLUG ) ) ) . '#/apps',
			'highest_level'        => self::sortActiveLevels(),
			'grace_period_enabled' => self::getSetting( 'grace_period_enabled', 0 ),
			'grace_period_days'    => self::getSetting( 'grace_period_days', 30 ),
		];

		ob_start();
		extract( $data );
		include WLLP_PLUGIN_PATH . 'App/Views/Settings.php';
		$html = ob_get_clean();
		echo $html;
	}

	/**
	 * To get settings.
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @return mixed|null
	 */
	public static function getSetting( string $key, $default = null ) {
		$settings = get_option( 'wllp_settings_data', [] );
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * To load assets.
	 *
	 * @return void
	 */
	public static function loadAssets() {
		if ( ! isset( $_GET['page'] ) || empty( $_GET['page'] || $_GET['page'] != WLLP_PLUGIN_SLUG ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		$suffix = '.min';
		if ( defined( 'SCRIPT_DEBUG' ) ) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';
		}

		wp_enqueue_style( WLR_PLUGIN_SLUG . '-alertify',
			WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify' . $suffix . '.css', array(), WLR_PLUGIN_VERSION );
		wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js',
			array(), WLR_PLUGIN_VERSION . '&t=' . time() );

		wp_enqueue_style( WLLP_PLUGIN_SLUG, WLLP_PLUGIN_URL . 'Assets/Admin/Css/wllp-admin.css', [],
			WLLP_PLUGIN_VERSION );

		wp_enqueue_script( WLLP_PLUGIN_SLUG, WLLP_PLUGIN_URL . 'Assets/Admin/Js/wllp-admin.js', [], WLLP_PLUGIN_VERSION,
			true );
		$localize_data = apply_filters( 'wllp_localize_data', [
			'home_url'            => get_home_url(),
			'admin_url'           => admin_url(),
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( WLLP_PLUGIN_SLUG ),
			'saving_button_label' => __( 'Saving...', 'wllp-point-based-level' ),
			'saved_button_label'  => __( 'Save Changes', 'wllp-point-based-level' ),
		] );
		wp_localize_script( WLLP_PLUGIN_SLUG, 'wllp_localize_data', $localize_data );
	}

	/**
	 * To load site assets.
	 *
	 * @return void
	 */
	public static function loadSiteAssets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style( WLLP_PLUGIN_SLUG . '-site', WLLP_PLUGIN_URL . 'Assets/Site/Css/wllp-site.css', [],
			WLLP_PLUGIN_VERSION );
	}

	/**
	 * Render template with data
	 *
	 * @param   string  $file     Template file path
	 * @param   array   $data     Template data
	 * @param   bool    $display  Whether to display or return content
	 *
	 * @return string|void
	 */
	public static function renderTemplate( string $file, array $data = [], bool $display = true ) {
		$content = '';
		if ( file_exists( $file ) ) {
			ob_start();
			extract( $data );
			include $file;
			$content = ob_get_clean();
		}
		if ( $display ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $content;
		} else {
			return $content;
		}
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


	/**
	 * To save settings.
	 *
	 * @return void
	 */
	public static function saveSettings() {
		$response = [];
		$input    = new Input();
		if ( ! wp_verify_nonce( $input->post( 'nonce', '' ), WLLP_PLUGIN_SLUG ) ) {
			$response['error']   = true;
			$response['message'] = esc_html__( 'Settings not saved!', 'wllp-point-based-level' );
			wp_send_json( $response );
		}

		$settings_data = $input->post( 'data', '' );

		if ( ! empty( $settings_data ) ) {
			parse_str( $settings_data, $settings );
			if ( empty( $settings['levels_from_which_point_based'] ) ) {
				$response['error']       = true;
				$response['field_error'] = [
					'levels_from_which_point_based' => esc_html__( 'This field have invalid strings',
						'wllp-point-based-level' )
				];
				$response['message']     = esc_html__( 'Settings not saved!', 'wllp-point-based-level' );
				wp_send_json( $response );
			}

			if ( $settings['levels_from_which_point_based'] != 'from_order_total' ) {
				unset( $settings['order_duration'] );
			}

			$data     = apply_filters( 'wllp_settings_data', [
				'levels_from_which_point_based' => $settings['levels_from_which_point_based'],
			] );
			$settings = self::addLevelsMetaData( $settings );
			update_option( 'wllp_settings_data', $settings );

			$response['error']   = false;
			$response['message'] = esc_html__( 'Settings saved successfully!', 'wllp-point-based-level' );


		} else {
			$response['error']   = true;
			$response['message'] = esc_html__( 'Settings not saved!', 'wllp-point-based-level' );
		}

		wp_send_json( $response );

	}

	public static function addLevelsMetaData( $data ) {
		$level_model      = new Levels();
		$available_levels = $level_model->getAll();

		if ( empty( $available_levels ) ) {
			return $data;
		}

		$levels_meta = [];
		foreach ( $available_levels as $level ) {
			$levels_meta[] = [
				'level_id'    => (int) $level->id,
				'from_points' => (int) $level->from_points,
				'to_points'   => (int) $level->to_points,
				'active'      => (int) $level->active,
			];
		}

		$data['level_metadata'] = $levels_meta;

		return $data;
	}

	/**
	 * Find level by ID in metadata array
	 *
	 * @param   array  $levels_meta
	 * @param   int    $level_id
	 *
	 * @return array|null
	 */
	public static function findLevelById( $levels_meta, $level_id ) {
		if ( ! is_array( $levels_meta ) ) {
			return null;
		}

		foreach ( $levels_meta as $level ) {
			if ( isset( $level['level_id'] ) && (int) $level['level_id'] === (int) $level_id ) {
				return $level;
			}
		}

		return null;
	}

	/**
	 * To get the level options.
	 *
	 * @return array
	 */
	public static function levelBasedOnOptions(): array {
		return apply_filters( 'wllp_level_based_on_options', [
			'from_current_balance'     => __( 'Points Balance', 'wllp-point-based-level' ),
			'from_total_earned_points' => __( 'Earned Points', 'wllp-point-based-level' ),
			'from_points_redeemed'     => __( 'Redeemed Points', 'wllp-point-based-level' ),
			//'from_order_total' => __('Order total', 'wllp-point-based-level'),
		] );
	}

	/**
	 * To get the purchase time list.
	 *
	 * @return array
	 */
	public static function purchaseTimeList(): array {
		return Labels::getPurchaseTimeList();
	}

	/**
	 * Check Custom Orders Table feature (HPOS) is enabled or not.
	 *
	 * @return bool
	 */
	public static function customOrdersTableIsEnabled() {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			if ( method_exists( 'Automattic\WooCommerce\Utilities\OrderUtil',
				'custom_orders_table_usage_is_enabled' ) ) {
				return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			}
		}

		return false;
	}
}