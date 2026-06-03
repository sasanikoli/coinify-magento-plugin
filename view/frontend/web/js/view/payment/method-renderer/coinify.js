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
