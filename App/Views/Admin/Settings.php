<?php
defined( 'ABSPATH' ) or die;

if ( ! isset( $options ) ) {
	return;
}

$wllp_level_based_on_options = \WLLP\App\Controllers\Controller::levelBasedOnOptions() ?? [];
$wllp_purchase_time_list     = \WLLP\App\Controllers\Controller::purchaseTimeList() ?? [];

$wllp_levels_from_which_point_based = $options['levels_from_which_point_based'] ?? \WLLP\App\Helpers\Util::getDefaults( 'levels_from_which_point_based' );
$wllp_order_duration                = $options['order_duration'] ?? '';
$wllp_grace_period_enabled          = $options['grace_period_enabled'] ?? \WLLP\App\Helpers\Util::getDefaults( 'grace_period_enabled' );
$wllp_grace_period_days             = $options['grace_period_days'] ?? \WLLP\App\Helpers\Util::getDefaults( 'grace_period_days' );

?>
<div id="wllp-main">
    <div class="wllp-main-header">
        <h1><?php echo esc_html__( 'WPLoyalty - Level Options', 'wllp-point-based-level' ); ?> </h1>
        <div><b><?php echo "v" . WLLP_PLUGIN_VERSION; ?></b></div>
    </div>
    <div class="wllp-grace-period-warning" style="<?php if ( $wllp_grace_period_enabled != 1 )
		echo 'display: none;' ?>">
        <div class="wllp-notice-header">
            <b><?php echo wp_kses_post( __(
					"Note: Please set up all required levels before enabling the Grace Period. After the Grace Period is enabled, any level changes will reset the Grace Period for all users.",
					'wllp-point-based-level'
				) ) ?></b>
        </div>
    </div>
    <div class="wllp-tabs">
        <a class="nav-tab-active"
           href="<?php echo esc_url( admin_url( 'admin.php?' . http_build_query( [ 'page' => WLLP_PLUGIN_SLUG ] ) ) ) ?>"><i
                    class="wlr wlrf-settings"></i><?php esc_html_e( 'Settings', 'wllp-point-based-level' ) ?></a>
    </div>
    <div>
        <div id="wllp-settings">
            <div class="wllp-setting-page-holder">
                <div class="wllp-spinner">
                    <span class="spinner"></span>
                </div>
                <form id="wllp-settings_form" method="post">
                    <div class="wllp-settings-header">
                        <div class="wllp-setting-heading">
                            <p><?php esc_html_e( 'SETTINGS', 'wllp-point-based-level' ) ?></p>
                        </div>
                        <div class="wllp-button-block">
                            <div class="wllp-back-to-apps wllp-button">
                                <a class="button back-to-apps" target="_self"
                                   href="<?php echo isset( $app_url ) ? esc_url( $app_url ) : '#'; ?>">
									<?php esc_html_e( 'Back to WPLoyalty', 'wllp-point-based-level' ); ?></a>
                            </div>
                            <div class="wllp-save-changes wllp-button">
                                <a class="button" id="wllp-setting-submit-button">
									<?php esc_html_e( 'Save Changes', 'wllp-point-based-level' ); ?></a>
                            </div>
                            <span class='spinner'></span>
                        </div>
                    </div>
                    <div class="wllp-setting-body">
                        <div class="wllp-settings-body-content">
                            <div class="wllp-field-block">
                                <div>
                                    <label
                                            class="wllp-settings-enable-conversion-label"><?php esc_html_e(
											'Levels should be based on',
											'wllp-point-based-level'
										); ?></label>
                                </div>
                                <div class="wllp-input-field">
                                    <select class="wllp-level-points-based" name="levels_from_which_point_based">
										<?php
										foreach ( $wllp_level_based_on_options as $wllp_key => $wllp_name ) {
											?>
                                            <option value="<?php echo esc_attr( $wllp_key ); ?>" <?php echo $wllp_levels_from_which_point_based == $wllp_key ? 'selected="selected"' : ''; ?>>
												<?php echo esc_html( $wllp_name ); ?>
                                            </option>
											<?php
										}
										?>
                                    </select>
                                </div>
                                <div class="wllp-order-field-inputs"
                                     style="<?php if ( $wllp_levels_from_which_point_based != 'from_order_total' )
									     echo 'display: none;' ?>">
                                    <div class="wllp-order-time-input">
                                        <select name="order_duration">
											<?php foreach ( $wllp_purchase_time_list as $wllp_list ) { ?>
                                                <option value="<?php echo esc_attr( $wllp_list['value'] ); ?>" <?php echo $wllp_order_duration == $wllp_list['value'] ? 'selected="selected"' : ''; ?>>
													<?php echo esc_html( $wllp_list['label'] ); ?>
                                                </option>
											<?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="wllp-field-block wllp-grace-period-section"
                                 style="<?php if ( ! in_array(
								     $wllp_levels_from_which_point_based,
								     [ 'from_current_balance', 'from_points_redeemed', 'from_total_earned_points' ]
							     ) )
								     echo 'display: none;' ?>">
                                <div class="wllp-grace-period-checkbox-container">
                                    <input type="checkbox" name="grace_period_enabled"
                                           value="1" <?php echo $wllp_grace_period_enabled == 1 ? 'checked="checked"' : ''; ?>
                                           class="wllp-grace-period-checkbox" id="wllp-grace-period-checkbox">
                                    <label for="wllp-grace-period-checkbox" class="wllp-grace-period-checkbox-label">
										<?php esc_html_e(
											'Enable grace period after level downgrade?',
											'wllp-point-based-level'
										); ?>
                                    </label>
                                </div>
                                <div class="wllp-grace-period-explanation">
                                    <p><?php esc_html_e(
											'How many days should a customer keep their current level before it\'s downgraded?',
											'wllp-point-based-level'
										); ?></p>
                                </div>
                                <div class="wllp-grace-period-days-input"
                                     style="<?php if ( $wllp_grace_period_enabled != 1 )
									     echo 'display: none;' ?>">
                                    <div class="wllp-grace-period-input-container">
                                        <input type="number" name="grace_period_days" id="grace_period_days"
                                               value="<?php echo esc_attr( $wllp_grace_period_days ); ?>" min="1"
                                               max="<?php echo esc_attr( apply_filters( 'wllp_max_grace_period_days',
											       365 ) ); ?>"
                                               class="wllp-grace-period-days">
                                        <div class="wllp-grace-period-days-label">
                                            <p><?php esc_html_e( 'days', 'wllp-point-based-level' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="wllp_grace_period_days_value_block"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>