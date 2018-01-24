<?php

class SuperiorCoin_Gateway extends WC_Payment_Gateway {

    private $reloadTime = 30000;
    private $discount;
    private $confirmed  = false;
    private $superiorcoin_daemon;

	function __construct() {

		$this->id                 = "superiorcoin_gateway";
        $this->method_title       = __("SuperiorCoin GateWay", 'superiorcoin_gateway');
        $this->method_description = __("SuperiorCoin Payment Gateway Plug-in for WooCommerce. ", 'superiorcoin_gateway');
        $this->title              = __("Superiorcoin Gateway", 'superiorcoin_gateway');
        $this->version            = "1";        

        $this->icon               = apply_filters('woocommerce_offline_icon', '');
        $this->has_fields         = false;

        $this->log                = new WC_Logger();

        $this->init_form_fields();
        $this->host               = $this->get_option('daemon_host');
        $this->port               = $this->get_option('daemon_port');
        $this->address            = $this->get_option('superiorcoin_address');
        $this->username           = $this->get_option('username');
        $this->password           = $this->get_option('password');
        $this->discount           = $this->get_option('discount');

        // After init_settings() Is Called, We Can Get The Settings And Load It Into Variables
        $this->init_settings();

        // Turn Setting Into Variables So That It Will Be Useable
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        add_action('admin_notices', array($this, 'do_ssl_check'));
        add_action('admin_notices', array($this, 'validate_fields'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'instruction'));

        if (is_admin()) {
            /* Save Settings */
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2);
        }

        $this->superiorcoin_daemon = new SuperiorCoin_Library($this->host . ':' . $this->port . '/json_rpc', $this->username, $this->password);

	}


	//Setting Forms Fields
	public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable / Disable', 'superiorcoin_gateway'),
                'label'   => __('Enable this payment gateway', 'superiorcoin_gateway'),
                'type'    => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title'    => __('Title', 'superiorcoin_gateway'),
                'type'     => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'superiorcoin_gateway'),
                'default'  => __('SuperiorCoin SUP Payment', 'superiorcoin_gateway')
            ),
            'description' => array(
                'title'    => __('Description', 'superiorcoin_gateway'),
                'type'     => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'superiorcoin_gateway'),
                'default'  => __('Pay securely using SUP.', 'superiorcoin_gateway')

            ),
            'superiorcoin_address' => array(
                'title'    => __('SuperiorCoin Address', 'superiorcoin_gateway'),
                'label'    => __('Useful for people that have not a daemon online'),
                'type'     => 'text',
                'desc_tip' => __('SuperiorCoin Wallet Address', 'superiorcoin_gateway')
            ),
            'daemon_host' => array(
                'title'    => __('SuperiorCoin Wallet RPC Host/ IP', 'superiorcoin_gateway'),
                'type'     => 'text',
                'desc_tip' => __('This is the Daemon Host/IP to authorize the payment with port', 'superiorcoin_gateway'),
                'default'  => 'localhost',
            ),
            'daemon_port' => array(
                'title'    => __('Superiorcoin Wallet RPC Port', 'superiorcoin_gateway'),
                'type'     => 'text',
                'desc_tip' => __('This is the Daemon Host/IP to authorize the payment with port', 'superiorcoin_gateway'),
                'default'  => '18080',
            ),
            'username' => array(
                'title'    => __('Superiorcoin Wallet Username', 'superiorcoin_gateway'),
                'desc_tip' => __('This is the username that you used with your superiorcoin wallet-rpc', 'superiorcoin_gateway'),
                'type'     => __('text'),
                'default'  => __('username', 'superiorcoin_gateway'),

            ),
            'password' => array(
                'title'       => __('SuperiorCoin Wallet RPC Password', 'superiorcoin_gateway'),
                'desc_tip'    => __('This is the password that you used with your superiorcoin wallet-rpc', 'superiorcoin_gateway'),
                'description' => __('you can leave these fields empty if you did not set', 'superiorcoin_gateway'),
                'type'        => __('text'),
                'default'     => ''

            ),
            'discount' => array(
                'title'       => __('% Discount For Using SUP', 'superiorcoin_gateway'),
                'desc_tip'    => __('Provide a discount to your customers for making a private payment with SUP!', 'superiorcoin_gateway'),
                'description' => __('Do you want to spread the word about SuperiorCoin? Offer a small discount! Leave this empty if you do not wish to provide a discount', 'superiorcoin_gateway'),
                'type'        => __('text'),
                'default'     => ''

            ),
            'environment' => array(
                'title'       => __(' Test Mode', 'superiorcoin_gateway'),
                'label'       => __('Enable Test Mode', 'superiorcoin_gateway'),
                'type'        => 'checkbox',
                'description' => __('Check this box if you are using testnet', 'superiorcoin_gateway'),
                'default'     => 'no'
            ),
            'onion_service' => array(
                'title'       => __(' Onion Service', 'superiorcoin_gateway'),
                'label'       => __('Enable Onion Service', 'superiorcoin_gateway'),
                'type'        => 'checkbox',
                'description' => __('Check this box if you are running on an Onion Service (Suppress SSL errors)', 'superiorcoin_gateway'),
                'default'     => 'no'
            ),
        );
    }


    public function admin_options()
    {
        $this->log->add('superiorcoin_gateway', '[SUCCESS] SuperiorCoin Settings OK');

        echo "<h1>SuperiorCoin Payment Gateway</h1>";
        echo "<p>Welcome to SuperiorCoin Extension for WooCommerce.";
        echo "<div style='border:1px solid #DDD; padding:5px 10px; font-weight:bold; color:#223079; background-color:#9ddff3;'>";
        $this->getamountinfo();
        echo "</div>";
        echo "<table class='form-table'>";
        $this->generate_settings_html();
        echo "</table>";
        echo "<h4>Learn more about using a password with the SuperiorCoin wallet-rpc <a href=\"http://superior-coin.com/\">here</a></h4>";
    }

    public function getamountinfo()
    {
        $wallet_amount = $this->superiorcoin_daemon->getbalance();

        if (!isset($wallet_amount)) {
            $this->log->add('superiorcoin_gateway', '[ERROR] No connection with daemon');
            $wallet_amount['balance'] = "0";
            $wallet_amount['unlocked_balance'] = "0";
        }

        $real_wallet_amount      = $wallet_amount['balance'] / 100000000;
        $real_amount_rounded     = round($real_wallet_amount, 8);

        $unlocked_wallet_amount  = $wallet_amount['unlocked_balance'] / 100000000;
        $unlocked_amount_rounded = round($unlocked_wallet_amount, 8);

        echo "Your balance is: " . $real_amount_rounded . " SUP </br>";
        echo "Unlocked balance: " . $unlocked_amount_rounded . " SUP </br>";
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('on-hold', __('Awaiting Offline Payment', 'superiorcoin_gateway'));

        // Reduce Stock Levels
        $order->reduce_order_stock();

        // Remove Cart
        WC()->cart->empty_cart();

        // Return thank you redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    // Submit Payment And Handle Response
    public function validate_fields()
    {
        if ($this->check_superiorcoin() != TRUE) {
            echo "<div class=\"error\"><p>Your SuperiorCoin Address doesn't seem valid. Have you checked it?</p></div>";
        }
    }

    // Validate Fields
    public function check_superiorcoin()
    {
        $superiorcoin_address = $this->settings['superiorcoin_address'];

        if (strlen($superiorcoin_address) == 95 && substr($superiorcoin_address, 1)) {
            return true;
        }

        return false;
    }

    public function instruction($order_id)
    {
        $order       = wc_get_order($order_id);
        $amount      = floatval(preg_replace('#[^\d.]#', '', $order->get_total()));
        $payment_id  = $this->set_paymentid_cookie();
        $currency    = $order->get_currency();
        $amount_SUP2 = number_format($this->changeto($amount, $currency, $payment_id), 2);
        $address     = $this->address;

        if (!isset($address)) {
            // If there isn't address (merchant missed that field!), $address will be the Monero address for donating :)
            $address = " ";
        }

        $uri                      = "superiorcoin:$address?amount=$amount?payment_id=$payment_id";
        $array_integrated_address = $this->superiorcoin_daemon->make_integrated_address($payment_id);
        

        if ($currency === 'USD') {
            $print_integrated_address = $array_integrated_address['integrated_address'];
        } else {
            $print_integrated_address = "SUP payment at the moment only supports USD transactions.";
        }   
         
        if (!isset($array_integrated_address)) {
            $this->log->add('superiorcoin_gateway', '[ERROR] Unable to getting integrated address');
            // Seems that we can't connect with daemon, then set array_integrated_address, little hack
            $array_integrated_address["integrated_address"] = $address;
        }

        $message = $this->verify_payment($payment_id, $amount_SUP2, $order);

        if ($this->confirmed) {
            $color = "006400";
        } else {
            $color = "DC143C";
        }

        $pluginURL = plugins_url() . '/superiorcoin/assets/logo.png';

        echo "<div style='border:5px solid #353334; padding:20px; color: #353334; margin: 20px 0;'>";
        echo "<h4><font color=$color>" . $message . "</font></h4>";
        echo "
                <head>
                    <!--Import Google Icon Font-->
                    <link href='https://fonts.googleapis.com/icon?family=Material+Icons' rel='stylesheet'>
                    <link href='https://fonts.googleapis.com/css?family=Montserrat:400,800' rel='stylesheet'>

                    <!--Browser Website Optimized For Mobile-->
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
                </head>
                <body>                
                    <div class='page-container' style='font-family:Montserrat,sans-serif; font-size:16px;'><!-- page container -->                   
                        <div class='container-SUP-payment'><!-- superiorCoin container payment box -->
                       
                            <div class='header-SUP-payment' style='overflow:hidden; border-top:1px solid #ddd; border-bottom:1px solid #ddd; padding:10px 0; margin-bottom:20px;'><!-- header -->
                                <img src='$pluginURL' style='float:left; margin-right:20px; max-width:85px;'/><span style='font-size:32px; text-transform:uppercase; letter-spacing:2px; line-height:1.4;'>SuperiorCoin<br> Payment</span> 
                            </div><!-- end header -->  

                            <div class='content-SUP-payment'><!-- SUP content box -->
                                <div class='SUP-amount-send'>
                                    <span class='SUP-label'>Send:</span>
                                    <span class='SUP-amount-box'>".$amount_SUP2."</span>
                                    <span class='SUP-box'>SUP</span>
                                </div><br>
                                <div class='SUP-address'>
                                    <span class='SUP-label'>To this address (Integrated Address):</span>
                                    <div class='SUP-address-box' style='background-color:#f0f38b; padding:5px;'>". $print_integrated_address ."</div>
                                </div><br>
                                <div class='SUP-qr-code'>
                                    <span class='SUP-label'>Or scan QR:</span>
                                    <div class='SUP-qr-code-box'><img src='https://api.qrserver.com/v1/create-qr-code/? size=200x200&data=".$uri."' /></div>
                                </div>
                                <div class='clear'></div>
                            </div><br><!-- end content box -->                        
                        
                            <div class='footer-SUP-payment'><!-- footer SUP payment -->
                                <a href='http://superior-coin.com/' target='_blank' style='color:#2196f3;'>Help</a> | <a href='http://superior-coin.com/' target='_blank' style='color:#2196f3;'>About SuperiorCoin</a>
                            </div><!-- end footer SUP payment -->

                        </div><!-- end superiorcoin container payment box -->
                    </div><!-- end page container  -->
                </body>
            ";                
        
        echo "<script type='text/javascript'>setTimeout(function () { location.reload(true); }, $this->reloadTime);</script>";
        echo "</div>";
        echo "<br><br>";
        
    }

    private function set_paymentid_cookie()
    {
        if (!isset($_COOKIE['payment_id'])) {
            $payment_id = bin2hex(openssl_random_pseudo_bytes(8));
            setcookie('payment_id', $payment_id, time() + 2700);
        }
        else{
            $payment_id = $this->sanatize_id($_COOKIE['payment_id']);
        }
        return $payment_id;
    }
    
    public function sanatize_id($payment_id)
    {
        // Limit payment id to alphanumeric characters
        $sanatized_id = preg_replace("/[^a-zA-Z0-9]+/", "", $payment_id);
        return $sanatized_id;
    }

    public function changeto($amount, $currency, $payment_id)
    {
        global $wpdb;
        // This will create a table named whatever the payment id is inside the database "WordPress"
        $create_table = "CREATE TABLE IF NOT EXISTS $payment_id ( rate INT )";
        $wpdb->query($create_table);
        $rows_num     = $wpdb->get_results("SELECT count(*) as count FROM $payment_id");

        if ($rows_num[0]->count > 0) // Checks if the row has already been created or not
        {
            $stored_rate             = $wpdb->get_results("SELECT rate FROM $payment_id");
            $stored_rate_transformed = $stored_rate[0]->rate / 100;
            $sup_live_price          = $this->retriveprice($currency); 

            if ($stored_rate[0]->rate == '0' ) {

                $sup_live_price       = $this->retriveprice($currency);                

                if ($this->discount) {

                    $discount_decimal = $this->discount / 100;
                    $new_amount       = $amount / $sup_live_price;
                    $discount         = $new_amount * $discount_decimal;
                    $final_amount     = $new_amount - $discount;
                    $rounded_amount   = round($final_amount, 8);

                } else {

                    $new_amount       = $amount / $sup_live_price;
                    $rounded_amount   = round($new_amount, 8);

                }

            }    

        } else { // If the row has not been created then the live exchange rate will be grabbed and stored

            $sup_live_price   = $this->retriveprice($currency);
            $new_amount       = $amount / $sup_live_price;
            $rounded_amount   = round($new_amount, 8);

            $wpdb->query("INSERT INTO $payment_id (rate) VALUES ($sup_live_price)");

        }

        return $rounded_amount;
    }


    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway

    public function retriveprice($currency)
    {

        $SUP_price = file_get_contents('https://www.southxchange.com/api/price/SUP/USD');
        $price     = json_decode($SUP_price, TRUE);

        if (!isset($price)) {
            $this->log->add('superiorcoin_gateway', '[ERROR] Unable to get the price of SuperiorCoin');
        }

        switch ($currency) {
            case 'USD':
                return $price['Last'];
            break;   
        }
    }
    
    private function on_verified($payment_id, $amount_atomic_units, $order_id)
    {
        $message = "Payment has been received and confirmed. Thanks!";
        $this->log->add('superiorcoin_gateway', '[SUCCESS] Payment has been recorded. Congratulations!');
        $this->confirmed = true;
        $order = wc_get_order($order_id);
        $order->update_status('completed', __('Payment has been received', 'superiorcoin_gateway'));
        
        global $wpdb;
        $wpdb->query("DROP TABLE $payment_id"); // Drop the table from database after payment has been confirmed as it is no longer needed
                         
        $this->reloadTime = 3000000000000; // Greatly increase the reload time as it is no longer needed
        return $message;
    }
    
    public function verify_payment($payment_id, $amount_SUP2, $order_id)
    {
        /*
         * function for verifying payments
         * Check if a payment has been made with this payment id then notify the merchant
         */
        $message             = "Waiting for your payment to be confirmed.";
        $amount_atomic_units = $amount_SUP2 / 100000000;
        $get_payments_method = $this->superiorcoin_daemon->get_payments($payment_id);

        if (isset($get_payments_method["payments"][0]["amount"])) {
            
            if ($get_payments_method["payments"][0]["amount"] >= $amount_atomic_units) {

                $message = $this->on_verified($payment_id, $amount_atomic_units, $order_id);
            }
            
            if ($get_payments_method["payments"][0]["amount"] < $amount_atomic_units) {

                $totalPayed     = $get_payments_method["payments"][0]["amount"];
                $outputs_count  = count($get_payments_method["payments"]); // number of outputs recieved with this payment id
                $output_counter = 1;

                while($output_counter < $outputs_count) {
                     $totalPayed += $get_payments_method["payments"][$output_counter]["amount"];
                     $output_counter++;
                }

                if($totalPayed >= $amount_atomic_units) {
                    $message = $this->on_verified($payment_id, $amount_atomic_units, $order_id);
                }

            }
        }

        return $message;
    }

    public function do_ssl_check()
    {
        if ($this->enabled == "yes" && !$this->get_option('onion_service')) {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public function connect_daemon()
    {
        $host                 = $this->settings['daemon_host'];
        $port                 = $this->settings['daemon_port'];
        $superiorcoin_library = new SuperiorCoin($host, $port);

        if ($superiorcoin_library->works() == true) {
            echo "<div class=\"notice notice-success is-dismissible\">
                    <p>
                        Everything works! Congratulations and welcome to SuperiorCoin. 
                        <button type=\"button\" class=\"notice-dismiss\"><span class=\"screen-reader-text\">Dismiss this notice.</span></button>
                    </p>
                 </div>";
        } else {

            $this->log->add('superiorcoin_gateway', '[ERROR] Plugin can not reach wallet rpc.');
            echo "<div class=\" notice notice-error\"><p>Error with connection of daemon, see documentation!</p></div>";

        }
    }

}