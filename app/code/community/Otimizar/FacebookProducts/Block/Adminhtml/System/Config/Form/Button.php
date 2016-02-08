<?php
class Otimizar_FacebookProducts_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('otimizar/facebookProducts/system/config/button.phtml');
    }
    
    /**
     * Return element html
     * 
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }
    
    /**
     * Return ajax url for button
     * 
     * @return string
     */
    public function getAjaxOldSettingsUrl(){
        return Mage::helper('adminhtml')->getUrl('facebookproducts/adminhtml_index/setoldsettings');
    }
    
    /**
     * Generate button html
     * 
     * @return string
     */
    public function getButtonHtml(){
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'id' => 'facebookProducts_button',
                    'label' => $this->helper('adminhtml')->__('Set old settings'),
                    'onclick' => 'javascript:setOldSettings(); return false;'
                ));
        
        return $button->toHtml();
    }
}