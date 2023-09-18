<?php
namespace PL\Paygcc\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use PL\Paygcc\Helper\Data as PaygccHelper;
use PL\Paygcc\Model\ApiCheckout as ApiCheckoutModel;

abstract class ApiCheckout extends Action
{
    /**
     * @var \PL\Paygcc\Model\Checkout\ApiCheckout
     */
    protected $_apiCheckout;

    protected $_config;

    protected $_quote = false;

    protected $_configType;

    protected $_configMethod = 'paygcc_apicheckout';

    protected $_checkoutType = \PL\Paygcc\Model\Checkout\ApiCheckout::class;

    protected $_customerSession;

    protected $_customerId;

    protected $_checkoutSession;

    protected $_orderFactory;

    protected $_apiCheckoutFactory;

    protected $_paygccSession;

    protected $_urlHelper;

    protected $_customerUrl;

    protected $quoteRepository;

    protected $_plLogger;

    /**
     * @var ApiCheckoutModel
     */
    protected $_apiCheckoutModel;

    protected $_paygccHelper;

    protected $_resultRedirectFactory;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Session\Generic $paygccSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \PL\Paygcc\Model\Checkout\ApiCheckoutFactory $apiCheckoutFactory,
        \PL\Paygcc\Logger\Logger $plLogger,
        PaygccHelper $paygccHelper,
        ApiCheckoutModel $apiCheckoutModel,
        \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory,
        CartRepositoryInterface $quoteRepository = null
    )
    {
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_paygccSession = $paygccSession;
        $this->_urlHelper = $urlHelper;
        $this->_customerUrl = $customerUrl;
        $this->_apiCheckoutFactory = $apiCheckoutFactory;
        $this->_plLogger = $plLogger;
        $this->_apiCheckoutModel = $apiCheckoutModel;
        $this->_paygccHelper = $paygccHelper;
        $this->_resultRedirectFactory  = $resultRedirectFactory;

        parent::__construct($context);

        $this->quoteRepository = $quoteRepository ?: ObjectManager::getInstance()->get(CartRepositoryInterface::class);
        $this->_apiCheckout = $this->_apiCheckoutFactory->create(['params' => ['quote' => $this->_getQuote()]]);
    }


    protected function _initCheckout(CartInterface $quoteObject = null)
    {
        $quote = $quoteObject ? $quoteObject : $this->_getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot initialize payment method.'));
        }
        if (!(float)$quote->getGrandTotal()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment Processor cannot process orders with a zero balance due.'));
        }

        $this->_apiCcheckout = $this->_apiCheckoutFactory->create(['params' => ['quote' => $quote]]);

    }
    protected function _getSession()
    {
        return $this->_paygccSession;
    }


    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * @return CartInterface|Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            if ($this->_getSession()->getQuoteId()) {
                $this->_quote = $this->quoteRepository->get($this->_getSession()->getQuoteId());
                $this->_getCheckoutSession()->replaceQuote($this->_quote);
            } else {
                $this->_quote = $this->_getCheckoutSession()->getQuote();
            }
        }
        return $this->_quote;
    }




    public function getCustomerBeforeAuthUrl()
    {
        return null;
    }


    public function getLoginUrl()
    {
        return $this->_customerUrl->getLoginUrl();
    }

    public function getRedirectActionName()
    {
        return 'redirect';
    }

    public function redirectLogin()
    {
        $this->_actionFlag->set('', 'no-dispatch', true);
        $this->_customerSession->setBeforeAuthUrl($this->_redirect->getRefererUrl());
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam($this->_customerUrl->getLoginUrl(), ['context' => 'checkout'])
        );
    }

    public function getActionFlagList()
    {
        return [];
    }

    abstract public function execute();

}

