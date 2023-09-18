define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/url',
        'ko',
        'uiComponent'
    ],
    function ($, Component, setPaymentInformationAction, additionalValidators, fullScreenLoader, url, ko){
        'use strict';
        // var paymentMethod = ko.observable(null);

        return Component.extend({
            redirectAfterPlaceOrder: false,

            defaults: {
                template: 'PL_Paygcc/payment/paygcc-apicheckout'
            },

            initialize: function() {
                this._super();
                self = this;
                this.savedCards = ko.observableArray([]);
                var responseData = window.checkoutConfig.save_cards;
                if (responseData === null || responseData === undefined) {
                    this.isLoggedIn = ko.observable(false);
                }
                else if(Array.isArray(responseData.data) && responseData.data.length === 0)
                {
                    this.isLoggedIn = ko.observable(false);
                }
                else{
                    this.isLoggedIn = ko.observable(true);
                    this.savedCards(responseData.data);
                }
            },

            getCode: function() {
                return 'paygcc_apicheckout';
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
                                var dropdown = document.querySelector('input[name="save-cards"]:checked');
                                var selectedValue = dropdown.value;
                                
                                if(selectedValue !== "0")
                                {
                                    $.mage.redirect(url.build('paygcc/apicheckout/redirect?usecard=1&cardvalue='+selectedValue+'&_epoch='+Date.now()))
                                }
                                else{
                                    $.mage.redirect(url.build('paygcc/apicheckout/redirect?usecard=0&_epoch='+Date.now()))
                                }
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
