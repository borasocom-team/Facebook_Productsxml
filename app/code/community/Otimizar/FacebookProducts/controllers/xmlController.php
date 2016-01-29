<?php
class Otimizar_FacebookProducts_xmlController extends Mage_Core_Controller_Front_Action {
    private $_fileName;
    public function indexAction() {
        $this->_fileName = Mage::getStoreConfig('otimizar_facebookProducts/export/filename');
        $filePath = Mage::getBaseDir('media').DIRECTORY_SEPARATOR;
        if(file_exists($filePath.$this->_fileName))
        {
            $content = file_get_contents($filePath.$this->_fileName);
            header('Content-Type: application/xml; charset=utf-8');
            echo $content;
        }else{
            echo 'File "'.$this->_fileName.'" not exists';
        }
        die;
    }
}