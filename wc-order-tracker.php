<?php
/**
 * Plugin Name: Kovatz WooCommerce Order Tracker
 * Plugin URI:  https://Kovatz.com
 * Description: Lets customers track their WooCommerce orders with or without logging in, using a Bootstrap-styled tracking form and timeline, plus a custom branded "Order Tracking Update" email.
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Author:      KOVATZ
 * Author URI:  https://profiles.wordpress.org/kovatz/
 * Text Domain: kovatz-woocommerce-order-tracker
 * Requires Plugins: woocommerce
 * Version: 1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WCOT_VERSION', '1.0.1' );
define( 'WCOT_PLUGIN_FILE', __FILE__ );
define( 'WCOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Bail early with an admin notice if WooCommerce isn't active.
 */
function wcot_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Kovatz WooCommerce Order Tracker</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Core bootstrap.
 */
function wcot_init() {
	if ( ! wcot_check_woocommerce() ) {
		return;
	}

	require_once WCOT_PLUGIN_DIR . 'includes/class-wcot-shortcode.php';
	require_once WCOT_PLUGIN_DIR . 'includes/class-wcot-ajax.php';
	require_once WCOT_PLUGIN_DIR . 'includes/class-wcot-admin.php';

	WCOT_Shortcode::init();
	WCOT_Ajax::init();
	WCOT_Admin::init();

	// Register our custom WooCommerce email.
	add_filter( 'woocommerce_email_classes', 'wcot_register_email_class' );
	add_action( 'woocommerce_email_after_order_table', 'wcot_add_tracking_link_to_email', 10, 4 );
}
add_action( 'plugins_loaded', 'wcot_init', 20 );

/**
 * Add the saved shipment tracking URL to standard customer order emails.
 *
 * @param WC_Order $order Order being rendered.
 * @param bool     $sent_to_admin Whether the email is for an administrator.
 * @param bool     $plain_text Whether the email is plain text.
 * @param WC_Email $email Email being rendered.
 */
function wcot_add_tracking_link_to_email( $order, $sent_to_admin, $plain_text, $email ) {
	if ( $sent_to_admin || ! $order instanceof WC_Order || ( isset( $email->id ) && 'wcot_order_tracking' === $email->id ) ) {
		return;
	}

	$tracking_url = $order->get_meta( '_wcot_tracking_url' );
	if ( ! $tracking_url ) {
		return;
	}

	if ( $plain_text ) {
		echo "\n" . esc_html__( 'Track your shipment:', 'wc-order-tracker' ) . ' ' . esc_url( $tracking_url ) . "\n";
		return;
	}

	?>
	<p style="margin:16px 0;">
		<a href="<?php echo esc_url( $tracking_url ); ?>" style="display:inline-block;padding:10px 18px;background-color:#212529;color:#ffffff;text-decoration:none;border-radius:4px;">
			<?php esc_html_e( 'Track Your Shipment', 'wc-order-tracker' ); ?>
		</a>
	</p>
	<?php
}

/**
 * Register the custom tracking email with WooCommerce's email system.
 *
 * @param array $email_classes
 * @return array
 */
function wcot_register_email_class( $email_classes ) {
	if ( ! class_exists( 'WC_Email' ) ) {
		return $email_classes;
	}

	$email_file = WCOT_PLUGIN_DIR . 'includes/class-wcot-email.php';
	if ( ! class_exists( 'WCOT_Email_Order_Tracking', false ) && is_readable( $email_file ) ) {
		require_once $email_file;
	}
	if ( ! class_exists( 'WCOT_Email_Order_Tracking', false ) ) {
		return $email_classes;
	}

	$email_classes['WCOT_Email_Order_Tracking'] = new WCOT_Email_Order_Tracking();
	return $email_classes;
}

/**
 * Enqueue front-end assets only on pages that contain our shortcode.
 */
function wcot_enqueue_assets() {
	global $post;

	$has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wc_order_tracker' );

	// Also always load on the WooCommerce "My Account" area, since we hook a tab in there.
	$is_account_page = function_exists( 'is_account_page' ) && is_account_page();

	if ( ! $has_shortcode && ! $is_account_page ) {
		return;
	}

	// Bootstrap 5 (CDN). If your theme already loads Bootstrap, you can remove this.
	wp_enqueue_style( 'bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3' );
	wp_enqueue_script( 'bootstrap-5-bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array(), '5.3.3', true );

	wp_enqueue_style( 'wcot-style', WCOT_PLUGIN_URL . 'assets/css/wc-order-tracker.css', array( 'bootstrap-5' ), WCOT_VERSION );
	wp_enqueue_script( 'wcot-script', WCOT_PLUGIN_URL . 'assets/js/wc-order-tracker.js', array( 'jquery', 'bootstrap-5-bundle' ), WCOT_VERSION, true );

	wp_localize_script( 'wcot-script', 'WCOT_Vars', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'wcot_track_order' ),
		'i18n'     => array(
			'loading' => __( 'Looking up your order…', 'wc-order-tracker' ),
			'error'   => __( 'We could not find that order. Please check the details and try again.', 'wc-order-tracker' ),
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'wcot_enqueue_assets' );

/**
 * Activation: create default options.
 */
function wcot_activate() {
	if ( false === get_option( 'wcot_settings' ) ) {
		update_option( 'wcot_settings', array(
			'require_login' => 'no', // 'no' = allow guest tracking too.
		) );
	}
}
register_activation_hook( __FILE__, 'wcot_activate' );
