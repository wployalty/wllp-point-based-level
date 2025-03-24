<?php
defined( 'ABSPATH' ) or die;

if (!isset($options)) {
    return;
}

$level_based_on_options = \WLLP\App\Controllers\Controller::levelBasedOnOptions() ?? [];
$purchase_time_list = \WLLP\App\Controllers\Controller::purchaseTimeList() ?? [];

$levels_from_which_point_based = $options['levels_from_which_point_based'] ?? 'from_total_earned_points';
$order_duration = $options['order_duration'] ?? '';

?>
<div id="wllp-main">
    <div class="wllp-main-header">
        <h1><?php echo WLLP_PLUGIN_NAME; ?> </h1>
        <div><b><?php echo "v" . WLLP_PLUGIN_VERSION; ?></b></div>
    </div>
    <div class="wllp-tabs">
        <a class="nav-tab-active"
           href="<?php echo esc_url(admin_url('admin.php?' . http_build_query(array('page' => WLLP_PLUGIN_SLUG)))) ?>"
        ><i class="wlr wlrf-settings"></i><?php esc_html_e('Settings', 'wllp-point-based-level') ?></a>
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
                            <p><?php esc_html_e('SETTINGS', 'wllp-point-based-level') ?></p>
                        </div>
                        <div class="wllp-button-block">
                            <div class="wllp-back-to-apps wllp-button">
                                <a class="button back-to-apps" target="_self"
                                   href="<?php echo isset($app_url) ? esc_url($app_url) : '#'; ?>">
                                    <?php esc_html_e('Back to WPLoyalty', 'wllp-point-based-level'); ?></a>
                            </div>
                            <div class="wllp-save-changes wllp-button">
                                <a class="button" id="wllp-setting-submit-button">
                                    <?php esc_html_e('Save Changes', 'wllp-point-based-level'); ?></a>
                            </div>
                            <span class='spinner'></span>
                        </div>
                    </div>
                    <div class="wllp-setting-body">
                        <div class="wllp-settings-body-content">
                            <div class="wllp-field-block">
                                <div>
                                    <label
                                            class="wllp-settings-enable-conversion-label"><?php esc_html_e('Levels should be based on', 'wllp-point-based-level'); ?></label>
                                </div>
                                <div class="wllp-input-field">
                                    <select class="wllp-level-points-based" name="levels_from_which_point_based">
                                        <?php
                                            foreach ($level_based_on_options as $key =>  $name) {
                                                ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $levels_from_which_point_based == $key ? 'selected="selected"' : ''; ?> >
                                                        <?php esc_html_e($name, 'wllp-point-based-level'); ?>
                                                    </option>
                                                <?php
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="wllp-order-field-inputs" style="<?php  if ($levels_from_which_point_based != 'from_order_total') echo 'display: none;' ?>">
                                    <div class="wllp-order-time-input">
                                        <select name="order_duration">
                                            <?php foreach ($purchase_time_list as $list) { ?>
                                                <option value="<?php echo $list['value']; ?>" <?php echo $order_duration == $list['value'] ? 'selected="selected"' : ''; ?>>
                                                    <?php esc_html_e($list['label']);?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>