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
        $only_if_image_exists,
        $ucfirst,
        $countProducts,
        $countRepetidos;

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
        $this->only_configurable_products = (int)Mage::getStoreConfig('facebookProducts/filters/only_configurable_products');
        $this->only_if_image_exists       = (int)Mage::getStoreConfig('facebookProducts/filters/only_if_image_exists');

        $this->xmlGeneration = Mage::helper('facebookProducts/installments')->makeArrayFieldValue(Mage::getStoreConfig('facebookProducts/xml/generation'));
        $this->use_category_children = (int)Mage::getStoreConfig('facebookProducts/filters/use_category_children');
    }

    public function run()
    {
        if(Mage::helper('facebookProducts')->isDebugModeOn()) {
            Mage::helper('facebookProducts')->emptyLogFile();
        }

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
        $this->countProducts  = 0;
        $this->countRepetidos = 0;
        $this->products = array();
        $this->collectionLimit = $feed['filter_limit']?(int)$feed['filter_limit']:100000;
        $this->_fileName .= '.tmp';

        $this->_putContent($this->xmlHead,'');

        $this->categories = $feed['filter_categories'];

        Mage::helper('facebookProducts')->writeMessagesFile("categories filters: ".$this->categories);
        $this->categories = explode(',',$this->categories);

        if(is_array($this->categories)){
            $categoryIds = $this->categories;
        }else{
            Mage::helper('facebookProducts')->writeMessagesFile("invalid configuration for categories filter");
            return;
        }
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $_eachStoreId => $val) {
            $store_id = Mage::app()->getStore($_eachStoreId)->getId();

            $store = Mage::getModel('core/store')->load($store_id);

            foreach($categoryIds as $catId) {

                if($this->use_category_children){
                    $this->_productWrite($catId);
                    $children = Mage::getModel('catalog/category')->getCategories($catId);
                    foreach ($children as $category) {
                        $catId = $category->getId();
                        $this->_productWrite($catId);
                    }
                }else{
                    $this->_productWrite($catId);
                }
            }
        }
        Mage::helper('facebookProducts')->writeMessagesFile("count products ".$this->countProducts);
        Mage::helper('facebookProducts')->writeMessagesFile("count repetidos ".$this->countRepetidos);
        $this->_putContent($this->xmlFooter);

        rename($this->path . $this->_fileName ,$this->path . $feed['filter_filename']);
    }

    private function _productWrite($catId){


        $_productCollection = Mage::getResourceModel('catalog/product_collection')
                                  ->joinField('category_id','catalog/category_product','category_id','product_id=entity_id',null,'left')
                                  ->addAttributeToFilter('category_id', array('in' => $catId))
                                  ->addAttributeToSelect('*');

        //not repeat products
        if(!empty($this->products)) {
            $_productCollection->addAttributeToFilter('entity_id', array('nin' => $this->products));
        }

        if(!empty($this->json_custom_filter) && is_array($this->json_custom_filter)){
            foreach($this->json_custom_filter as $k => $v){
                $_productCollection->addAttributeToFilter($k, $v);
            }
        }

        $_productCollection->addAttributeToFilter('status', 1);
        $_productCollection->addAttributeToFilter('visibility', 4);

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

	        if(Mage::helper('facebookProducts')->isDebugModeOn()) {
		        Mage::helper('facebookProducts')->writeLogFile('------->Product Object: ');
		        Mage::helper('facebookProducts')->writeLogFile($p);
	        }

            $sku = $p->getData('sku');
            if(!array_key_exists($sku,$this->products))
            {
                if($this->only_if_image_exists){
                    $productImage = Mage::getBaseDir('media').'/catalog/product' . $p->getImage();
                    if(!file_exists($productImage)){
                        continue;
                    }
                    unset($productImage);
                }

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
                $this->countProducts++;
            }else{
                $this->countRepetidos++;
            }
        }
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
                                    //$value = 'media/catalog/product' . $dataObject->getImage();
                                    $value = Mage::getModel('catalog/product_media_config')->getMediaUrl( $dataObject->getImage() );
                                    break;
                                case 'imageCacheUrl':
                                    $product = Mage::getModel('catalog/product')->load($dataObject->getId());
                                    $value = Mage::helper('catalog/image')->init($product, 'image')->resize(600,600);
                                    break;
                                case 'productUrl':
                                    $value = $dataObject->getProductUrl();
                                    break;
                                case 'price':
                                    $value = $dataObject->getData($props[0]);
                                    $value = number_format((double)$value, 2, '.', '');
                                    $value .= ' '.strtoupper(Mage::app()->getStore()->getCurrentCurrencyCode());
                                    break;
                                case 'specialPrice':
                                    $value = $dataObject->getData($props[0]);
                                    if($value <= 0 ){$value = $dataObject->getData("price");}
                                    $value = number_format((double)$value, 2, '.', '');
                                    $value .= ' '.strtoupper(Mage::app()->getStore()->getCurrentCurrencyCode());
                                    break;
                                case 'productColors':
                                    $value = $this->productColors($dataObject);
                                    break;
                                case 'productSizes':
                                    $value = $this->productSizes($dataObject);
                                    break;
                                case 'categorySubcategory':
                                    $value = $this->categorySubcategory($dataObject);
                                    $value = str_replace('Default Category > ','',$value);
                                    break;
                                case 'googleCategory':
                                    $value = $this->getGoogleCategory($dataObject);
                                    break;
                                default:
                                    $value = $dataObject->getData($props[0]);
                            }

                            if(Mage::helper('facebookProducts')->isDebugModeOn()) {
                                Mage::helper('facebookProducts')->writeLogFile('------->' . $props[0] . ': ');
                                Mage::helper('facebookProducts')->writeLogFile($value);
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

                            if($this->ucfirst && ($props[0]!='price' && $props[0]!='specialPrice')) {
                                $value = ucfirst(strtolower($value));
                            }

	                        if(!empty($value)) {
		                        $value = '<![CDATA['.$value.']]>';
	                        }

                            $content = str_replace($match[0][$var_num], strval($value), $content);

                        }

                    }
                }
            }
        }
        return $content;
    }

    public function productColors($_product){
        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($_product->getId());

        if($_product->getTypeId() == 'simple' && empty($parentIds)) {
            return '';
        }

        $productAttributeOptions = $_product->getTypeInstance(TRUE)->getConfigurableAttributesAsArray($_product);
        $swatches = array();

        foreach( $productAttributeOptions as $productAttribute ){
            if( $productAttribute['attribute_code'] == 'color' ){
                foreach( $productAttribute['values'] as $attribute ){
                    $swatches[] = $attribute['label'];
                }
            }
        }

        $response = implode("/",$swatches);
        return $response;
    }

    public function productSizes($_product){
        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($_product->getId());

        if($_product->getTypeId() == 'simple' && empty($parentIds)) {
            return '';
        }
        $productAttributeOptions = $_product->getTypeInstance(TRUE)->getConfigurableAttributesAsArray($_product);
        $swatches = array();

        foreach( $productAttributeOptions as $productAttribute ){
            if( $productAttribute['attribute_code'] == 'size'
                || $productAttribute['attribute_code'] == 'size_roupa'
                || $productAttribute['attribute_code'] == 'size_calcado'){
                foreach( $productAttribute['values'] as $attribute ){
                    $swatches[] = $attribute['label'];
                }
            }
        }

        $response = implode("/",$swatches);
        return $response;

    }

	public function categorySubcategory($_product) {
		$level = 1;
		$deepestId = false;
		$categories = array();
		$response = array();

		foreach ($_product->getCategoryCollection() as $category) {
			$category = Mage::getModel('catalog/category')->load($category->getId());
			if($category->getIsActive()) {
				$path = $category->getPathIds();
				array_shift($path);
				$categories[$category->getId()] = array(
					'name' => $category->getName(),
					'path' => $path
				);
				if((int)$category->getLevel() > $level) {
					$deepestId = $category->getId();
					$level = (int)$category->getLevel();
				}
			}
		}

		if(!$deepestId) {
			return false;
		}

		foreach($categories[$deepestId]['path'] as $id) {
			array_push($response, $categories[$id]['name']);
		}

		$response = implode(" > ",$response);
		return $response;

	}

	public function getGoogleCategory($_product){
		$level = 1;
		$deepestId = false;
		$categories = array();

		foreach ($_product->getCategoryCollection() as $category) {
			$category = Mage::getModel('catalog/category')->load($category->getId());
			if($category->getIsActive()) {
				$categories[$category->getId()] = array(
					'googleCategoryName' => $category->getGoogleCategoryFb()
				);
				if((int)$category->getLevel() > $level) {
					$deepestId = $category->getId();
					$level = (int)$category->getLevel();
				}
			}
		}

		if(!$deepestId) {
			return false;
		}

		$googleCategory = $categories[$deepestId]['googleCategoryName'];
		if(!empty($googleCategory)) {
			return $googleCategory;
		}

		return false;
	}
}