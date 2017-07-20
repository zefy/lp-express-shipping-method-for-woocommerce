<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


class WC_LPExpress_Courier_Shipping_Method extends WC_Shipping_Method {


	/**
	 * Constructor for your shipping class
	 *
	 * @param int $instance_id Shipping method instance.
	 */
	public function __construct( $instance_id = 0 ) {
		// Meta and input field name
		$this->id                 = 'lpexpress_courier';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( '"LP Express" courier', 'lp-express-shipping-method-for-woocommerce' );
		$this->method_description = __( 'Shipping via "LP Express" courier services', 'lp-express-shipping-method-for-woocommerce' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->field_name = apply_filters( 'wc_shipping_'. $this->id .'_terminals_field_name', 'wc_'. $this->id .'_info' );

		$this->init();

	}

	/**
	 * Initialize LP Express courier
	 *
	 * @return void
	 */
	public function init() {
		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title            = $this->get_option( 'title' );
		$this->tax_status       = $this->get_option( 'tax_status', 'none' );
		$this->cost             = $this->get_option( 'cost', 0 );
		$this->free_shipping    = $this->get_option( 'free_shipping', 0 );

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Define settings field for this shipping method
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'         => __( 'Title', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'text',
				'description'   => __( 'Title to be display on site', 'lp-express-shipping-method-for-woocommerce' ),
				'default'       => __( '"LP Express" courier', 'lp-express-shipping-method-for-woocommerce' ),
				'desc_tip'      => true,
			),

			'cost' => array(
				'title'         => __( 'Cost', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'number',
				'description'   => __( 'Shipping price', 'lp-express-shipping-method-for-woocommerce' ),
				'placeholder'   => '0',
				'default'       => 0,
				'desc_tip'      => true,
			),

			'free_shipping'     => array(
				'title'         => __( 'Free shipping', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'number',
				'description'   => __( 'Free shipping if price greater than (0 - turned off)', 'lp-express-shipping-method-for-woocommerce' ),
				'placeholder'   => '0',
				'default'       => 0,
				'desc_tip'      => true,
			),

			'tax_status' => array(
				'title'         => __( 'Tax status', 'lp-express-shipping-method-for-woocommerce' ),
				'type'          => 'select',
				'class'         => 'wc-enhanced-select',
				'default'       => 'none',
				'options'       => array(
					'taxable'   => __( 'Taxable', 'lp-express-shipping-method-for-woocommerce' ),
					'none'      => _x( 'None', 'Tax status', 'lp-express-shipping-method-for-woocommerce' ),
				),
			),

		);

	}

	/**
	 * This function is used to calculate the shipping cost
	 * We will make it free if possible.
	 *
	 * @uses WC_Shipping_Method::add_rate()
	 * @param mixed $package
	 *
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {

		$rate = array(
			'id' => $this->id,
			'label' => $this->title,
			'cost' => $this->cost,
			'taxes' => TRUE
		);

		if( $this->free_shipping > 0 && $package['contents_cost'] >= $this->free_shipping) {
			$rate['cost'] = 0;
		}

		if( $this->tax_status == 'none' ) {
			$rate['taxes'] = FALSE;
		}

		// Register the rate
		$this->add_rate( $rate );

	}
}