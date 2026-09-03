/* global jQuery */
( function() {
    'use strict';

    function refreshPaymentCards() {
        document.querySelectorAll( '.woocommerce-checkout-payment .wc_payment_method' ).forEach( function( card ) {
            var radio = card.querySelector( 'input[type="radio"]' );
            card.classList.toggle( 'slc-payment-card--selected', !! ( radio && radio.checked ) );
        } );
    }

    /* Les informations qui ne sont utiles qu'à certains clients restent
       disponibles, sans allonger inutilement le formulaire principal. */
    function addOptionalSection( id, summary, fieldIds, extraClass ) {
        if ( document.getElementById( id ) ) return;

        var rows = fieldIds.map( function( fieldId ) {
            return document.getElementById( fieldId );
        } ).filter( Boolean );
        if ( ! rows.length ) return;

        var details = document.createElement( 'details' );
        details.id = id;
        details.className = 'slc-optional-section ' + extraClass;
        var title = document.createElement( 'summary' );
        title.textContent = summary;
        details.appendChild( title );

        var content = document.createElement( 'div' );
        content.className = 'slc-optional-section__content';
        details.appendChild( content );
        rows[0].parentNode.insertBefore( details, rows[0] );

        rows.forEach( function( row ) {
            content.appendChild( row );
            var field = row.querySelector( 'input, select, textarea' );
            if ( field && ( field.value || field.checked ) ) details.open = true;
            row.addEventListener( 'focusin', function() { details.open = true; } );
        } );
    }

    function compactCustomerForm() {
        addOptionalSection(
            'slc-collector-details',
            'Une autre personne récupère la commande (facultatif)',
            [ 'sl_collect_collector_name_field', 'sl_collect_collector_phone_field' ],
            'slc-optional-section--collector'
        );
        addOptionalSection(
            'slc-customer-extras',
            'Ajouter ma date d’anniversaire et mes préférences (facultatif)',
            [ 'slc_birthday_field', 'slc_optin_field' ],
            'slc-optional-section--extras'
        );
    }

    document.addEventListener( 'change', function( event ) {
        if ( event.target.matches( '.woocommerce-checkout-payment input[type="radio"]' ) ) {
            refreshPaymentCards();
        }
    } );

    document.addEventListener( 'DOMContentLoaded', function() {
        refreshPaymentCards();
        compactCustomerForm();
    } );

    if ( window.jQuery ) {
        jQuery( document.body ).on( 'updated_checkout', refreshPaymentCards );
    }
}() );
