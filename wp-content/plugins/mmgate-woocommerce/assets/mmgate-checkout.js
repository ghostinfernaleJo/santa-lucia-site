/* global jQuery */
( function() {
    'use strict';

    var modal;
    var phoneInput;
    var errorMessage;
    var activeForm;
    var allowSubmit = false;

    function paymentPhoneField( form ) {
        return form ? form.querySelector( '#mmgate_msisdn' ) : null;
    }

    function isMMGateSelected( form ) {
        var method = form && form.querySelector( 'input[name="payment_method"]:checked' );
        return !! ( method && method.value === 'mmgate' );
    }

    function normalizePhone( value ) {
        var digits = String( value || '' ).replace( /\D/g, '' );
        if ( digits.indexOf( '00237' ) === 0 ) {
            digits = digits.slice( 5 );
        } else if ( digits.indexOf( '237' ) === 0 && digits.length > 9 ) {
            digits = digits.slice( 3 );
        }
        if ( digits.length === 10 && digits.charAt( 0 ) === '0' ) {
            digits = digits.slice( 1 );
        }
        return /^6\d{8}$/.test( digits ) ? digits : '';
    }

    function createModal() {
        if ( modal ) {
            return;
        }

        modal = document.createElement( 'div' );
        modal.className = 'mmgate-confirmation-modal';
        modal.setAttribute( 'hidden', 'hidden' );
        modal.innerHTML = ''
            + '<div class="mmgate-confirmation-modal__backdrop"></div>'
            + '<section class="mmgate-confirmation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="mmgate-confirmation-title">'
            + '<button type="button" class="mmgate-confirmation-modal__close" aria-label="Fermer">×</button>'
            + '<p class="mmgate-confirmation-modal__eyebrow">Paiement Mobile Money</p>'
            + '<h2 id="mmgate-confirmation-title">Quel numéro devons-nous débiter&nbsp;?</h2>'
            + '<p>Indiquez le numéro MTN MoMo ou Orange Money sur lequel vous validerez la demande de paiement.</p>'
            + '<label for="mmgate_confirmation_phone">Numéro Mobile Money à débiter <span aria-hidden="true">*</span></label>'
            + '<input id="mmgate_confirmation_phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="6XX XX XX XX">'
            + '<p class="mmgate-confirmation-modal__hint">Le numéro peut être différent de votre téléphone de contact.</p>'
            + '<p class="mmgate-confirmation-modal__error" role="alert" aria-live="assertive"></p>'
            + '<div class="mmgate-confirmation-modal__actions"><button type="button" class="mmgate-confirmation-modal__cancel">Retour</button><button type="button" class="mmgate-confirmation-modal__confirm">Continuer vers le paiement</button></div>'
            + '</section>';
        document.body.appendChild( modal );

        phoneInput = modal.querySelector( '#mmgate_confirmation_phone' );
        errorMessage = modal.querySelector( '.mmgate-confirmation-modal__error' );
        modal.querySelector( '.mmgate-confirmation-modal__close' ).addEventListener( 'click', closeModal );
        modal.querySelector( '.mmgate-confirmation-modal__cancel' ).addEventListener( 'click', closeModal );
        modal.querySelector( '.mmgate-confirmation-modal__backdrop' ).addEventListener( 'click', closeModal );
        modal.querySelector( '.mmgate-confirmation-modal__confirm' ).addEventListener( 'click', confirmPhone );
        phoneInput.addEventListener( 'input', function() {
            errorMessage.textContent = '';
        } );
        phoneInput.addEventListener( 'keydown', function( event ) {
            if ( event.key === 'Enter' ) {
                event.preventDefault();
                confirmPhone();
            }
        } );
    }

    function openModal( form ) {
        createModal();
        activeForm = form;
        var field = paymentPhoneField( form );
        phoneInput.value = field ? field.value : '';
        errorMessage.textContent = '';
        modal.removeAttribute( 'hidden' );
        document.documentElement.classList.add( 'mmgate-modal-open' );
        window.setTimeout( function() { phoneInput.focus(); }, 0 );
    }

    function closeModal() {
        if ( ! modal ) {
            return;
        }
        modal.setAttribute( 'hidden', 'hidden' );
        document.documentElement.classList.remove( 'mmgate-modal-open' );
        var button = activeForm && activeForm.querySelector( '#place_order' );
        if ( button ) {
            button.focus();
        }
        activeForm = null;
    }

    function confirmPhone() {
        var normalized = normalizePhone( phoneInput.value );
        if ( ! normalized ) {
            errorMessage.textContent = 'Saisissez un numéro Mobile Money camerounais valide (9 chiffres commençant par 6).';
            phoneInput.focus();
            return;
        }

        var form = activeForm;
        var field = paymentPhoneField( form );
        if ( ! form || ! field ) {
            closeModal();
            return;
        }

        field.value = normalized;
        field.dataset.mmgateConfirmed = '1';
        closeModal();
        allowSubmit = true;
        var button = form.querySelector( '#place_order' );
        if ( form.requestSubmit ) {
            form.requestSubmit( button || undefined );
        } else if ( button ) {
            button.click();
        }
    }

    function interceptSubmit( event ) {
        var form = event.target;
        if ( ! form.matches || ! form.matches( 'form.checkout, form#order_review' ) || ! isMMGateSelected( form ) ) {
            return;
        }
        if ( allowSubmit ) {
            allowSubmit = false;
            return;
        }
        var field = paymentPhoneField( form );
        if ( field && field.dataset.mmgateConfirmed === '1' && normalizePhone( field.value ) ) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        openModal( form );
    }

    document.addEventListener( 'DOMContentLoaded', function() {
        document.documentElement.classList.add( 'mmgate-checkout--js' );
        document.addEventListener( 'submit', interceptSubmit, true );
        document.addEventListener( 'change', function( event ) {
            if ( event.target.matches( 'input[name="payment_method"]' ) ) {
                var form = event.target.closest( 'form' );
                var field = paymentPhoneField( form );
                if ( field ) {
                    delete field.dataset.mmgateConfirmed;
                }
            }
        } );
        document.addEventListener( 'keydown', function( event ) {
            if ( event.key === 'Escape' && modal && ! modal.hasAttribute( 'hidden' ) ) {
                closeModal();
            }
        } );
    } );
}() );
