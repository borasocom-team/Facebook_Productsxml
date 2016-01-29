<?php

class Otimizar_FacebookProducts_Model_Cron {
    private $path,
        $_fileName,
        $json_custom_filter,
        $xmlHead,
        $xmlContent,
        $xmlFooter,
        $checkIfIsAvailable,
        $categories,
        $collectionLimit,
        $ucfirst;

    public function __construct(){
        $this->path = Mage::getBaseDir().DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR;
        $this->_fileName = Mage::getStoreConfig('otimizar_facebookProducts/export/filename');
        $this->json_custom_filter = json_decode(Mage::getStoreConfig('otimizar_facebookProducts/filters/json_custom_filter'));
        $this->xmlHead = Mage::getStoreConfig('otimizar_facebookProducts/feed/xml_head_content');
        $this->xmlContent = Mage::getStoreConfig('otimizar_facebookProducts/feed/xml_content');
        $this->xmlFooter = Mage::getStoreConfig('otimizar_facebookProducts/feed/xml_footer_content');
        $this->categories = Mage::getStoreConfig('otimizar_facebookProducts/filters/categories');
        $this->checkIfIsAvailable = Mage::getStoreConfig('otimizar_facebookProducts/filters/check_is_available');
        $this->collectionLimit = (int)Mage::getStoreConfig('otimizar_facebookProducts/filters/limit');
        $this->ucfirst = (int)Mage::getStoreConfig('otimizar_facebookProducts/filters/ucfirst');
        $this->htmlentities = (int)Mage::getStoreConfig('otimizar_facebookProducts/filters/htmlentities');

    }

    public function generateFeeds()
    {
        $countProducts = 0;
        $countRepetidos = 0;
        $products = array();

        $this->_putContent($this->xmlHead,'');

        Mage::log("categories filters: ".$this->categories);
        $this->categories = explode(',',$this->categories);

        if(is_array($this->categories)){
            $categoryIds = $this->categories;
        }else{
            Mage::log("invalid configuration for categories filter");
            return;
        }
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $_eachStoreId => $val) {
            $store_id = Mage::app()->getStore($_eachStoreId)->getId();

            $store = Mage::getModel('core/store')->load($store_id);

            foreach($categoryIds as $catId) {
                $_rootcatID = $catId;

                $_productCollection = Mage::getResourceModel('catalog/product_collection')
                    ->joinField('category_id','catalog/category_product','category_id','product_id=entity_id',null,'left')
                    ->addAttributeToFilter('category_id', array('in' => $_rootcatID))
                    ->addAttributeToSelect('*');

                //not repeat products
                if(!empty($products)) {
                    $_productCollection->addAttributeToFilter('entity_id', array('nin' => $products));
                }

                if(!empty($this->json_custom_filter) && is_array($this->son_custom_filter)){
                    foreach($this->json_custom_filter as $k => $v){
                        $_productCollection->addAttributeToFilter($k, $v);
                    }
                }else {
                    $_productCollection->addAttributeToFilter('status', 1)
                        ->addAttributeToFilter('type_id', 'configurable')
                        ->addAttributeToFilter('visibility', 4);
                }

                $_productCollection->joinField('qty',
                    'cataloginventory/stock_item',
                    'qty',
                    'product_id=entity_id',
                    '{{table}}.is_in_stock=1',
                    'inner')
                ;


                $_productCollection->getSelect()->limit($this->collectionLimit);

                $_productCollection->load();

                foreach($_productCollection as $p){
                    $p->setStoreId(1);
                    $pEntityId = $p->getData('entity_id');
                    if(!array_key_exists($pEntityId,$products))
                    {
                        $products[$pEntityId] = $pEntityId;

                        if ($this->checkIfIsAvailable) {
                            if ($p->isAvailable()) {
                                $content = $this->setVars($this->xmlContent, $p);
                                $this->_putContent($content);
                            }
                        } else {
                            $content = $this->setVars($this->xmlContent, $p);
                            $this->_putContent($content);
                        }
                        $countProducts++;
                    }else{
                        $countRepetidos++;
                    }
                }
            }
        }
        Mage::log("count products ".$countProducts);
        Mage::log("count repetidos ".$countRepetidos);
        $this->_putContent($this->xmlFooter);


    }

    private function _putContent($string,$flag = FILE_APPEND)
    {
        if ($flag == '') {
            file_put_contents($this->path . $this->_fileName, $string, null);
        } else {
            file_put_contents($this->path . $this->_fileName, $string, $flag);
        }
    }

    public function setVars($content, $dataObject, $clearVars = false) {

        $match = array();

        preg_match_all('/{{var:(.+?)(\s.*?)?}}/s', $content, $match);

        if (! empty($match)) {

            if ($var_num = count($match[0])) {

                while ($var_num --) {

                    $props = explode('.', $match[1][$var_num]);
                    reset($props);

                    $value = '';

                    if ($props_count = count($props)) {

                        try {
                            switch($props[0]){
                                case 'imageUrl':
                                    $value = 'media/catalog/product' . $dataObject->getImage();
                                    break;
                                case 'imageCacheUrl':
                                    $value = Mage::helper('catalog/image')->init($dataObject, 'image')->resize(600,300);
                                    break;
                                case 'productUrl':
                                    $value = $dataObject->getProductUrl();
                                    break;
                                case 'price':
                                    $value = $dataObject->getData($props[0]);
                                    $value = number_format((double)$value, 2, ',', '.');
                                    break;
                                case 'specialPrice':
                                    $value = $dataObject->getData($props[0]);
                                    if($value <= 0 ){$value = $dataObject->getData("price");}
                                    $value = number_format((double)$value, 2, ',', '.');
                                    break;
                                default:
                                    $value = $dataObject->getData($props[0]);
                            }

                            if ($props_count > 1) {

                                for($i = 1; $i < $props_count; $i ++) {

                                    if ($value instanceof Varien_Object) {

                                        $value = $value->getData($props[$i]);

                                    }
                                    else {

                                        break;

                                    }

                                }

                            }

                            $attributes = array();

                            if ($attributes_value = $match[2][$var_num]) {

                                preg_match_all('/(.*?)\="(.*?)"/s', $attributes_value, $attributes);

                                foreach ($attributes[1] as $i => $attribute_name) {

                                    $value = $this->getFeed()->applyValueFilter($attribute_name, $attributes[2][$i], $value);

                                }

                            }

                        }
                        catch (Exception $e) {
                            $value = '';
                        }

                        if ($value !== null || $clearVars == true) {

                            if($this->htmlentities) {
                                $value = htmlentities($value);
                            }

                            if($this->ucfirst) {
                                $value = ucfirst(strtolower($value));
                            }

                            $content = str_replace($match[0][$var_num], strval($value), $content);

                        }

                    }
                }
            }
        }

        return $content;
    }


}