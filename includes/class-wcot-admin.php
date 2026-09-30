<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Shipment Tracking" meta box to the WooCommerce order edit screen
 * (HPOS + legacy compatible) and fires the custom tracking email on save
 * when requested.
 */
class WCOT_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save' ) );
		add_action( 'save_post_shop_order', array( __CLASS__, 'save' ) );
		// HPOS (High-Performance Order Storage) screens use this hook instead.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'render_inline' ) );
	}

	public static function add_meta_box() {
		$screen = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'wcot_tracking_box',
			__( 'Shipment Tracking', 'wc-order-tracker' ),
			array( __CLASS__, 'render' ),
			$screen,
			'side',
			'default'
		);
	}

	public static function render( $post_or_order ) {
		$order = ( $post_or_order instanceof WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		self::fields( $order );
	}

	/**
	 * Fallback render for themes/plugins that only fire the classic shipping-address hook.
	 */
	public static function render_inline( $order ) {
		// Avoid duplicate output if the meta box already rendered on this screen.
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
	}

	private static function fields( $order ) {
		wp_nonce_field( 'wcot_save_tracking', 'wcot_tracking_nonce' );
		$number  = $order->get_meta( '_wcot_tracking_number' );
		$carrier = $order->get_meta( '_wcot_tracking_carrier' );
		$url     = $order->get_meta( '_wcot_tracking_url' );
		?>
		<p>
			<label for="wcot_tracking_carrier"><strong><?php esc_html_e( 'Carrier', 'wc-order-tracker' ); ?></strong></label>
			<input type="text" class="widefat" id="wcot_tracking_carrier" name="wcot_tracking_carrier" value="<?php echo esc_attr( $carrier ); ?>" placeholder="e.g. FedEx, UPS, DHL">
		</p>
		<p>
			<label for="wcot_tracking_number"><strong><?php esc_html_e( 'Tracking Number', 'wc-order-tracker' ); ?></strong></label>
			<input type="text" class="widefat" id="wcot_tracking_number" name="wcot_tracking_number" value="<?php echo esc_attr( $number ); ?>">
		</p>
		<p>
			<label for="wcot_tracking_url"><strong><?php esc_html_e( 'Tracking URL', 'wc-order-tracker' ); ?></strong></label>
			<input type="url" class="widefat" id="wcot_tracking_url" name="wcot_tracking_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://…">
		</p>
		<p>
			<label>
				<input type="checkbox" name="wcot_send_email" value="1">
				<?php esc_html_e( 'Send tracking update email to customer on save', 'wc-order-tracker' ); ?>
			</label>
		</p>
		<?php
	}

	public static function save( $order_id ) {
		if ( ! isset( $_POST['wcot_tracking_nonce'] ) || ! wp_verify_nonce( $_POST['wcot_tracking_nonce'], 'wcot_save_tracking' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_wcot_tracking_carrier', sanitize_text_field( wp_unslash( $_POST['wcot_tracking_carrier'] ?? '' ) ) );
		$order->update_meta_data( '_wcot_tracking_number', sanitize_text_field( wp_unslash( $_POST['wcot_tracking_number'] ?? '' ) ) );
		$order->update_meta_data( '_wcot_tracking_url', esc_url_raw( wp_unslash( $_POST['wcot_tracking_url'] ?? '' ) ) );
		$order->save();

		if ( ! empty( $_POST['wcot_send_email'] ) ) {
			do_action( 'wcot_send_tracking_email', $order_id );
		}
	}
}
