<?php
namespace WLLP\App\Helpers;

defined( 'ABSPATH' ) || exit;

class Plugin
{

    /**
     * Add admin notice.
     *
     * @param string $message Message.
     * @param string $status Status.
     *
     * @return void
     */
    public static function adminNotice( string $message, string $status = 'success' ) {
        add_action( 'admin_notices', function () use ( $message, $status ) {
            ?>
            <div class="notice notice-<?php echo esc_attr( $status ); ?>">
                <p><?php echo wp_kses_post( $message ); ?></p>
            </div>
            <?php
        }, 1 );
    }
    /**
     * Dependency check.
     *
     * @param bool $allow_exit Allow exit.
     *
     * @return bool
     */
    public static function checkDependencies( bool $allow_exit = false ): bool {
        if ( ! self::isPHPCompatible() ) {
            // translators: First %s will replace plugin name, Second %s replace minimum PHP version
            $message = sprintf( esc_html__( '%1$s requires minimum PHP version %2$s', 'wllp-point-based-level' ), WLLP_PLUGIN_NAME, WLLP_MINIMUM_PHP_VERSION );
            $allow_exit ? die( esc_html( $message ) ) : self::adminNotice( esc_html( $message ), 'error' );

            return false;
        }

        if ( ! self::isWordPressCompatible() ) {
            // translators: First %s will replace plugin name, Second %s replace a minimum WordPress version
            $message = sprintf( esc_html__( '%1$s requires minimum WordPress version %2$s', 'wllp-point-based-level' ), WLLP_PLUGIN_NAME, WLLP_MINIMUM_WP_VERSION );
            $allow_exit ? exit( esc_html( $message ) ) : self::adminNotice( esc_html( $message ), 'error' );

            return false;
        }

        if ( ! self::isActive( 'woocommerce/woocommerce.php' ) ) {
            // translators: 1. %s will replace plugin name
            $message = sprintf( esc_html__( '%1$s requires WooCommerce to be installed and activated in order to be used.', 'wllp-point-based-level' ), WLLP_PLUGIN_NAME );
            $allow_exit ? exit( esc_html( $message ) ) : self::adminNotice( esc_html( $message ), 'error' );

            return false;
        }

        if ( ! self::isWooCompatible() ) {
            // translators: 1. %s will replace plugin name, 2. %s replace WooCommerce version
            $message = sprintf( esc_html__( '%1$s requires minimum Woocommerce version %2$s', 'wllp-point-based-level' ), WLLP_PLUGIN_NAME, WLLP_MINIMUM_WC_VERSION );
            $allow_exit ? exit( esc_html( $message ) ) : self::adminNotice( esc_html( $message ), 'error' );

            return false;
        }
        if ( ! self::isLoyaltyCompatible() ) {
            // translators: 1. %s will replace plugin name, 2. %s replace WooCommerce version
            $message = sprintf( esc_html__( '%1$s requires minimum loyalty version %2$s', 'wllp-point-based-level' ), WLLP_PLUGIN_NAME, WLLP_MINIMUM_WLR_VERSION );
            $allow_exit ? exit( esc_html( $message ) ) : self::adminNotice( esc_html( $message ), 'error' );

            return false;
        }

        return true;
    }

    /**
     * Check php version is compatible.
     * @return bool
     */
    protected static function isPHPCompatible(): bool {
        return (int) version_compare( PHP_VERSION, WLLP_MINIMUM_PHP_VERSION, '>=' ) > 0;
    }

    /**
     * Check WordPress required version.
     * @return bool
     */
    protected static function isWordPressCompatible(): bool {
        return (int) version_compare( get_bloginfo( 'version' ), WLLP_MINIMUM_WP_VERSION, '>=' ) > 0;
    }

    /**
     * Check the plugin are active or not.
     *
     * @param string $plugin_path Plugin path.
     *
     * @return bool
     */
    public static function isActive( string $plugin_path ): bool {
        $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
        if ( is_multisite() ) {
            $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
        }

        return in_array( $plugin_path, $active_plugins ) || array_key_exists( $plugin_path, $active_plugins );
    }

    /**
     * Check woocommerce is compatible.
     * @return bool
     */
    protected static function isWooCompatible(): bool {
        $woo_version = self::getWooVersion();

        return (int) version_compare( $woo_version, WLLP_MINIMUM_WC_VERSION, '>=' ) > 0;
    }

    /**
     * Get Woocommerce version.
     * @return string
     */
    protected static function getWooVersion(): string {
        if ( defined( 'WC_VERSION' ) ) {
            return WC_VERSION;
        }
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        if ( ! file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
            return '1.0.0';
        }
        $plugin_folder = get_plugins( '/woocommerce' );

        return $plugin_folder['woocommerce.php']['Version'] ?? '1.0.0';
    }

    /**
     * Check Spark required version.
     * @return bool
     */
    protected static function isLoyaltyCompatible(): bool {
        return (int) version_compare( self::getLoyaltyVersion(), WLLP_MINIMUM_WLR_VERSION, '>=' ) > 0;
    }

    /**
     * Get Loyalty version.
     *
     * @return string
     */
    protected static function getLoyaltyVersion(): string {
        if ( defined( 'WLR_PLUGIN_VERSION' ) ) {
            return WLR_PLUGIN_VERSION;
        }
        $version = self::getWLRVersion(false);
        if ( $version == '1.0.0' ) {
            $version = self::getWLRVersion();
        }

        return $version;
    }

    /**
     * Get the version of the WP Loyalty Rules plugin.
     *
     * @param bool $force Whether to force to retrieve the version.
     *
     * @return string
     */
    public static function getWLRVersion( bool $force = true ): string {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $folder = 'wp-loyalty-rules';
        $file   = 'wp-loyalty-rules.php';
        if ( $force ) {
            $folder = 'wployalty';
            $file   = 'wp-loyalty-rules-lite.php';
        }
        $plugin_file = $folder . '/' . $file;
        if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
            return '1.0.0';
        }
        $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
        if ( is_multisite() ) {
            $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
        }
        if(!(in_array( $plugin_file, $active_plugins ) || array_key_exists( $plugin_file, $active_plugins ))){
            return '1.0.0';
        }
        $plugin_folder = get_plugins( '/' . $folder );

        return $plugin_folder[ $file ]['Version'] ?? '1.0.0';
    }
}