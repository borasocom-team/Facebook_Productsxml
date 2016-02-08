<?php
class Otimizar_FacebookProducts_Model_System_Config_Backend_Installments extends Mage_Core_Model_Config_Data
{
    /**
     * Process data after load
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();
        $value = Mage::helper('facebookProducts/installments')->makeArrayFieldValue($value);
        $this->setValue($value);
    }

    /**
     * Prepare data before save
     */
    protected function _beforeSave()
    {
        $value = $this->getValue();
        $value = Mage::helper('facebookProducts/installments')->makeStorableArrayFieldValue($value);
        $this->setValue($value);
    }
}
