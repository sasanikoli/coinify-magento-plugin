/**
 * Registers the Coinify payment method renderer with Magento's checkout
 * payment renderer list. This file is the entry point loaded by the
 * checkout layout XML; it pushes the renderer component path so the
 * checkout UI knows which JS component to instantiate for the 'coinify'
 * payment method code.
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'coinify',
        component: 'Coinify_Payment/js/view/payment/method-renderer/coinify'
    });

    return Component.extend({});
});
