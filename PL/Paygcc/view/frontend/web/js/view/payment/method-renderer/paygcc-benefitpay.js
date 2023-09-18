define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/url'
    ],
    function ($, Component, setPaymentInformationAction, additionalValidators, fullScreenLoader, url){
        'use strict';
        // var paymentMethod = ko.observable(null);

        return Component.extend({
            redirectAfterPlaceOrder: false,

            defaults: {
                template: 'PL_Paygcc/payment/paygcc-benefitpay'
            },

            initialize: function() {
                this._super();
                self = this;
            },

            getCode: function() {
                return 'paygcc_benefitpay';
            },

            getData: function() {
                return {
                    'method': this.item.method
                };
            },

            placeOrder: function () {
                if (this.validate() && additionalValidators.validate()) {
                    fullScreenLoader.startLoader();
                    this.isPlaceOrderActionAllowed(false);
                    $.when(
                        setPaymentInformationAction(this.messageContainer, this.getData())
                    )
                        .done(
                            function() {
                                $.mage.redirect(url.build('paygcc/benefitpay/redirect?_epoch='+Date.now()))
                            }
                        )
                        .fail(
                            function () {
                                this.isPlaceOrderActionAllowed(true);
                            }
                        );
                }
            }

        });
    }
);
