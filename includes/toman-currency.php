<?php

/**
 * Add Toman currency for EDD
 *
 * @param 				array $currencies Currencies list
 * @return 				array
 */
if ( ! function_exists('irg_add_toman_currency')):
function add_tomain_currency_alsatpardakht( $currencies ) {
	$currencies['IRT'] = 'تومان';
	return $currencies;
}
endif;
add_filter( 'edd_currencies', 'add_tomain_currency_alsatpardakht' );

/**
 * Format decimals
 */
add_filter( 'edd_sanitize_amount_decimals', function( $decimals ) {
	
	$currency = function_exists('edd_get_currency') ? edd_get_currency() : '';
	
	global $edd_options;
	
	if ( $edd_options['currency'] == 'IRT' || $currency == 'IRT' || $edd_options['currency'] == 'RIAL' || $currency == 'RIAL' ) {
		return $decimals = 0;
	}

	return $decimals;
} );

add_filter( 'edd_format_amount_decimals', function( $decimals ) {
	
	$currency = function_exists('edd_get_currency') ? edd_get_currency() : '';
	
	global $edd_options;
	
	if ( $edd_options['currency'] == 'IRT' || $currency == 'IRT' || $edd_options['currency'] == 'RIAL' || $currency == 'RIAL' ) {
		return $decimals = 0;
	}
	
	return $decimals;
} );

if ( function_exists('per_number') ) {
	add_filter( 'edd_irt_currency_filter_after', 'per_number', 10, 2 );
}

add_filter( 'edd_irt_currency_filter_after', 'toman_postfix_alsatpardakht', 10, 2 );
function toman_postfix_alsatpardakht( $price, $did ) {
	return str_replace( 'IRT', 'تومان', $price );
}

add_filter( 'edd_rial_currency_filter_after', 'rial_postfix_alsatpardakht', 10, 2 );
function rial_postfix_alsatpardakht( $price, $did ) {
	return str_replace( 'RIAL', 'ریال', $price );
}
