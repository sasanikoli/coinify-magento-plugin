/**
 * Coinify payment method renderer for the Magento 2 checkout.
 *
 * Extends the default checkout payment component with redirect-based behaviour:
 * instead of completing the payment on-page, the customer is forwarded to the
 * Coinify payment window after the Magento order is placed.
 *
 * redirectAfterPlaceOrder is set to false so Magento does not attempt its own
 * redirect — afterPlaceOrder() handles the navigation manually using the
 * redirectUrl injected into checkoutConfig by ConfigProvider.php.
 */
define([
    'Magento_Checkout/js/view/payment/default'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Coinify_Payment/payment/redirect'
        },

        redirectAfterPlaceOrder: false,

        getCode: function () {
            return 'coinify';
        },

        afterPlaceOrder: function () {
            var redirectUrl = window.checkoutConfig.payment.coinify.redirectUrl;
            window.location.replace(redirectUrl || '/checkout/cart');
        }
    });
});
