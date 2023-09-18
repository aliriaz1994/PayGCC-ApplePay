<?php
namespace PL\Paygcc\Controller\Webhook;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Notify extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{
    protected $paygccHelper;

    protected $plLogger;

    protected $storeManager;

    protected $orderFactory;

    protected $apiCheckout;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \PL\Paygcc\Helper\Data $paygccHelper,
        \PL\Paygcc\Logger\Logger $plLogger,
        \PL\Paygcc\Model\ApiCheckout $apiCheckout
    ) {
        parent::__construct($context);
        $this->paygccHelper = $paygccHelper;
        $this->plLogger = $plLogger;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->apiCheckout = $apiCheckout;
    }
    public function execute()
    {
        if ($this->getRequest()->isPost()) {
            $ipnResponse = $this->getRequest()->getPostValue();
            if ($this->apiCheckout->getConfigData('debug')) {
                $this->plLogger->debug("NOTIFY RESPONSE: ".print_r($ipnResponse,1));
            }

        }
    }

    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
