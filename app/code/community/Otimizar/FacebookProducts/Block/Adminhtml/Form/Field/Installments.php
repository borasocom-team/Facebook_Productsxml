<?php
class Otimizar_FacebookProducts_Block_Adminhtml_Form_Field_Installments extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    protected function _prepareToRender()
    {
        $this->addColumn('filter_filename', array(
            'label' => Mage::helper('facebookProducts')->__('Filename'),
            'style' => 'width:100px'

        ));
        $this->addColumn('filter_categories', array(
            'label' => Mage::helper('facebookProducts')->__('Categories'),
            'style' => 'width:100px'
        ));
        $this->addColumn('filter_limit', array(
            'label' => Mage::helper('facebookProducts')->__('Limit'),
            'style' => 'width:50px',
        ));

        $this->addColumn('filter_jsoncustomfilter', array(
            'label' => Mage::helper('facebookProducts')->__('json custom filter (optional)'),
            'style' => 'width:100px',
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('facebookProducts')->__('Add XML');
    }
}
