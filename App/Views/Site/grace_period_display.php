<?php
/**
 * Grace Period Display Template
 *
 * @author      WLLP Point Based Level
 * @license     http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or die;

$wllp_current_level_name = $current_level_name ?? '';
$wllp_expiry_date        = $expiry_date ?? '';
$wllp_expiry_time        = $expiry_time ?? '';
$wllp_minimum_points     = $minimum_points ?? 0;
$wllp_maximum_points     = $maximum_points ?? 0;
$wllp_level_id           = $level_id ?? 0;
$wllp_level_data         = $level_data ?? null;

$wllp_level_check = isset( $wllp_level_data ) && is_object( $wllp_level_data ) && isset( $wllp_level_data->id ) && ! empty( $wllp_level_data->name ) && (int) $wllp_level_data->id === (int) $wllp_level_id;
?>
<?php if ( isset( $wllp_level_id ) && $wllp_level_id > 0 && $wllp_level_check ): ?>

    <div class="wlr-grace-period-section">
        <div class="wlr-heading-container">
            <h3 class="wlr-heading"><?php esc_html_e( 'Grace Period Status', 'wllp-point-based-level' ); ?></h3>
        </div>
        <div class="wlr-grace-period-box wlr-border-color">
            <div class="wlr-grace-period-content">
                <div class="wlr-grace-period-icon">
                    <i class="wlrf-clock wlr-theme-color-apply" style="font-size: 48px;"></i>
                </div>
                <div class="wlr-grace-period-details">
                    <h4 class="wlr-text-color"><?php esc_html_e( 'Level Grace Period Active',
							'wllp-point-based-level' ); ?></h4>
                    <p class="wlr-text-color">
						<?php
						printf(
						/* translators: 1: current level name, 2: remaining time in grace period */
							esc_html__( 'You are currently enjoying %1$s level benefits, and it expires on %2$s, at %3$s.',
								'wllp-point-based-level' ),
							'<strong>' . esc_html( $wllp_current_level_name ) . '</strong>',
							'<strong>' . esc_html( $wllp_expiry_date ) . '</strong>',
							'<strong>' . esc_html( $wllp_expiry_time ) . '</strong>'
						);
						?>
                    </p>
                    <p class="wlr-text-color">
						<?php
						printf(
						/* translators: 1: minimum points, 2: maximum points */
							esc_html__( 'To maintain this level, you need to maintain your points between %1$d and %2$d by the end of the grace period.',
								'wllp-point-based-level' ),
							(int) $wllp_minimum_points,
							(int) $wllp_maximum_points
						);
						?>
                    </p>
                    <div class="wlr-grace-period-warning">
                        <i class="wlrf-warning wlr-theme-color-apply"></i>
                        <span class="wlr-text-color">
                        <?php esc_html_e( 'After the grace period expires, your level will revert to the corresponding level if you don\'t maintain the minimum points.',
	                        'wllp-point-based-level' ); ?>
                    </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
