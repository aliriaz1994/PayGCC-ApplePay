define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(

            {
                type: 'paygcc_apicheckout',
                component: 'PL_Paygcc/js/view/payment/method-renderer/paygcc-apicheckout'
            },
            {
                type: 'paygcc_benefit',
                component: 'PL_Paygcc/js/view/payment/method-renderer/paygcc-benefit'
            },
            {
                type: 'paygcc_benefitpay',
                component: 'PL_Paygcc/js/view/payment/method-renderer/paygcc-benefitpay'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
