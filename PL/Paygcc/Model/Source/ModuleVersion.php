<?php
namespace PL\Paygcc\Model\Source;

use Magento\Config\Block\System\Config\Form\Field\Heading;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Context;
use Magento\Framework\Module\ModuleListInterface;

class ModuleVersion extends Heading
{
    const MODULE_NAME = 'PL_Paygcc';

    const EXTENSION_URL = 'paygcc-payment-gateway-magento-2.html';

    const BASE_URL = 'https://www.paygcc.com/';

    protected $context;

    protected $_moduleList;

    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->context = $context;
        $this->_moduleList = $moduleList;
    }

    public function render(AbstractElement $element)
    {
        $newVersion = null;
        if($this->getVersionInfo()) {
            $versionInfo = json_decode($this->getVersionInfo(),true);
            if (isset($versionInfo[self::MODULE_NAME])) {
                $newVersion = $versionInfo[self::MODULE_NAME];
            }
        }
        $moduleInfo = $this->_moduleList->getOne(self::MODULE_NAME);
        $version = $moduleInfo['setup_version'];
        $content = sprintf("Version %s", $version);
        if (!empty($newVersion) && $newVersion!=$version) {
            $content.= sprintf(
                ' <a target="_blank" href="%s">Check for Updates</a>',
                self::BASE_URL.self::EXTENSION_URL
            );
        }
        return sprintf(
            '<tr id="row_%s">'
            . '<td class="label"></td><td class="value" colspan="3">%s</td>'
            . '</tr>',
            $element->getHtmlId(),
            $content
        );

    }

    public function getVersionInfo()
    {
        $handle = curl_init(self::BASE_URL.'extension_version.json');
        curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if($httpCode >= 200 && $httpCode <= 400) {
            return $response;
        } else {
            return false;
        }
    }

}
