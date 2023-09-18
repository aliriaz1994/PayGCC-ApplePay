<?php
namespace PL\Paygcc\Model\Source;

class PaymentType implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value'=>'1',
                'label'=>'Credimax'
            ],
            [
                'value'=>'4',
                'label'=>'NBB'
            ]
        ];
    }
}
