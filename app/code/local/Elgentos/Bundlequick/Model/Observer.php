<?php

class Elgentos_Bundlequick_Model_Observer
{

    /**
     * add Massaction Option to Productgrid
     *
     * @param $observer Varien_Event
     */
    public function addMassactionToProductGrid($observer)
    {
        if(Mage::getStoreConfig('bundlequick/general/disabled',Mage::app()->getStore())) return null;
        $block = $observer->getBlock();
        if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid){

            $discounts[0] = 'Default';
            for($i=0;$i<=100;$i++) {
                $discounts[$i+1] = $i;
            }
            $discounts[0] = 'Default';

            $block->getMassactionBlock()->addItem('bundlequick', array(
                'label'=> Mage::helper('catalog')->__('Bundlequick'),
                'url'  => $block->getUrl('*/*/bundlequick', array('_current'=>true)),
                'additional' => array(
                    'discount' => array(
                        'name' => 'discount',
                        'type' => 'select',
                        'class' => 'required-entry',
                        'label' => Mage::helper('catalog')->__('Discount'),
                        'values' => $discounts
                    )
                )
            ));
        }
    }
}