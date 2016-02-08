<?php
class Otimizar_FacebookProducts_Helper_Installments extends Mage_Core_Helper_Abstract
{

    public function getInstallments($store = null)
    {
        $value = Mage::getStoreConfig("facebookProducts/xml/generation", $store);
        $value = $this->_unserializeValue($value);

        return $value;
    }

    protected function _unserializeValue($value)
    {
        if (is_string($value) && !empty($value)) {
            return unserialize($value);
        } else {
            return array();
        }
    }

    protected function _isEncodedArrayFieldValue($value)
    {
        if (!is_array($value)) {
            return false;
        }

        unset($value['__empty']);

        foreach ($value as $_id => $row) {
            if (!is_array($row) || !array_key_exists('installment_boundary', $row) || !array_key_exists('installment_frequency', $row) || !array_key_exists('installment_interest', $row)) {
                return false;
            }
        }

        return true;
    }

    protected function _decodeArrayFieldValue(array $value)
    {
        $result = array();
        unset($value['__empty']);

        return $result;
    }

    protected function _encodeArrayFieldValue(array $value)
    {
        $result = array();

        foreach ($value as $v) {
            $_id = Mage::helper('core')->uniqHash('_');

            if (isset($v['filter_filename'])
                && isset($v['filter_categories'])
                && isset($v['filter_limit'])
                && isset($v['filter_jsoncustomfilter'])
            ) {
                $result[$_id] = array(
                    'filter_filename'         => $v['filter_filename'],
                    'filter_categories'       => $v['filter_categories'],
                    'filter_limit'            => $v['filter_limit'],
                    'filter_jsoncustomfilter' => $v['filter_jsoncustomfilter']
                );
            }
        }

        return $result;
    }

    protected function _serializeValue($value)
    {
        return serialize($value);
    }

    public function makeStorableArrayFieldValue($value)
    {
        if ($this->_isEncodedArrayFieldValue($value)) {
            $value = $this->_decodeArrayFieldValue($value);
        }

        $value = $this->_serializeValue($value);

        return $value;
    }

    public function makeArrayFieldValue($value)
    {
        $value = $this->_unserializeValue($value);

        if (!$this->_isEncodedArrayFieldValue($value)) {
            $value = $this->_encodeArrayFieldValue($value);
        }

        return $value;
    }
}