<?php
namespace PL\Paygcc\Controller;

abstract class Benefit extends \Magento\Framework\App\Action\Action
{
    protected $paygccHelper;

    protected $plLogger;

    protected $checkoutSession;

    protected $storeManager;

    protected $orderFactory;

    protected $_paymentHelper;

    protected $_eventManager;

    protected $resultRedirectFactory;

    protected $benefit;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \PL\Paygcc\Helper\Data $paygccHelper,
        \PL\Paygcc\Logger\Logger $plLogger,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\Controller\Result\Redirect $resultRedirectFactory,
        \PL\Paygcc\Model\Benefit $benefit
    ) {
        parent::__construct($context);
        $this->paygccHelper = $paygccHelper;
        $this->plLogger = $plLogger;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->_paymentHelper = $paymentHelper;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->benefit = $benefit;
        $this->_eventManager = $context->getEventManager();
    }

    protected function _getOrder()
    {
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        return $this->getOrder($incrementId);
    }

    protected function _getOrderByIncrementId($incrementId)
    {
        return $this->getOrder($incrementId);
    }


    public function getOrder($incrementId)
    {
        return $this->orderFactory->create()->loadByIncrementId($incrementId);
    }
}
