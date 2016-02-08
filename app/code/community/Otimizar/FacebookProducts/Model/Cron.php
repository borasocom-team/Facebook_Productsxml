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
        $xmlGeneration,
        $products,
        $check_isinstock,
        $only_configurable_products,
        $ucfirst;

    public function __construct(){
        $this->path               = Mage::getBaseDir().DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR;

        $this->xmlHead            = Mage::getStoreConfig('facebookProducts/feed/xml_head_content');
        $this->xmlContent         = Mage::getStoreConfig('facebookProducts/feed/xml_content');
        $this->xmlFooter          = Mage::getStoreConfig('facebookProducts/feed/xml_footer_content');

        $this->checkIfIsAvailable = Mage::getStoreConfig('facebookProducts/filters/check_is_available');
        $this->ucfirst            = (int)Mage::getStoreConfig('facebookProducts/filters/ucfirst');
        $this->htmlentities       = (int)Mage::getStoreConfig('facebookProducts/filters/htmlentities');
        $this->json_custom_filter = json_decode(Mage::getStoreConfig('facebookProducts/filters/json_custom_filter'));
        $this->check_isinstock    = (int)Mage::getStoreConfig('facebookProducts/filters/check_isinstock');
        $this->only_configurable_products    = (int)Mage::getStoreConfig('facebookProducts/filters/only_configurable_products');


        $this->xmlGeneration = Mage::helper('facebookProducts/installments')->makeArrayFieldValue(Mage::getStoreConfig('facebookProducts/xml/generation'));

    }

    public function run()
    {
        if(!Mage::getStoreConfig("facebookProducts/general/enable")){
            return;
        }
        foreach($this->xmlGeneration as $feed){
            if(isset($feed['filter_filename'])) {
                $this->_fileName = trim($feed['filter_filename']);
                $this->json_custom_filter = trim($feed['filter_jsoncustomfilter']);
                $this->generateFeeds($feed);
            }
        }
    }

    private function generateFeeds($feed)
    {
        $countProducts = 0;
        $countRepetidos = 0;
        $this->products = array();
        $this->collectionLimit = $feed['filter_limit']?(int)$feed['filter_limit']:100000;
        $this->_fileName .= '.tmp';

        $this->_putContent($this->xmlHead,'');

        $this->categories = $feed['filter_categories'];

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

                if(!empty($this->json_custom_filter) && is_array($this->json_custom_filter)){
                    foreach($this->json_custom_filter as $k => $v){
                        $_productCollection->addAttributeToFilter($k, $v);
                    }
                }

                $_productCollection->addAttributeToFilter('status', 1)
                    ->addAttributeToFilter('visibility', 4);

                if($this->only_configurable_products){
                    $_productCollection->addAttributeToFilter('type_id', 'configurable');
                }

                if($this->check_isinstock) {
                    $_productCollection->joinField('qty',
                        'cataloginventory/stock_item',
                        'qty',
                        'product_id=entity_id',
                        '{{table}}.is_in_stock=1',
                        'inner');
                }

                $_productCollection->getSelect()->limit($this->collectionLimit);

                $_productCollection->load();
                
                Mage::app()->setCurrentStore(Mage_Core_Model_App::DISTRO_STORE_ID);
                
                foreach($_productCollection as $p){
                    $sku = $p->getData('sku');
                    if(!array_key_exists($sku,$this->products))
                    {
                        $this->products[$sku] = $sku;

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

        rename($this->path . $this->_fileName ,$this->path . $feed['filter_filename']);
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
                                    $value = number_format((double)$value, 2, '.', '');
                                    break;
                                case 'specialPrice':
                                    $value = $dataObject->getData($props[0]);
                                    if($value <= 0 ){$value = $dataObject->getData("price");}
                                    $value = number_format((double)$value, 2, '.', '');
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

                            $value = '<![CDATA['.$value.']]>';

                            $content = str_replace($match[0][$var_num], strval($value), $content);

                        }

                    }
                }
            }
        }
        return $content;
    }
}