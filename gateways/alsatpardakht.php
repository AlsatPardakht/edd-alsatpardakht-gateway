<?php
//AlsatPardakht Payment Gateway For for Easy Digital Downloads

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ALSATPARDAKHT_EDD_GATEWAY' ) ) :


	class ALSATPARDAKHT_EDD_GATEWAY {
		public $keyName;

		/**
		 * Initialize gateway and hook
		 *
		 * @return                void
		 */
		public function __construct() {
			$this->keyName = 'alsatpardakht';

			add_filter( 'edd_payment_gateways', array( $this, 'add' ) );
			add_action( $this->format( 'edd_{key}_cc_form' ), array( $this, 'cc_form' ) );
			add_action( $this->format( 'edd_gateway_{key}' ), array( $this, 'process' ) );
			add_action( $this->format( 'edd_verify' ), array( $this, 'verify' ) );
			add_filter( 'edd_settings_gateways', array( $this, 'settings' ) );

			add_action( 'edd_payment_receipt_after', array( $this, 'receipt' ) );

			add_action( 'init', array( $this, 'listen' ) );
		}

		/**
		 * Add gateway to list
		 *
		 * @param  array  $gateways  Gateways array
		 *
		 * @return                array
		 */
		public function add( $gateways ) {
			global $edd_options;

			$gateways[ $this->keyName ] = array(
				'checkout_label' => isset( $edd_options['alsatpardakht_label'] ) ? $edd_options['alsatpardakht_label'] : 'درگاه آل‌سات پرداخت',
				'admin_label'    => 'آل‌سات پرداخت'
			);

			return $gateways;
		}

		/**
		 * CC Form
		 * We don't need it anyway.
		 *
		 * @return                void
		 */
		public function cc_form() {
		}

		/**
		 * @param $action  (PaymentRequest, )
		 * @param  array  $params  string
		 *
		 * @return mixed
		 */
		public function sendRequestToAlsatPardakht( $action, array $params ) {
			try {
				$args   = array(
					'body'        => $params,
					'timeout'     => '30',
					'redirection' => '5',
				);
				$result = wp_safe_remote_post( $action, $args );

				if ( ! isset( $result->errors ) ) {
					if ( isset( $result['body'] ) && $result['body'] ) {
						$result = json_decode( $result['body'] );
					} else {
						$result = json_decode( '[]' );
					}

				}

				return $result;
			} catch ( Exception $ex ) {
				return false;
			}
		}


		/**
		 * Process the payment
		 *
		 * @param  array  $purchase_data
		 *
		 * @return                false
		 */
		public function process( $purchase_data ) {
			global $edd_options;
			@ session_start();
			$payment = $this->insert_payment( $purchase_data );

			if ( $payment ) {

				$alsatVaset = ( isset( $edd_options[ $this->keyName . '_alsatDirectIPG' ] ) ? $edd_options[ $this->keyName . '_alsatDirectIPG' ] : false );

				$merchant = ( isset( $edd_options[ $this->keyName . '_merchant' ] ) ? $edd_options[ $this->keyName . '_merchant' ] : '' );
				$callback = get_permalink( $edd_options['success_page'] );

				$amount = $this->getAmount( intval( $purchase_data['price'] ) );

				if ( $alsatVaset === '1' ) {
					$Tashim[] = [];
					$data     = array(
						'ApiKey'              => $merchant,
						'Amount'              => $amount,
						'Tashim'              => json_encode( $Tashim ),
						'RedirectAddressPage' => $callback
					);
					$result   = $this->SendRequestToAlsatPardakht( "https://www.alsatpardakht.com/IPGAPI/Api22/send.php",
						$data );
				} else {
					$data = array(
						'Api'             => $merchant,
						'Amount'          => $amount,
						'InvoiceNumber'   => $payment,
						'RedirectAddress' => $callback
					);

					$result = $this->sendRequestToAlsatPardakht( 'https://www.alsatpardakht.com/API_V1/sign.php',
						$data );
				}


				if ($result->IsSuccess && $result->Token ) {
					edd_insert_payment_note( $payment, 'شروع پرداخت در تاریخ : ' . $result->TimeStamp );
					@ session_start();
					$_SESSION['alsatpardakht_payment'] = $payment;

					wp_redirect( "https://www.alsatpardakht.com/API_V1/Go.php?Token=$result->Token" );
				} else {
					$errorMessage = '';
					if ( $result->get_error_code() ) {
						$errorMessage .= $result->get_error_code() . " - ";
						$errorMessage .= isset( $result->errors[ $result->get_error_code() ] ) ? $result->errors[ $result->get_error_code() ][0] : ' خطای curl ';
					} else {
						$errorMessage .= ' خطای curl# ';
					}
					edd_insert_payment_note( $payment, "در اتصال به درگاه مشکلی پیش آمد. علت : $errorMessage" );
					edd_update_payment_status( $payment, 'failed' );
					edd_set_error( 'alsatpardakht_connect_error',
						"در اتصال به درگاه مشکلی پیش آمد. علت : {$result->errors[ $result->get_error_code() ][0]} {$result->get_error_code()}" );
					edd_send_back_to_checkout();

					return false;
				}
			} else {
				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
			}
			return false;
		}

		/**
		 * Verify the payment
		 *
		 * @return                false
		 */
		public function verify() {
			global $edd_options;
			$alsatVaset = ( isset( $edd_options[ $this->keyName . '_alsatDirectIPG' ] ) ? $edd_options[ $this->keyName . '_alsatDirectIPG' ] : false );

			@ session_start();
			$payment = edd_get_payment( $_SESSION['alsatpardakht_payment'] );
			unset( $_SESSION['alsatpardakht_payment'] );
			if ( ! $payment ) {
				wp_die( 'رکورد پرداخت موردنظر وجود ندارد!' );
			}
			if ( $payment->status == 'complete' ) {
				return false;
			}

			if ( isset( $_GET['tref'] ) && isset( $_GET['iN'] ) && $_GET['iD'] ) {

				$tref = sanitize_text_field( $_GET['tref'] );
				$iN   = sanitize_text_field( $_GET['iN'] );
				$iD   = sanitize_text_field( $_GET['iD'] );

				$amount = $this->getAmount( intval( edd_get_payment_amount( $payment->ID ) ) );
				if ( $alsatVaset === '1' ) {
					$data   = array(
						'tref' => $tref,
						'iN'   => $iN,
						'iD'   => $iD,
					);
					$result = $this->SendRequestToAlsatPardakht( "https://www.alsatpardakht.com/IPGAPI/Api22/VerifyTransaction.php",
						$data );
				} else {
					$apiKey = ( isset( $edd_options[ $this->keyName . '_merchant' ] ) ? $edd_options[ $this->keyName . '_merchant' ] : '' );
					$data   = array(
						'Api'  => $apiKey,
						'tref' => $tref,
						'iN'   => $iN,
						'iD'   => $iD,
					);
					$result = $this->SendRequestToAlsatPardakht( "https://www.alsatpardakht.com/API_V1/callback.php",
						$data );
				}

				if ( isset( $result->VERIFY->IsSuccess ) && isset( $result->PSP ) && $result->PSP->IsSuccess === true ) {

					if ( $result->PSP->Amount === $amount ) {

						if ( version_compare( EDD_VERSION, '2.1', '>=' ) ) {
							edd_set_payment_transaction_id( $payment->ID, $result->PSP->TransactionReferenceID );
						}
						if ( isset( $result->VERIFY ) && $result->VERIFY->IsSuccess ) {
							edd_insert_payment_note( $payment->ID,
								'شماره تراکنش بانکی: ' . $result->PSP->TransactionReferenceID .
								' و شماره کارت واریز کننده' . $result->PSP->TrxMaskedCardNumber
							);
							edd_update_payment_meta( $payment->ID, 'alsatpardakht_tref',
								$result->PSP->TransactionReferenceID );
							edd_update_payment_meta( $payment->ID, 'alsatpardakht_hashedCard',
								$result->PSP->TrxMaskedCardNumber );
							edd_update_payment_status( $payment->ID );
							edd_send_to_success_page();
						}
					} else {
						edd_update_payment_status( $payment->ID, 'failed' );
						wp_redirect( get_permalink( $edd_options['failure_page'] ) );
					}
				} else {
					edd_update_payment_status( $payment->ID, 'failed' );
					wp_redirect( get_permalink( $edd_options['failure_page'] ) );
				}
				edd_empty_cart();

			} else {
				edd_update_payment_status( $payment->ID, 'failed' );
				wp_redirect( get_permalink( $edd_options['failure_page'] ) );
			}
			edd_empty_cart();
			return false;
		}

		/**
		 * Receipt field for payment
		 *
		 * @param  object  $payment
		 *
		 * @return                void
		 */
		public function receipt( $payment ) {
			$tref = edd_get_payment_meta( $payment->ID, 'alsatpardakht_tref' );
			$hashedCard = edd_get_payment_meta( $payment->ID, 'alsatpardakht_hashedCard' );
			if ( $tref ) {
				echo '<tr class="alsatpardakht-ref-id-row ezp-field "><td><strong>شماره تراکنش بانکی:</strong></td><td>' . $tref . '</td></tr>';
			}
			if ( $hashedCard ) {
				echo '<tr class="alsatpardakht-ref-id-row ezp-field "><td><strong>شماره تراکنش بانکی:</strong></td><td>' . $hashedCard . '</td></tr>';
			}
		}

		/**
		 * Gateway settings
		 *
		 * @param  array  $settings
		 *
		 * @return                array
		 */
		public function settings( $settings ) {
			return array_merge( $settings, array(
				$this->keyName . '_header'         => array(
					'id'   => $this->keyName . '_header',
					'type' => 'header',
					'name' => '<strong>درگاه آل‌سات پرداخت</strong>'
				),
				$this->keyName . '_merchant'       => array(
					'id'   => $this->keyName . '_merchant',
					'name' => 'کد دریافتی از پنل (API)',
					'type' => 'text',
					'size' => 'regular'
				),
				$this->keyName . '_alsatDirectIPG' => array(
					'id'   => $this->keyName . '_alsatDirectIPG',
					'name' => 'درگاه مستقیم',
					'type' => 'checkbox',
					'desc' => 'استفاده از IPG واسط'
				),

//	            $this->keyName . '_label' =>	array(
//                    'id' 			=> $this->keyName . '_label',
//                    'name' 			=>	'نام درگاه در صفحه پرداخت',
//                    'type' 			=>	'text',
//                    'size' 			=>	'regular',
//                    'std' 			=>	'درگاه آل‌سات پرداخت'
//                )
			) );
		}

		/**
		 * Format a string, replaces {key} with $keyname
		 *
		 * @param  string  $string  To format
		 *
		 * @return            string Formatted
		 */
		private function format( $string ) {
			return str_replace( '{key}', $this->keyName, $string );
		}

		/**
		 * Inserts a payment into database
		 *
		 * @param  array  $purchase_data
		 *
		 * @return            int $payment_id
		 */
		private function insert_payment( $purchase_data ) {
			global $edd_options;

			$payment_data = array(
				'price'        => $purchase_data['price'],
				'date'         => $purchase_data['date'],
				'user_email'   => $purchase_data['user_email'],
				'purchase_key' => $purchase_data['purchase_key'],
				'currency'     => $edd_options['currency'],
				'downloads'    => $purchase_data['downloads'],
				'user_info'    => $purchase_data['user_info'],
				'cart_details' => $purchase_data['cart_details'],
				'status'       => 'pending'
			);

			// record the pending payment
			return edd_insert_payment( $payment_data );
		}

		/**
		 * Listen to incoming queries
		 *
		 * @return            void
		 */
		public function listen() {
			if ( isset( $_GET['tref'] ) || isset($_GET['iN']) || isset($_GET['IsSuccess']) ) {
				do_action( 'edd_verify' );
			}
		}

		private function getAmount( $amount ) {
			$strToLowerCurrency = strtolower( edd_get_currency() );
			if (
				$strToLowerCurrency === strtolower( 'IRT' ) ||
				$strToLowerCurrency === strtolower( 'TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'Iran TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'Iranian TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'Iran-TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'Iranian-TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'Iran_TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'Iranian_TOMAN' ) ||
				$strToLowerCurrency === strtolower( 'تومان' ) ||
				$strToLowerCurrency === strtolower( 'تومان ایران' )
			) {
				$amount *= 10;
			} elseif ( strtolower( $strToLowerCurrency ) === strtolower( 'IRHT' ) ) {
				$amount *= 10000;
			} elseif ( strtolower( $strToLowerCurrency ) === strtolower( 'IRHR' ) ) {
				$amount *= 1000;
			}

			return $amount;
		}


	}

endif;

new ALSATPARDAKHT_EDD_GATEWAY;
