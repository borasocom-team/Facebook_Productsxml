<?php
class Otimizar_FacebookProducts_Helper_Data extends Mage_Core_Helper_Abstract {

	const IS_DEBUG_MODE_ON = 'dev/fb_product_feed/check_is_debug_enabled';
	const LOG_FILE_NAME = 'fb_product_feed_module.log';

	public function getLogFileFullPath() {
		$fbLogFileFullPath = Mage::getBaseDir().DS.'var'.DS.'log'.DS.'fb_product_feed_module.log';
		return $fbLogFileFullPath;
	}

	public function isDebugModeOn() {
		return Mage::getStoreConfig(self::IS_DEBUG_MODE_ON);
	}

	public function emptyLogFile() {
		$fileObject = new Varien_Io_File();
		if($fileObject->fileExists($this->getLogFileFullPath())) {
			if($fileObject->isWriteable($this->getLogFileFullPath())) {
				$fileObject->rm($this->getLogFileFullPath());
			}
		}
	}

	public function writeLogFile($value, $level = 0) {
		Mage::log($value,$level,self::LOG_FILE_NAME);
	}

}