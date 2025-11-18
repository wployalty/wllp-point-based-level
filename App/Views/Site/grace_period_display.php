<?php
/**
 * Grace Period Display Template
 *
 * @author      WLLP Point Based Level
 * @license     http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) or die;

$current_level_name = $current_level_name ?? '';
$time_display       = $time_display ?? __( '0 days', 'wllp-point-based-level' );
$minimum_points     = $minimum_points ?? 0;
$maximum_points     = $maximum_points ?? 0;
$level_id           = $level_id ?? 0;
$level_data         = $level_data ?? null;

$level_check = isset( $level_data ) && is_object( $level_data ) && isset( $level_data->id ) && ! empty( $level_data->name ) && (int) $level_data->id === (int) $level_id;
?>
<?php if ( isset( $level_id ) && $level_id > 0 && $level_check ): ?>

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
							esc_html__( 'You are currently enjoying %1$s benefits and it expires on %2$s.',
								'wllp-point-based-level' ),
							'<strong>' . esc_html( $current_level_name ) . '</strong>',
							'<strong>' . esc_html( $time_display ) . '</strong>'
						);
						?>
                    </p>
                    <p class="wlr-text-color">
						<?php
						printf(
						/* translators: 1: minimum points, 2: maximum points */
							esc_html__( 'To maintain this level, you need to maintain your points between %1$d to %2$d by the end of the grace period.',
								'wllp-point-based-level' ),
							(int) $minimum_points,
							(int) $maximum_points
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
