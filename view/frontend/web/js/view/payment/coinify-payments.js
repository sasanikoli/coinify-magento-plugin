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
