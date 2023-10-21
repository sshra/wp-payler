<?php

class WC_PAYLER extends WC_Payment_Gateway {

  public function __construct() {
    
    $plugin_dir = plugin_dir_url(__FILE__);

    global $woocommerce;

    $this->id = 'payler';
    $this->icon = apply_filters('woocommerce_payler_icon', ''.substr($plugin_dir, 0, -8) . 'payler.png');
    $this->has_fields = false;
    $this->liveurl = 'https://secure.payler.com';
    $this->testurl = 'https://sandbox.payler.com';

    // Load the settings
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->title = $this->get_option('title');
    $this->payler_key = $this->get_option('payler_key');
    $this->testmode = $this->get_option('testmode');
    $this->testmail = $this->get_option('testmail');
    $this->description = $this->get_option('description');
    $this->instructions = $this->get_option('instructions');
    $this->fiscalcheck = $this->get_option('fiscalcheck');
    $this->saletax = $this->get_option('saletax');

    // Actions
    add_action('valid-payler-standard-ipn-reques', array($this, 'successful_request') );
    add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

    // Save options
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

    // Payment listener/API hook
    add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

    if (!$this->is_valid_for_use()){
      $this->enabled = false;
    }
  }
  
  /**
   * Check if this gateway is enabled and available in the user's country
   */
  function is_valid_for_use() {
    if (!in_array(get_option('woocommerce_currency'), array('RUB'))){
      return false;
    }
    return true;
  }
  
  /**
  * Admin Panel Options 
  * - Options for bits like 'title' and availability on a country-by-country basis
  **/
  public function admin_options() {
    ?>
    <h3><?php _e('PAYLER', 'woocommerce'); ?></h3>
    <p><?php _e('Payment settings via PAYLER.', 'woocommerce'); ?></p>

    <?php if ( $this->is_valid_for_use() ) : ?>

    <table class="form-table">

    <?php     
          // Generate the HTML For the settings form.
          $this->generate_settings_html();
    ?>
    </table><!--/.form-table-->
        
    <?php else : ?>
    <div class="inline error"><p><strong><?php _e('GATEWAY is off', 'woocommerce'); ?></strong>: <?php _e('PAYLER doesn\' support your shop currency.', 'woocommerce' ); ?></p></div>
    <?php
      endif;

    } // End admin_options()

  /**
  * Initialise Gateway Settings Form Fields
  *
  * @access public
  * @return void
  */
  function init_form_fields(){
    $this->form_fields = array(
        'enabled' => array(
          'title' => __('ON/OFF', 'woocommerce'),
          'type' => 'checkbox',
          'label' => __('ON', 'woocommerce'),
          'default' => 'yes'
        ),
        'title' => array(
          'title' => __('Title', 'woocommerce'),
          'type' => 'text', 
          'description' => __( 'This is the title, which user sees on an order checkout stage.', 'woocommerce' ), 
          'default' => __('PAYLER', 'woocommerce')
        ),
        'payler_key' => array(
          'title' => __('API KEY', 'woocommerce'),
          'type' => 'text',
          'description' => __('Please enter your payler integration key ', 'woocommerce'),
          'default' => ''
        ),
        'testmode' => array(
          'title' => __('Test mode', 'woocommerce'),
          'type' => 'checkbox', 
          'label' => __('ON', 'woocommerce'),
          'description' => __('This is the sandbox mode, without real bank transactions.', 'woocommerce'),
          'default' => 'no'
        ),
        'testmail' => array(
          'title' => __('Tester email', 'woocommerce'),
          'type' => 'text', 
          'description' => __('Orders for given email will trigger 1 RUB bills.', 'woocommerce'),
          'default' => ''
        ),        
        'description' => array(
          'title' => __( 'Description', 'woocommerce' ),
          'type' => 'textarea',
          'description' => __( 'Description of payment method for customers.', 'woocommerce' ),
          'default' => 'Оплата с помощью payler.'
        ),
        'fiscalcheck' => array(
          'title' => __('Send fiscal checks', 'woocommerce'),
          'type' => 'checkbox', 
          'label' => __('ON', 'woocommerce'),
          'description' => __('Actvate if you need to send fiscal checks.', 'woocommerce'),
          'default' => 'no'
        ),
        'saletax' => array(
          'title' => __('Check\'s sale tax', 'woocommerce'),
          'type' => 'select', 
          'options' => [
            'none' => 'None',
            'vat0' => '0%',
            'vat10' => '10%',
            'vat20' => '20%',
          ],
          'description' => __('Tax rate in fiscal checks.', 'woocommerce'),
          'default' => 'vat20'
        ),
      );
  }

  /**
  * There are no payment fields for sprypay, but we want to show the description if set.
  **/
  function payment_fields(){
    if ($this->description){
      echo wpautop(wptexturize($this->description));
    }
  }
  
  private function isTestMailer($email) {
    return ($email == $this->testmail) && !empty($this->testmail);
  }

  /**
  * Generate the dibs button link
  **/
  public function generate_form($order_id){
    global $woocommerce;

    $order = new WC_Order( $order_id );

    if ($this->isTestMailer($order->get_billing_email())) {
      $out_summ = '1.00';
    } else {
      $out_summ = number_format($order->order_total, 2, '.', '');
    }

    $crc = $this->payler_merchant . ':' . $out_summ . ':' . $order_id . ':' . $this->payler_key1;
    
    $args = array(
        // Merchant
        'MrchLogin' => $this->payler_merchant,
        'OutSum' => $out_summ,
        'InvId' => $order_id,
        'SignatureValue' => md5($crc),
        'Culture' => 'ru',
      );
      
    foreach ($args as $key => $value){
      $args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
    }
    
    $Data = array(
      "type"    => 1,
      "order_id"  => $order->get_id() . '|' . time(),
      "amount"  => ($this->isTestMailer($order->get_billing_email()) ? 1 : $order->order_total) * 100,
      "userdata"  => $order->order_key
    );

    $payler_result = $this->api_request('/gapi/StartSession', $Data);
    $payler_url = $this->payler_url();
        
    if (isset($payler_result['session_id'])) {
       $order->add_order_note('PaylerOrderID:' . $Data['order_id']);
       return '<form action="' . $payler_url . '/gapi/Pay" method="POST" id="payler_payment_form">'."\n".
      implode("\n", $args_array).
      '<input type="submit" class="button alt" id="submit_payler_payment_form" value="'.__('Оплатить', 'woocommerce').'" /> <a class="button cancel" href="'. wc_get_cart_url() . '">'.__('Отказаться от оплаты & вернуться в корзину', 'woocommerce').'</a>'."\n".
      '<input type="hidden" value="' . $payler_result['session_id'] . '" name="session_id">'.
      '</form>';
    }

    $order->add_order_note('Payler payment error: can\'t create payment session. OrderID:' . $Data['order_id']);
    return '<label>Unable to start payment session via the Payler Gateway. Please, notify an administrator</label><br>
      <a class="button cancel" href="' . wc_get_cart_url() . '">'.__('Back to cart', 'woocommerce').'</a>'."\n".
      '<input type="hidden" value="' . $payler_result['session_id'] . '" name="session_id">'.
      '</form>';
  }
  
  private function payler_url() {
    return $this->testmode == 'yes' ? $this->testurl : $this->liveurl;
  }

  /**
   * PAYLER API low-level request
   */
  private function api_request($command, $Data, $contentType = 'application/x-www-form-urlencoded') {
    $Headers = array(
      'Content-type' => $contentType,
      'Cache-Control' => 'no-cache',
      'charset' => '"utf-8"',
    );
    
    $Data['key'] = $this->payler_key;
    $payler_url = $this->payler_url();
        
    $options = array (
      'timeout' => 45,
      'sslverify' => false,
      'headers' => $Headers,
    );

    if ($contentType == 'application/json') {
      $data_string = json_encode($Data);
      $options['body'] = $data_string;
    } else {
      $options['body'] = $Data;
    }
         
    // Payler API request
    $result = wp_remote_post( $payler_url . $command, $options );

    if (empty($result['body'])) {
      die ('Payler API request error!');
    } else {
      $payler_result = json_decode($result['body'], TRUE);
    }
    return $payler_result;
  }

  /**
   * Process the payment and return the result
   **/
  function process_payment($wc_order_id){
    $order = new WC_Order($wc_order_id);

    return array(
      'result' => 'success',
      'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
    );
  }
  
  /**
   * receipt_page
   **/
  function receipt_page($order){
    echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
    echo $this->generate_form($order);
  }
  
  static function getPaylerOrderId($wp_order_id) {
    global $wpdb;
    $payler_order_id = $wp_order_id = (int) $wp_order_id;
    
    $query = "SELECT * FROM $wpdb->comments WHERE comment_post_ID = $wp_order_id";
    $comments = $wpdb->get_results($query);

    foreach($comments as $comment) {
      $pos = strpos($comment->comment_content, 'PaylerOrderID:');
      if(!($pos === false)) {
        $payler_order_id = substr($comment->comment_content, 14); //14 = length('PaylerOrderID:) 
        break;
      }
    }    
    return $payler_order_id;
  }
  
  /**
   * Check Response (CALLBACK point)
   **/
  function check_ipn_response() {
    global $woocommerce;

    $wp_order_id = intval($_GET['order_id']);
    
    $payler_order_id = $this->getPaylerOrderId($wp_order_id);
    $Data = array(
      "order_id"  => $payler_order_id
    );

    $payler_status = $this->api_request('/gapi/GetStatus', $Data);
    $payler_url = $this->payler_url();

    $payler_edit_order_id = substr($payler_status['order_id'], 0, strpos($payler_status['order_id'], '|'));
    $our_edit_order_id    = $wp_order_id;

    if ($our_edit_order_id == $payler_edit_order_id) {
      if ($payler_status['status'] == 'Charged') {
        $order = new WC_Order($payler_edit_order_id);
        $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
        WC()->cart->empty_cart();

        if ($this->fiscalcheck == 'yes') {
          // sent payler check (KKT) request
          $Data = array('order_id' => $our_edit_order_id, 'payler_order_id' => $payler_order_id);
          $this->kkt($Data);
        }

        wp_redirect( $this->get_return_url( $order ) );
      } else {
        $order = new WC_Order($payler_edit_order_id);
        $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
        wp_redirect($order->get_cancel_order_url());
        exit;
      }
    }    
  }

  /**
   * Request fiscal checks for given order
   */
  public function kktStatus($Data) {
    $order = new WC_Order($Data['order_id']);
    $payler_order_id = $this->getPaylerOrderId($Data['order_id']);

    $Data = array(
      'order_id' => $payler_order_id,
    );
    $result = $this->api_request('/kkt/v2/GetStatus', $Data, 'application/json');
    return $result;
  }

  /**
   * Create fiscal check for given order
   */
  public function kkt($Data) {
    $order = new WC_Order($Data['order_id']);

    if (empty($Data['payler_order_id'])) {
      $payler_order_id = $this->getPaylerOrderId($Data['order_id']);
    } else {
      $payler_order_id = $Data['payler_order_id'];
      unset($Data['payler_order_id']);
    }

    $items = $order->get_items();
    $total = $order->get_total('edit');
    switch ($this->saletax) {
      case 'vat20':
        $tax = round($total * 0.2, 2);  
        break;
      case 'vat10':
        $tax = round($total * 0.1, 2);  
        break;
      case 'vat0':
      default:
        $tax = 0;
        break;
    }

    $Data = array(
      'order_id' => $payler_order_id,
      'type' => 'sell',
      'client_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
      'email' => $order->get_billing_email(),
      'phone' => $order->get_billing_phone(),
      'items' => [],
      'payments' => [
        (object) [
          'type' => 1,
          'sum' => number_format($total, 2),
        ]
      ],
      'vats' => [
        (object) [
          'type' => $this->saletax,
          'sum' => number_format($tax, 2),
        ]
      ]

    );

    foreach ($order->get_items() as $item) {
      $kkt_item = array(
        'name' => $item->get_name('edit'),
        'quantity' => number_format($item->get_quantity('edit'), 3),
        'sum' => number_format($item->get_total('edit'), 2),
        'price' => number_format($item->get_total('edit') / $item->get_quantity('edit'), 2),
        'payment_method' => 'full_prepayment',
        'payment_object' => 'commodity',
        'vat' => (object) ['type' => $this->saletax],
      );
      $Data['items'][] = (object) $kkt_item;
    }

    $result = $this->api_request('/kkt/v2/Receipt', $Data, 'application/json');
    $result['requestData'] = $Data;
    return $result;
  }
  
}