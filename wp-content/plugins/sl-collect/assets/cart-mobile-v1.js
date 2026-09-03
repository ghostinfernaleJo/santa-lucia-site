( function() {
    'use strict';

    function initCouponToggle() {
        var coupon = document.querySelector( '.woocommerce-cart-form .coupon' );
        if ( ! coupon || coupon.dataset.slcCouponReady === '1' ) return;

        coupon.dataset.slcCouponReady = '1';
        var toggle = document.createElement( 'button' );
        toggle.type = 'button';
        toggle.className = 'slc-cart-coupon-toggle';
        toggle.setAttribute( 'aria-expanded', 'false' );
        toggle.textContent = 'Vous avez un code promo ?';
        coupon.parentNode.insertBefore( toggle, coupon );
        document.body.classList.add( 'slc-cart-coupon-ready' );

        toggle.addEventListener( 'click', function() {
            var open = coupon.classList.toggle( 'slc-coupon-open' );
            toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
            toggle.textContent = open ? 'Masquer le code promo' : 'Vous avez un code promo ?';
            if ( open ) {
                var input = coupon.querySelector( 'input' );
                if ( input ) input.focus();
            }
        } );
    }

    if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', initCouponToggle );
    else initCouponToggle();
}() );
