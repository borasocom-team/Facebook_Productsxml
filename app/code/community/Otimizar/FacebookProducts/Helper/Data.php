<?php
class Otimizar_FacebookProducts_Helper_Data extends Mage_Core_Helper_Abstract {

	const IS_DEBUG_MODE_ON = 'dev/fb_product_feed/check_is_debug_enabled';

	public function isDebugModeOn() {
		return Mage::getStoreConfig(self::IS_DEBUG_MODE_ON);
	}

}