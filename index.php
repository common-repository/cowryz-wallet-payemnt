<?php
/*
Plugin Name: Cowryz - Woocommerce
Plugin URI: 
Description: This is Cowryz payment gateway for WooCommerce. Allows you to use Interswitch payment gateway in WooCommerce plugin and empowering any business to collect money online within a minutes.
Version: 1.0
Author: Ifeanyi
Author URI: 
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_action('plugins_loaded', 'woocommerce_cowryz_init', 0);
define('cowryz_imgdir', plugins_url( 'images/', __FILE__ ));


function woocommerce_cowryz_init(){
	if(!class_exists('WC_Payment_Gateway')) return;

    if( isset($_GET['msg']) && !empty($_GET['msg']) ){
        add_action('the_content', 'cowryz_showMessage');
    }
    function cowryz_showMessage($content){
            return '<div class="'.htmlentities($_GET['type']).'">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }

    /**
     * Gateway class
     */
	class WC_cowryz extends WC_Payment_Gateway{
		public function __construct(){
			$this->id 					= 'cowryz';
			$this->method_title 		= 'Cowryz Wallet Payment';
			$this->method_description	= "Redefining Payments, Simplifying Lives";
			$this->cowryz_url 			= "http://www.nubianz.com/payment/";
			$this->has_fields 			= false;
			$this->init_form_fields();
			$this->init_settings();
			if ( $this->settings['showlogo'] == "yes" ) {
				$this->icon 			= cowryz_imgdir . 'logo.png';
			}			
			$this->title 			= $this->settings['title'];
			$this->redirect_page_id = $this->settings['redirect_page_id'];
			if ( $this->settings['testmode'] == "yes" ) {
				$this->firstname 		= 'Nilesh';
				$this->lastname 		= 'OMS';
				$this->email 			= 'eawagu@gmail.com';
				$this->phone 			= '08029400082';
				$this->merchantlogo		= cowryz_imgdir . 'logo.png';
				$this->description 		= $this->settings['description'].
										"<br/><br/><u>Test Mode is <strong>ACTIVE</strong>";
			} else {
				$this->firstname 		= $this->settings['firstname'];
				$this->lastname 		= $this->settings['lastname'];
				$this->email 			= $this->settings['email'];
				$this->phone 			= $this->settings['phone'];
				$this->merchantlogo		= $this->settings['merchantlogo'];
			}					
			$this->msg['message'] 	= "";
			$this->msg['class'] 	= "";
					
			add_action('init', array(&$this, 'check_cowryz_response'));
			//update for woocommerce >2.0
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_cowryz_response' ) );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			
			add_action('woocommerce_receipt_cowryz', array(&$this, 'receipt_page'));
		}
    
		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __('Enable/Disable', 'ifeanyi'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Cowryz Wallet Payment Module.', 'ifeanyi'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
      			'title' => array(
					'title' 		=> __('Title:', 'ifeanyi'),
					'type'			=> 'text',
					'default' 		=> __('Cowryz wallet payment', 'ifeanyi'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'ifeanyi'),
					'desc_tip' 		=> true
				),
      			'description' => array(
					'title' 		=> __('Description:', 'ifeanyi'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay securely by Cowryz wallet payment .', 'ifeanyi'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'ifeanyi'),
					'desc_tip' 		=> true
				),
      			'firstname' 		=> array(
					'title' 		=> __('Merchant First Name', 'ifeanyi'),
					'type' 			=> 'text',
					'description' 	=> '',
					'desc_tip' 		=> true
				),
      			'lastname'			=> array(
					'title' 		=> __('Merchant Last Name', 'ifeanyi'),
					'type' 			=> 'text',
					'description' 	=>  '',
					'desc_tip' 		=> true
                ),
      			'email'				=> array(
					'title' 		=> __('Merchant Email Id', 'ifeanyi'),
					'type' 			=> 'text',
					'description' 	=>  '',
					'desc_tip' 		=> true
                ),
      			'phone'				=> array(
					'title' 		=> __('Merchant Phone Number', 'ifeanyi'),
					'type' 			=> 'text',
					'description' 	=>  '',
					'desc_tip' 		=> true
                ),
      			'merchantlogo'				=> array(
					'title' 		=> __('Merchant Logo URL', 'ifeanyi'),
					'type' 			=> 'text',
					'description' 	=>  '',
					'desc_tip' 		=> true
                ),
				'showlogo' => array(
					'title' 		=> __('Show Logo', 'ifeanyi'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Show the "Cowryz" logo in the Payment Method section for the user', 'ifeanyi'),
					'default' 		=> 'yes',
					'description' 	=> __('Tick to show "Cowryz" logo'),
					'desc_tip' 		=> true
                ),				
      			'testmode' => array(
					'title' 		=> __('TEST Mode', 'ifeanyi'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Cowryz wallet payment TEST Transactions.', 'ifeanyi'),
					'default' 		=> 'no',
					'description' 	=> __('Tick to run TEST Transaction on the Cowryz platform'),
					'desc_tip' 		=> true
                ),
      			'redirect_page_id' => array(
					'title' 		=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->cowryz_get_pages('Select Page'),
					'description' 	=> __('URL of success page', 'ifeanyi'),
					'desc_tip' 		=> true
                )
			);
		}
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
		public function admin_options(){
			echo '<h3>'.__('Cowryz Wallet Payment', 'ifeanyi').'</h3>';
			echo '<p>'.__('Redefining Payments, Simplifying Lives! Empowering any business to collect money online within minutes').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		}
        /**
         *  There are no payment fields for techpro, but we want to show the description if set.
         **/
		function payment_fields(){
			if($this->description) echo wpautop(wptexturize($this->description));
		}
		/**
		* Receipt Page
		**/
		function receipt_page($order){
			echo '<p>'.__('Thank you for your order, please click the button below to pay with Cowryz wallet payment.', 'ifeanyi').'</p>';
			echo $this->generate_cowryz_form($order);
		}
		/**
		* Generate Cowrys link
		**/
		function generate_cowryz_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$paymenttype = 'Cowryz Wallet Payment';		
			$tracode=rand(10000000,99999999);

			if ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) {
				$redirect_url = $order->get_checkout_order_received_url();
			} else {
				$redirect_url = get_permalink($this->redirect_page_id);
			}

			//For wooCoomerce 2.0
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			}

			/*check if currency converstion is enable*/
			$the_order_total = $order->order_total;

			$this->add_field('slna', $this->lastname);
			$this->add_field('sona', $this->firstname);
			$this->add_field('semail', $this->email);
			$this->add_field('sphone', $this->phone);
			$this->add_field('paymenttype', $paymenttype);
			$this->add_field('trancode', $tracode);
			$this->add_field('return', $redirect_url);
			$this->add_field('cancel_return',$redirect_url);
			if($this->merchantlogo!="")
				$this->add_field('merchant_logo',$this->merchantlogo);
			else			
				$this->add_field('merchant_logo',cowryz_imgdir . 'logo.png');
			$this->add_field('shipping_charge', "0"); 	/*In percentage*/
			$this->add_field('tax', "0");	/*In percentage*/
			$this->add_field('discount_rate_cart',"0");
			$this->add_field('cn',$order_id);
			$this->add_field('total_item', sizeof($order->get_items()));
	
			/*Get Items*/
			$item_loop = 1;
			if (sizeof($order->get_items()) > 0) {
				foreach ($order->get_items() as $item) {
					if ($item['qty']) {
						$product = $order->get_product_from_item($item);
						$this->add_field('item_' . $item_loop, $item['name']);
						$this->add_field('amount_' . $item_loop, $order->get_item_total($item, false, false));
						$this->add_field('quantity_' . $item_loop,$item['qty']);
						$item_loop++;
					}
				}
			}	
			$this->submit_cowryz_post(); // submit the fields to Cowryz
		}
		
		
	   function add_field($field, $value) {
		  $this->fields["$field"] = $value;
	   }

	   function submit_cowryz_post() {
		  echo "<html>\n";
		  echo "<head><title>Processing Payment...</title></head>\n";
		  echo "<body onLoad=\"document.forms['cowryz_form'].submit();\">\n";
		  echo "<center><h2>Please wait, your order is being processed and you";
		  echo " will be redirected to the cowryz wallet payment website.</h2></center>\n";
		  echo "<form method=\"post\" name=\"cowryz_form\" ";
		  echo "action=".$this->cowryz_url.">\n";
	
		  foreach ($this->fields as $name => $value) {
			 echo "<input type=\"hidden\" name=\"$name\" value=\"$value\"/>\n";
		  }
		  echo "<center><br/><br/>If you are not automatically redirected to ";
		  echo "cowryz wallet payment within 5 seconds...<br/><br/>\n";
		  echo "<input type=\"submit\" value=\"Click Here\"></center>\n";
		  
		  echo "</form>\n";
		  echo "</body></html>\n";    
	   }
		/**
		* Process the payment and return the result
		**/
		function process_payment($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
				/* 2.1.0 */
				$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				/* 2.0.0 */
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
        	);
		}
		/**
		* Check for valid cowryz server callback
		**/
		function check_cowryz_response(){
			global $woocommerce;
			if( sanitize_text_field($_REQUEST['responsecode'])){
				$order_id = $_REQUEST['cn'];
				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );
						$status = sanitize_text_field($_REQUEST['status']);
						$transauthorised = false;
						if( $order->status !=='completed' ){							
								$status = strtolower($status);
								if($status=="complete"){
									$transauthorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
									$this->msg['class'] = 'woocommerce-message';
										$order->payment_complete();
										$order->add_order_note('Cowryz wallet payment successful.<br/>Cowryz Response Id: '.sanitize_text_field($_REQUEST['responsecode']));
										$woocommerce -> cart -> empty_cart();
								}else{
									$this->msg['class'] = 'woocommerce-error';
									$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									$order->add_order_note('Transaction ERROR: '.$this->respo(sanitize_text_field($_REQUEST['responsecode'])));
								}
							
							if($transauthorised==false){
								$order->update_status('failed');
							}
						}
					}catch(Exception $e){
                        $msg = "Error";
					}
				}

                $redirect_url = ($this->redirect_page_id=="" || $this->redirect_page_id==0)?get_site_url() . "/":get_permalink($this->redirect_page_id);
                //For wooCoomerce 2.0
                $redirect_url = add_query_arg( array('msg'=> urlencode($this->msg['message']), 'type'=>$this->msg['class']), $redirect_url );

                wp_redirect( $redirect_url );
                exit;
			}
		}
		// get all pages
		function cowryz_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
    		}
		/*Get response from cowryz*/
		function respo($rpcode=""){
			$resdeca=array(
						10001=>"Unknown error. Check to see if you have sent a duplicate request reference id in the request xml. Query for the transaction using the Query Transaction method",
						10002=>"Invalid Request. Check your request xml to verify you are sending valid request xml.",
						10003=>"Unauthorized. Please provide valid details and try again later. Please contact Interswitch.",
						10010=>"Partner not setup/Incorrect entity code. The Institution/Biller/Entity Code in your request message has not been configured on Quick Teller.",
						10020=>"Partner not properly configured. Partner/Biller has not been properly configured on Quick Teller. Please contact Interswitch.",
						10030=>"Partner deactivated. Partner/Biller has been deactivated on Quick Teller. Please contact Interswitch.",
						20010=>"Xml Node Missing. Something is missing in your request message. Please check the response xml.",
						20020=>"Xml Node Value Empty. You have sent an empty xml node. Please check the response xml.",
						20030=>"Xml Node Value not valid. Please check the response xml for the invalid value in your request message.",
						20040=>"Invalid data type, date expected. You have sent an invalid date format, please check the request xml.",
						20041=>"Invalid data type, numeric expected. You have sent an invalid data, please check the response xml.",
						20050=>"Message integrity validation failed. Please check to correct your MAC and resend the transaction.",
						20060=>"Entity Location not enabled for cash payout Please other locations enabled for payout and resend transaction.",
						20061=>"Entity Location is Disabled. Please contact Interswitch.",
						20062=>"Entity is Disabled. Please contact Interswitch.",
						30010=>"Transaction not found. The Transaction does not exist in Quick Teller. Please contact Interswitch.",
						30020=>"Duplicate Transfer Code. The transfer code exists in QuickTeller, please try another transfer code.",
						30030=>"Bank authorization needed. The transfer code exists in QuickTeller, please try another transfer code",
						40010=>"ATM cash out payments must be in multiples. Please change the amount to of 1000 multiples of 1000 and resend the transaction.",
						40020=>"ATM cash out payment has exceeded the maximum allowed value. Please try cash out the next day.",
						40030=>"Maximum transfer limit has been exceeded. Please ensure that amount is not more than the allowed maximum limit",
						50010=>"Invalid cancellation request - Transfer has been completed. You cannot cancel the transfer because it has been cashed out.",
						60001=>"User not found. The user does not exist in QuickTeller. Please contact Interswitch.",
						60002=>"Username and/or password incorrect. Please provide correct username/ password and try again.",
						60003=>"Security token invalid or expired. Please use a valid or active security token.",
						60010=>"Password does not meet complexity requirements. Please use a complex password and try again.",
						60012=>"Password does not meet history requirements. Please try a password that meets history requirements or contact Interswitch.",
						60011=>"Duplicate Username. The username exists in Quick Teller, please try another username.",
						60020=>"Invalid Activation Token. Please use a valid or active activation token.",
						60030=>"Passwords do not match Please provide match passwords and try again",
						60031=>"Password change failed. Please provide match passwords and try again.",
						70010=>"Biller not found. Please provide correct biller code in your request xml and try again.",
						70011=>"Unrecognized card. Your card has not been configured on Interswitch PayDirect. Please contact Interswitch.",
						70012=>"Unrecognized terminal owner. Your terminal has not been configured on Quick Teller. Please contact Interswitch.",
						70013=>"Unrecognized customer. The customer does not exist please try another. You can contact Interswitch if you think the customer is valid.",
						70014=>"Unrecognized payment channel. The payment channel does not exist or is invalid, check this document for a valid payment channel code. You can contact Interswitch if you think the code is valid.",
						70015=>"Collections account not setup. A collections account has not been setup. Please contact Interswitch.",
						70016=>"Collections account type not set. The collections account type must be set, please check and try again. You can contact Interswitch if you have set the account type and still got this error.",
						70017=>"Payment type code not recognized. You have supplied an invalid payment type code. Please check to correct and try again.",
						70018=>"Transaction Reference not found. Please provide correct Transaction Reference in your request xml and try again.",
						70019=>"Failed to send payment downstream. Please contact Interswitch.",
						70020=>"Collecting bank settings not configured.Please contact Interswitch.",
						70021=>"Lead Bank not found or setup. Please contact Interswitch.",
						70022=>"Advice previously received and processed. Try Query Transaction method to successfully see the details of your transaction",
						70023=>"Biller already associated with customer. Please contact Interswitch.",
						70024=>"Application settings not found or configured properly. Please contact Interswitch.",
						70025=>"Bank not setup or enabled for bill payment. Please contact Interswitch.",
						70026=>"Biller not enabled for channel. Please contact Interswitch.",
						70027=>"Bank not enabled for biller.Please contact Interswitch.",
						70028=>"Terminal owner not enabled for biller.Please contact Interswitch.",
						70029=>"Terminal owner not enabled for channel. Please contact Interswitch.",
						70030=>"Terminal owner not setup or enabled for bill payment. Please contact Interswitch.",
						70031=>"Unrecognized CBN Bank Code. Please contact Interswitch.",
						70032=>"Certificate identity error. Ensure the identity of your certificate is the same as the public key provided to Interswitch. If problem persist, please contact Interswitch.",
						70033=>"Certificate not recognised/setup in Please QuickTeller, contact Interswitch.",
						70034=>"Certificate terminal owner mismatch. Please contact Interswitch.",
						70035=>"Access to method call is denied. Please contact Interswitch.",
						70036=>"Terminal owner not associated with a funds transfer institution. Please contact Interswitch.",
						70037=>"Fees not setup. Please contact Interswitch.",
						70038=>"Data not found. The data you queried for does not exist. Please check to confirm and try again or contact Interswitch.",
						70039=>"Transaction set not allowed. Please contact Interswitch.",
						70040=>"Financial Transactional card has not been configured. Please contact Interswitch.",
						70041=>"Expired Transaction. Please contact Interswitch.",
						70042=>"Full Payment required. Please contact Interswitch.",
						90000=>"Successful. Your transaction was successful.",
						90001=>"Refer to Financial Institution. Please contact the Bank or Interswitch.",
						90002=>"Refer to Financial Institution, Special Condition. Please contact the Bank, it requires special attention.",
						90003=>"Invalid Merchant. Please check to be sure that the merchant provided is valid and try again.",
						90004=>"Pick-up card. Please contact Interswitch.",
						90005=>"Do not Honour. Please contact Interswitch.",
						90006=>"Error. There was a transaction failure because of a system problem. Please try again later.",
						90007=>"Pick-up Card, Special Condition. Please contact Interswitch.",
						90008=>"Honor with Identification.The Transaction can only be honored with identification.",
						90009=>"Request in Progress. Kindly call Query Transaction method to ascertain the status and details of your transaction.",
						90010=>"Approved by Financial Institution, Partial The transaction has been completed successfully.",
						90011=>"Approved by Financial Institution, VIP The transaction has been completed successfully.",
						90012=>"Invalid Transaction. If this is your first time, you may have to change your PIN.",
						90013=>"Invalid Amount. Please supply valid amount and try again.",
						90014=>"Invalid Card Number. Please supply valid card number and try again.",
						90015=>"No Such Financial Institution. Please supply an existing/valid financial institution and try again.",
						90016=>"Approved by Financial Institution, Update The transaction has been Track 3 completed successfully.",
						90017=>"Customer Cancellation. Please contact Interswitch.",
						90018=>"Customer Dispute. Please contact Interswitch.",
						90019=>"Re-enter Transaction. Please try again later.",
						90020=>"Invalid Response from Financial Institution. Please contact the financial institution.",
						90021=>"No Action Taken by Financial Institution. Please contact the financial institution or Interswitch.",
						90022=>"Suspected Malfunction. Try again later. If the error persists, please contact Interswitch.",
						90023=>"Unacceptable Transaction Fee. Please supply an acceptable transaction fee and try again.",
						90024=>"File Update not Supported. This feature is not supported. Please contact Interswitch.",
						90025=>"Unable to Locate Record. Record does not exist. You may contact Interswitch.",
						90026=>"Duplicate Record. Please try again with a new record.",
						90027=>"File Update Field Edit Error. Please rectify the error and try again. If the error persists, please contact Interswitch.",
						90028=>"File Update File Locked. Please try again later with a new record. Please contact Interswitch.",
						90029=>"File Update Failed. Please contact Interswitch.",
						90030=>"Format Error. Please change the format or contact Interswitch.",
						90031=>"Bank Not Supported. Please contact the bank or Interswitch.",
						90032=>"Completed Partially by Financial Institution. Please contact the bank or Interswitch.",
						90033=>"Expired Card, Pick-Up. Please contact your bank.",
						90034=>"Suspected Fraud, Pick-Up. Please contact your bank.",
						90035=>"Contact Acquirer, Pick-Up. Please contact your bank.",
						90036=>"Restricted Card, Pick-Up. Please contact your bank.",
						90037=>"Call Acquirer Security, Pick-Up. Please contact your bank.",
						90038=>"PIN Tries Exceeded, Pick-Up. Please contact your bank.",
						90039=>"No Credit Account. Please contact your bank.",
						90040=>"Function not Supported. Please contact your bank or Interswitch.",
						90041=>"Lost Card, Pick-Up. Please contact your bank.",
						90042=>"No Universal Account. Please try again with a valid account or contact your bank.",
						90043=>"Stolen Card, Pick-Up. Please contact your bank",
						90044=>"No Investment Account. Please try again with a valid account or contact your bank.",
						90051=>"Insufficient Funds. Please contact your bank.",
						90052=>"No Check Account. Please try again with a valid account or contact your bank.",
						90053=>"No Savings Account. Please try again with a valid account or contact your bank.",
						90054=>"Expired Card. Please contact your bank.",
						90055=>"Incorrect PIN. Please try again with correct PIN.",
						90056=>"No Card Record. Please try again with a valid card or contact your bank.",
						90057=>"Transaction not Permitted to Cardholder. Please contact your bank.",
						90058=>"Transaction not Permitted on Terminal. Please contact Interswitch.",
						90059=>"Suspected Fraud. Please contact your bank.",
						90060=>"Contact Acquirer. Your transaction has been declined by your bank. Please contact your bank.",
						90061=>"Exceeds Withdrawal Limit.Please contact your bank.",
						90062=>"Restricted Card. Please contact your bank.",
						90063=>"Security Violation. Please contact your bank.",
						90064=>"Original Amount Incorrect. Please enter correct original amount and try again.",
						90065=>"Exceeds withdrawal frequency. Please try again later or contact your bank.",
						90066=>"Call Acquirer Security. Please contact the Acquirer or Interswitch.",
						90067=>"Hard Capture. Please contact Interswitch.",
						90068=>"Response Received Too Late. Please try again later or contact your bank.",
						90075=>"PIN tries exceeded. Please contact your bank.",
						90076=>"Reserved for Future Postilion Use. Please contact Interswitch.",
						90077=>"Intervene, Bank Approval Required. Please contact the bank.",
						90078=>"Intervene, Bank Approval Required for Partial Amount. Please contact the bank.",
						90090=>"Cut-off in Progress. The financial Institution is not available, please try again later.",
						90091=>"Issuer or Switch Inoperative. Please contact the bank, Telco or Interswitch.",
						90092=>"Routing Error. Please contact Interswitch.",
						90093=>"Violation of law. Please contact Interswitch.",
						90094=>"Duplicate Transaction. You have sent this transaction before, please send a new transaction.",
						90095=>"Reconcile Error. Please contact Interswitch.",
						90096=>"System Malfunction. Please try again, if the problem persist contact Interswitch.",
						90098=>"Exceeds Cash Limit. Please contact your bank.",
						'900A0'=>"Unknown Error.Please contact Interswitch.",
						'900A5'=>"Contract phone number recharge is not. Please try again with a non-allowed contract phone number or contact the Telco.",
						'900A6'=>"The phone number you have supplied is inactive. Please try again with an active phone number or contact the Telco.",
						'900A7'=>"The phone number you have supplied has been barred. Please try again with an unbarred phone number or contact the Telco.",
						'900A8'=>"There is no voucher of the requested voucher denomination. Please try again with a valid denomination.",
						'900A9'=>"The phone number or smart card number you have supplied is invalid. Please try again with a valid phone number or smart card number."
					);
			
			if (array_key_exists($rpcode, $resdeca)) 
			{
				return $resdeca["$rpcode"];
			}
			else
			{   
				return "Unknown Error";
			}
		}
		}
		/**
		* Add the Gateway to WooCommerce
		**/
		function woocommerce_add_cowryz_gateway($methods) {
			$methods[] = 'WC_cowryz';
			return $methods;
		}
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_cowryz_gateway' );
	}