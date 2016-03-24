<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$entityTypeId     = $installer->getEntityTypeId('catalog_category');
$attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $installer->getAttributeGroup($entityTypeId, $attributeSetId);

$installer->addAttribute('catalog_category', 'google_category_fb', array(
	'type'              => 'varchar',
	'label'             => 'Facebook Google merchant category',
	'input'             => 'text',
	'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
	'visible'           => true,
	'required'          => false,
	'user_defined'      => false,
	'default'           => '',
	'searchable'        => false,
	'filterable'        => false,
	'comparable'        => false,
	'visible_on_front'  => false,
	'unique'            => false,
));

$installer->addAttributeToGroup(
	$entityTypeId,
	$attributeSetId,
	$attributeGroupId,
	'google_category_fb',
	'4'
);


$installer->endSetup();