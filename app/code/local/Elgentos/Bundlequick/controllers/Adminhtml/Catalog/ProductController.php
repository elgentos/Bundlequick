<?php

class Elgentos_Bundlequick_Adminhtml_Catalog_ProductController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Product list page
     */
    public function bundlequickAction()
    {
        if(Mage::getStoreConfig('bundlequick/general/disabled',Mage::app()->getStore())) return null;

        $productIds = $this->getRequest()->getParam('product');
        $storeId = (int)$this->getRequest()->getParam('store', 0);
        $discount = (int)$this->getRequest()->getParam('discount', 0);
        if(is_numeric($discount)) {
            $discount -= 1;
        } else {
            $discount = Mage::getStoreConfig('bundlequick/general/defaultdiscount',Mage::app()->getStore());
        }

        if (!is_array($productIds)) {
            $this->_getSession()->addError($this->__('Please select product(s)'));
        }
        else {
            $unsets = array('sku','entity_id','entity_type_id','type_id','has_options','required_options','created_at','updated_at','name','meta_title','meta_description','image','small_image','thumbnail','url_key','url_path','custom_design','page_layout','options_container','image_label','small_image_label','thumbnail_label','gift_message_available','description','short_description','meta_keyword','custom_layout_update','price','special_price','cost','weight','status','is_recurring','visibility','enable_googlecheckout','is_imported','special_from_date','special_to_date','news_from_date','news_to_date','custom_design_from','custom_design_to','media_gallery','tier_price','tier_price_changed','stock_item','is_in_stock','is_salable');
            try {
                $short_description = $description = $sku = $name = null;
                $price = 0;
                $selections = array();
                $items = array();
                $images = array();
                $cats = array();
                $selected = array();
                foreach ($productIds as $key=>$productId) {
                    $product = Mage::getSingleton('catalog/product')
                        ->unsetData()
                        ->setStoreId($storeId)
                        ->load($productId);

                    $items[$productId] = array(
                        'title' => $product->getName(),
                        'option_id' => '',
                        'delete' => '',
                        'type' => 'checkbox',
                        'required' => 1,
                        'store_id' => 1,
                        'position' => 0
                    );

                    $selectionRawData[] = array(
                        'selection_id' => '',
                        'option_id' => '',
                        'product_id' => $product->getId(),
                        'delete' => '',
                        'store_id' => 1,
                        'is_default' => 1,
                        'selection_price_value' => 0, // changed from $product->getPrice() to 0 due to double pricing
                        'selection_price_type' => 0,
                        'selection_qty' => 1,
                        'selection_can_change_qty' => 0,
                        'position' => 0
                    );
                    $selections[$productId] = $selectionRawData;

                    $name .= $product->getName().' + ';
                    $sku .= $product->getSku().'-';
                    $description .= $product->getDescription().'\n';
                    $short_description .= $product->getShortDescription().'\n';
                    $price += (int)$product->getPrice();
                    $productDataTemp= $product->getData();
                    $selected[] = $productDataTemp['image'];
                    foreach($unsets as $unset) {
                        if(isset($productDataTemp[$unset])) unset($productDataTemp[$unset]);
                    }
                    if(!isset($intersects)) $intersects = $productDataTemp;
                    $intersects = array_intersect_assoc($productDataTemp,$intersects);
                    $cats = array_merge($cats,$product->getCategoryIds());
                    $gallery = $product->getMediaGallery();
                    $images = array_merge($images,$gallery['images']);
                }

                $price = $price*(1-($discount/100));
                $name = substr($name,0,-3);
                $sku = substr($sku,0,-1);
                $description = substr($description,0,-2);
                $short_description = substr($short_description,0,-2);

                $product = Mage::getModel('catalog/product');
                foreach($intersects as $key=>$value) {
                    $product->setData($key,$value);
                }
                $attributeSetId = (isset($intersects['attribute_set_id']) ? $intersects['attribute_set_id'] : 4);
                $product->setAttributeSetId($attributeSetId);
                $product->setPriceType(1);
                $product->setTypeId('bundle');
                $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
                $product->setStatus(1);
                $product->setSkuType(0);
                $product->setSku($sku);
                $product->setWebsiteIDs(array(1));
                $product->setStoreIDs(array(1));
                foreach($images as $image) {
                    if(in_array($image['file'],$selected)) {
                        $fields = array ('image','small_image','thumbnail');
                    } else {
                        $fields = array();
                    }
                    $filePath = Mage::getBaseDir('media').'/catalog/product'.$image['file'];
                    if(file_exists($filePath)) {
                        $product->addImageToMediaGallery($filePath, $fields, false, false);
                    }
                }
                $product->setStockData(array(
                    'is_in_stock' => 1,
                    'qty' => 1,
                    'manage_stock' => 0
                ));
                $product->setName($name);
                $product->setDescription($description);
                $product->setShortDescription($short_description);
                $product->setPrice($price);

                Mage::register('product', $product);
                Mage::register('current_product', $product);

                $product->setBundleOptionsData($items);
                $product->setBundleSelectionsData($selections);
                $product->setCanSaveCustomOptions(true);
                $product->setCanSaveBundleSelections(true);

                if(Mage::getStoreConfig('bundlequick/general/cats',Mage::app()->getStore())) {
                    $product->setCategoryIds($cats);
                }

                try {
                    if (is_array($errors = $product->validate())) {
                        $strErrors = array();
                        foreach($errors as $code=>$error) {
                            $strErrors[] = ($error === true)? Mage::helper('catalog')->__('Attribute "%s" is invalid.', $code) : $error;
                        }
                        die(implode("\n", $strErrors));
                    }

                    $product->save();
                    Mage::dispatchEvent('catalog_product_bundlequick_after', array('products'=>$productIds));
                    $this->_getSession()->addSuccess(
                        $this->__('Bundle product was successfully created', count($productIds))
                    );

                    if(Mage::getStoreConfig('bundlequick/general/related',Mage::app()->getStore())) {
                        $related[$product->getId()] = array('position'=>$key);
                        foreach($productIds as $productId) {
                            $_product = Mage::getModel('catalog/product')->load($productId);
                            $_product->setRelatedLinkData($related)->save();
                        }
                    }

                    $this->_redirect('adminhtml/catalog_product/edit/id/'.$product->getId(), array());
                } catch (Mage_Core_Exception $e) {
                    die($e->getMessage());
                }
            }
            catch (Exception $e) {
                $this->_getSession()->addException($e, $e->getMessage());
                $this->_redirect('adminhtml/catalog_product/index/', array());
            }
        }
    }
}
