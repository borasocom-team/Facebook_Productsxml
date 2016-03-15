<?php
class Otimizar_FacebookProducts_Helper_Data extends Mage_Core_Helper_Abstract {

	const IS_DEBUG_MODE_ON = 'dev/fb_product_feed/check_is_debug_enabled';

	public function getLogFileName() {
		$fbLogFileName = Mage::getBaseDir().DS.'var'.DS.'log'.DS.'fb_product_feed_module.log';
		return $fbLogFileName;
	}

	public function isDebugModeOn() {
		return Mage::getStoreConfig(self::IS_DEBUG_MODE_ON);
	}

	public function emptyLogFile() {
		$fileObject = new Varien_Io_File();
		if($fileObject->fileExists($this->getLogFileName())) {
			if($fileObject->isWriteable($this->getLogFileName())) {
				$fileObject->rm($this->getLogFileName());
			}
		}
	}

	public function writeLogFile($value, $level = 0) {
		Mage::log($value,$level,$this->getLogFileName());
	}

}