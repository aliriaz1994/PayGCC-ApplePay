<?php
namespace PL\Paygcc\Model\Checkout;

use Magento\Customer\Api\Data\CustomerInterface as CustomerDataObject;
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use PL\Paygcc\Logger\Logger as PLLogger;
use PL\Paygcc\Model\Benefit as BenefitModel;

class Benefit
{
    protected $_methodType = 'paygcc_benefit';

    protected $_redirectUrl = '';

    protected $_quote;

    protected $_config;

    protected $_customerId;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    protected $_configCacheType;

    protected $_checkoutData;

    protected $_coreUrl;

    protected $_checkoutOnepageFactory;

    protected $_checkoutSession;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $_customerRepository;

    /**
     * @var \Magento\Customer\Model\AccountManagement
     */
    protected $_accountManagement;

    protected $_encryptor;

    protected $_messageManager;

    /**
     * @var OrderSender
     */
    protected $_orderSender;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var \Magento\Quote\Model\Quote\TotalsCollector
     */
    protected $totalsCollector;

    protected $_benefitModel;

    protected $_countryFactory;

    protected $_plLogger;

    protected $_jsonHelper;

    protected $_paygccHelper;


    const PAYMENT_ADDITIONAL_INFO = 'payment_additional_info';


    public function __construct(
        \Magento\Customer\Model\Url $customerUrl,
        \Magento\Checkout\Helper\Data $checkoutData,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Cache\Type\Config $configCacheType,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $coreUrl,
        \Magento\Checkout\Model\Type\OnepageFactory $onepageFactory,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        BenefitModel $benefitModel,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \PL\Paygcc\Logger\Logger $plLogger,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \PL\Paygcc\Helper\Data $paygccHelper,
        \Magento\Framework\DataObject\Copy $objectCopyService,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        AccountManagement $accountManagement,
        OrderSender $orderSender,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        $params = []
    )
    {
        $this->quoteManagement = $quoteManagement;
        $this->_customerUrl = $customerUrl;
        $this->_checkoutData = $checkoutData;
        $this->_configCacheType = $configCacheType;
        $this->_storeManager = $storeManager;
        $this->_coreUrl = $coreUrl;
        $this->_checkoutOnepageFactory = $onepageFactory;
        $this->_objectCopyService = $objectCopyService;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerRepository = $customerRepository;
        $this->_encryptor = $encryptor;
        $this->_messageManager = $messageManager;
        $this->_orderSender = $orderSender;
        $this->_accountManagement = $accountManagement;
        $this->quoteRepository = $quoteRepository;
        $this->totalsCollector = $totalsCollector;
        $this->_benefitModel = $benefitModel;
        $this->_countryFactory = $countryFactory;
        $this->_plLogger = $plLogger;
        $this->_jsonHelper = $jsonHelper;
        $this->_paygccHelper = $paygccHelper;

        $this->_customerSession = isset($params['session'])
        && $params['session'] instanceof \Magento\Customer\Model\Session ? $params['session'] : $customerSession;

        if (isset($params['quote']) && $params['quote'] instanceof \Magento\Quote\Model\Quote) {
            $this->_quote = $params['quote'];
        } else {
            // phpcs:ignore Magento2.Exceptions.DirectThrow
            throw new \Exception('Quote instance is required.');
        }
    }

    public function getPaymentRequest()
    {
        $this->_quote->collectTotals();

        if (!$this->_quote->getGrandTotal()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Payment Processor cannot process orders with a zero balance due.'
                )
            );
        }
        $this->_quote->reserveOrderId();
        $this->quoteRepository->save($this->_quote);
        $paymentUrl = $this->_benefitModel->getPayGCCPaymentUrl($this->_quote);
        return $paymentUrl;
    }


    /**
     * @param array $result
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function place($result = [])
    {
        //Save payment return
        $quote = $this->_quote;
        $quote->collectTotals();
        $payment = $quote->getPayment();
        $payment->setMethod($this->_methodType);
        $additionalData = $this->_jsonHelper->jsonEncode($result);
        $payment->setAdditionalInformation(self::PAYMENT_ADDITIONAL_INFO, $additionalData);
        $this->quoteRepository->save($quote);

        //create order
        if ($this->getCheckoutMethod() == \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote();
        }
        $order = $this->quoteManagement->submit($this->_quote);
        if (!$order) {
            die('cannot create order');
        }

        $this->_order = $order;

    }



    public function getRedirectUrl()
    {
        return $this->_redirectUrl;
    }

    protected function _setRedirectUrl($params = null)
    {
        $this->_redirectUrl = '';
    }

    public function getOrder()
    {
        return $this->_order;
    }


    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    public function setCustomerWithAddressChange(
        CustomerDataObject $customerData,
        $billingAddress = null,
        $shippingAddress = null
    ) {
        $this->_quote->assignCustomerWithAddressChange($customerData, $billingAddress, $shippingAddress);
        $this->_customerId = $customerData->getId();
        return $this;
    }

    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn()) {
            return \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER;
        }
        if (!$this->_quote->getCheckoutMethod()) {
            if ($this->_checkoutData->isAllowedGuestCheckout($this->_quote)) {
                $this->_quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
            } else {
                $this->_quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_REGISTER);
            }
        }
        return $this->_quote->getCheckoutMethod();
    }

    protected function prepareGuestQuote()
    {
        $quote = $this->_quote;
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        return $this;
    }
}
