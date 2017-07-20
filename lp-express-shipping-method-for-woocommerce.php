<?php
/**
 * Plugin Name: "LP Express" Shipping Method for WooCommerce
 * Description: "LP Express" shipping to self-service parcel terminals and/or directly to customer via courier
 * Version: 1.0.0
 * Author: Martynas Žaliaduonis
 * Author URI: https://github.com/zefy
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lp-express-shipping-method-for-woocommerce
 * Domain Path: languages
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Main file constant
 */
define( 'LPEXPRESS_SHIPPING_METHOD_MAIN_FILE', __FILE__ );

/**
 * Includes folder path
 */
define( 'LPEXPRESS_SHIPPING_METHOD_MAIN_INCLUDES_PATH', plugin_dir_path( LPEXPRESS_SHIPPING_METHOD_MAIN_FILE ) . 'includes' );


class LPExpress_Shipping_Method_For_WooCommerce {

	/**
	 * Available shipping methods (ID => Class name)
	 *
	 * @var array
	 */
	public $available_methods = array(
		'lpexpress_terminals'   => 'WC_LPExpress_Terminals_Shipping_Method',
		'lpexpress_courier'     => 'WC_LPExpress_Courier_Shipping_Method'
	);

	/**
	 * Array for created shipping methods instances
	 *
	 * @var array
	 */
	public $methods = array( );

	function __construct() {
		// Load plugin functionality when others have loaded
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Class constructor
	 */
	public function plugins_loaded() {
		// Load functionality
		$this->includes();
		$this->load_translations();

		add_action( 'woocommerce_shipping_init', array( $this, 'shipping_init' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'shipping_methods' ) );

		// Allow WC template file search in this plugin
		add_filter( 'woocommerce_locate_template',      array( $this, 'locate_template' ), 20, 3 );
		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_template' ), 20, 3 );

		// Include frontend scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'javascript' ) );
	}

	/**
	 * Required functionality
	 *
	 * @return void
	 */
	public function includes() {
		require_once LPEXPRESS_SHIPPING_METHOD_MAIN_INCLUDES_PATH . '/class-wc-lpexpress-terminals-shipping-method.php';
		require_once LPEXPRESS_SHIPPING_METHOD_MAIN_INCLUDES_PATH . '/class-wc-lpexpress-courier-shipping-method.php';
	}

	/**
	 * Load translations
	 *
	 * @return void
	 */
	function load_translations() {
		load_plugin_textdomain( 'lp-express-shipping-method-for-woocommerce', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Initiating available shipping methods instances
	 *
	 * @return void
	 */
	public function shipping_init( ) {
		foreach( $this->available_methods as $method_id => $method ) {
			$this->methods[ $method_id ] = new $method();
		}
	}

	/**
	 * Returning all available shipping methods instances
	 *
	 * @param array $methods
	 *
	 * @return mixed
	 */
	public function shipping_methods( $methods ) {
		foreach( $this->available_methods as $method_id => $method ) {
			$methods[ $method_id ] = $this->methods[ $method_id ];
		}

		return $methods;
	}

	/**
	 * Locates the WooCommerce template files from this plugin directory
	 *
	 * @param  string $template      Already found template
	 * @param  string $template_name Searchable template name
	 * @param  string $template_path Template path
	 * @return string                Search result for the template
	 */
	public function locate_template( $template, $template_name, $template_path ) {
		// Tmp holder
		$_template = $template;

		if ( ! $template_path ) $template_path = WC_TEMPLATE_PATH;

		// Set our base path
		$plugin_path = plugin_dir_path( LPEXPRESS_SHIPPING_METHOD_MAIN_FILE ) . 'woocommerce/';

		// Look within passed path within the theme - this is priority
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name
			)
		);

		// Get the template from this plugin, if it exists
		if ( ! $template && file_exists( $plugin_path . $template_name ) )
			$template	= $plugin_path . $template_name;

		// Use default template
		if ( ! $template )
			$template = $_template;

		// Return what we found
		return $template;
	}

	/**
	 * Load necessary javascript files
	 *
	 * @return void
	 */
	public function javascript() {
		wp_enqueue_script('lpexpress-checkout', plugin_dir_url(LPEXPRESS_SHIPPING_METHOD_MAIN_INCLUDES_PATH ) . 'woocommerce/js/lpexpress-checkout.js', array(), null, true);
	}
}

/**
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function run_lpexpress_shipping_method_for_woocommerce() {
		new LPExpress_Shipping_Method_For_WooCommerce();
	}

	run_lpexpress_shipping_method_for_woocommerce();
}