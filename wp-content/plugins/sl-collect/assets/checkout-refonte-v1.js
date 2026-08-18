/* global jQuery */
( function() {
    'use strict';

    function refreshPaymentCards() {
        document.querySelectorAll( '.woocommerce-checkout-payment .wc_payment_method' ).forEach( function( card ) {
            var radio = card.querySelector( 'input[type="radio"]' );
            card.classList.toggle( 'slc-payment-card--selected', !! ( radio && radio.checked ) );
        } );
    }

    document.addEventListener( 'change', function( event ) {
        if ( event.target.matches( '.woocommerce-checkout-payment input[type="radio"]' ) ) {
            refreshPaymentCards();
        }
    } );

    document.addEventListener( 'DOMContentLoaded', refreshPaymentCards );

    if ( window.jQuery ) {
        jQuery( document.body ).on( 'updated_checkout', refreshPaymentCards );
    }
}() );
