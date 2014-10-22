<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * PHP Version 5.3
 *
 * @category  Hackathon
 * @package   Hackathon_Magefixes
 * @author    Daniel Niedergesäß <me at danielniedergesaess.de>
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      http://mage-hackathon.de/
 *
 * this class fixes the core bug with broken EAV cache
 *
 */
class Hackathon_Magefixes_Model_Eav_Config extends Mage_Eav_Model_Config
{
    /**
     * cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'EAV';

    /**
     * Entity types cache
     *
     * @var array()
     */
    protected $_entityTypes = null;

    /**
     * Attribute sets cache
     *
     * @var array()
     */
    protected $_attributeSets = null;

    /**
     * Attributes array cache
     *
     * @var array()
     */
    protected $_attributes = null;

    /**
     * check eav cache is enabled and valid for usage
     *
     * @return bool
     */
    protected function _isEavCacheEnabled()
    {
        if ($this->_isCacheEnabled === null) {
            if (false === Mage::isInstalled() || true === Mage::app()->getUpdateMode()) {
                $this->_isCacheEnabled = false;
            } else {
                //check cache is enabled in backend
                $this->_isCacheEnabled = Mage::app()->useCache('eav');
            }
        }
        return $this->_isCacheEnabled;
    }

    /**
     * load data from cache
     *
     * @param  string $cacheId cachekey
     * @return bool|mixed
     */
    protected function _loadFromCache($cacheId)
    {
        if (true === $this->_isEavCacheEnabled()) {
            $string = Mage::app()->loadCache($cacheId);
            if (false !== $data = @unserialize($string)) {
                return $data;
            }
        }
        return false;
    }

    /**
     * Load data from cache
     *
     * @param string $cacheId
     * @param string $data
     * @return void
     */
    protected function _saveToCache($cacheId, $data)
    {
        Mage::app()->saveCache(serialize($data), $cacheId, array(self::CACHE_TAG, Mage_Eav_Model_Entity_Attribute::CACHE_TAG));
    }

    /**
     * init entity types
     *
     * @return Hackathon_Magefixes_Model_Eav_Config
     */
    protected function _initEntityTypes()
    {
        //check entity types already initialized
        if (true === is_array($this->_entityTypes)) {
            return $this;
        }
        // check data is already cached
        $cache = $this->_loadFromCache(self::ENTITIES_CACHE_ID);
        if (true === is_array($cache) && count($cache) > 0) {
            list($this->_entityTypes, $this->_references['entity']) = $cache;
            return $this;
        }

        $this->_entityTypes = array();
        /** @var Mage_Eav_Model_Resource_Entity_Type_Collection $entityTypeCollection */
        $entityTypeCollection = Mage::getResourceModel('eav/entity_type_collection');
        if ($entityTypeCollection->count() > 0) {
            /** @var $entityType Mage_Eav_Model_Entity_Type */
            foreach ($entityTypeCollection as $entityType) {
                $entityTypeId = $entityType->getData('entity_type_id');
                $entityTypeCode = $entityType->getData('entity_type_code');
                $this->_entityTypes[$entityTypeCode] = $entityType;
                $this->_references['entity'][$entityTypeId] = $entityTypeCode;
            }
        }
        return $this;
    }

    /**
     * init attribute sets
     *
     * @return Hackathon_Magefixes_Model_Eav_Config
     */
    protected function _initAllAttributeSets()
    {
        //check attribute sets already initialized
        if (true === is_array($this->_attributeSets)) {
            return $this;
        }
        $this->_attributeSets = array();
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $attributeSetCollection */
        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection');
        if ($attributeSetCollection->count() > 0) {
            /** @var $attributeSet Mage_Eav_Model_Entity_Attribute_Set */
            foreach ($attributeSetCollection as $attributeSet) {
                $this->_attributeSets[$attributeSet->getData('attribute_set_id')] = $attributeSet;
            }
        }
        return $this;
    }

    /**
     * init entity types attributes
     *
     * @return Hackathon_Magefixes_Model_Eav_Config
     */
    protected function _initAllAttributes()
    {
        //check entity types attributes already initialized
        if (true === is_array($this->_attributes)) {
            return $this;
        }

        //preload entity types
        $this->_initEntityTypes();
        // check data is already cached
        $cache = $this->_loadFromCache(self::ATTRIBUTES_CACHE_ID);
        if (true === is_array($cache) && count($cache) > 0) {
            list($this->_attributeSets, $this->_attributes, $this->_references['attribute']) = $cache;
            return $this;
        }

        //preload all attribute sets
        $this->_initAllAttributeSets();
        if (true === is_array($this->_entityTypes) && count($this->_entityTypes) > 0) {
            foreach ($this->_entityTypes as $entityType) {
                /** @var Mage_Eav_Model_Entity_Type $entityType */
                $this->_initAttributes($entityType);
            }
        }

        //store all the stuff into cache
        if (true === $this->_isEavCacheEnabled()) {
            $this->_saveToCache(self::ATTRIBUTES_CACHE_ID,
                array($this->_attributeSets, $this->_attributes, $this->_references['attribute'])
            );
            $this->_saveToCache(self::ENTITIES_CACHE_ID,
                array($this->_entityTypes, $this->_references['entity'])
            );
        }
        return $this;
    }

    /**
     * init entity type attributes
     *
     * @param  Mage_Eav_Model_Entity_Type $entityType
     * @return Hackathon_Magefixes_Model_Eav_Config
     */
    protected function _initAttributes($entityType)
    {
        //if someone added an entity the wrong way we should catch exception and disable caching
        try {
            /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $attributesCollection */
            $attributesCollection = Mage::getResourceModel($entityType->getEntityAttributeCollection());
            if ($attributesCollection) {
                //fetch all attribute data with set info for this entity 
                $attributesData = $attributesCollection->setEntityTypeFilter($entityType)->addSetInfo()->getData();
                $tmpEntityTypeAttributeCodes = array();
                $tmpAttributeSetsAttributeCodes = array();
                foreach ($attributesData as $attributeData) {
                    //init specific attribute
                    $this->_initAttribute($entityType, $attributeData);
                    $tmpEntityTypeAttributeCodes[] = $attributeData['attribute_code'];
                    //find attribute set id's
                    $attributeSetIds = array_keys($attributeData['attribute_set_info']);
                    unset($attributeData['attribute_set_info']);
                    //assign attribut code to attribute set
                    foreach ($attributeSetIds as $attributeSetId) {
                        if (false === isset($tmpAttributeSetsAttributeCodes[$attributeSetId])) {
                            $tmpAttributeSetsAttributeCodes[$attributeSetId] = array();
                        }
                        $tmpAttributeSetsAttributeCodes[$attributeSetId][] = $attributeData['attribute_code'];
                    }
                }
                //add attributes to entity types
                $entityType->setData('attribute_codes', $tmpEntityTypeAttributeCodes);
                if (count($tmpAttributeSetsAttributeCodes) > 0) {
                    foreach ($tmpAttributeSetsAttributeCodes as $attributeSetId => $attributeCodes) {
                        if (isset($this->_attributeSets[$attributeSetId])) {
                            $this->_attributeSets[$attributeSetId]->setData('attribute_codes', $attributeCodes);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_isCacheEnabled = false;
        }
        return $this;
    }

    /**
     * generate attribute from array
     *
     * @param Mage_Eav_Model_Entity_Type
     * @param array $attributeData
     * @return void
     */
    protected function _initAttribute($entityType, $attributeData)
    {
        $entityTypeCode = $entityType->getEntityTypeCode();
        if (false === empty($attributeData['attribute_model'])) {
            $model = $attributeData['attribute_model'];
        } else {
            $model = $entityType->getAttributeModel();
        }

        $attributeCode = $attributeData['attribute_code'];
        $attribute = Mage::getModel($model)->setData($attributeData);
        $entity = $entityType->getEntity();
        if ($entity &&
            true === method_exists($entity,'getDefaultAttributes') &&
            true === in_array($attributeCode, $entity->getDefaultAttributes())) {
            $attribute->setBackendType(Mage_Eav_Model_Entity_Attribute_Abstract::TYPE_STATIC)->setIsGlobal(true);
        }

        $this->_attributes[$entityTypeCode][$attributeCode] = $attribute;
        $this->_addAttributeReference($attributeData['attribute_id'], $attributeCode, $entityTypeCode);

    }

    /**
     * flushes object data needed in observer after attribute changes
     *
     * @return Hackathon_Magefixes_Model_Eav_Config
     */
    public function flushEavConfig()
    {
        $this->_references = null;
        $this->_entityTypes = null;
        $this->_attributeSets = null;
        $this->_attributes = null;
        return $this;
    }

    /**
     * Import attributes data from external source
     * its not needed anymore
     *
     * @see     Mage_Eav_Model_Config
     * @param string|Mage_Eav_Model_Entity_Type $entityType
     * @param array $attributes
     * @return Hackathon_Magefixes_Model_Eav_Config
     */
    public function importAttributesData($entityType, array $attributes)
    {
        return $this;
    }

    /**
     * Prepare attributes for usage in EAV collection
     * its not needed anymore
     *
     * @see     Mage_Eav_Model_Config
     * @param   mixed $entityType
     * @param   array $attributes
     * @return  Hackathon_Magefixes_Model_Eav_Config
     */
    public function loadCollectionAttributes($entityType, $attributes)
    {
        return $this;
    }

    /**
     * Preload entity type attributes for performance optimization
     * its not needed anymore
     *
     * @see     Mage_Eav_Model_Config
     * @param   mixed $entityType
     * @param   mixed $attributes
     * @return  Mage_Eav_Model_Config
     */
    public function preloadAttributes($entityType, $attributes)
    {
        return $this;
    }

    /**
     * Get entity type object by entity type code/identifier
     *
     * @param  Mage_Eav_Model_Entity_Type|string|int $code
     * @return Mage_Eav_Model_Entity_Type
     * @throws Mage_Core_Exception
     */
    public function getEntityType($code)
    {
        //if we already have an object we can return it
        if ($code instanceof Mage_Eav_Model_Entity_Type) {
            return $code;
        }
        //init entity types
        $this->_initEntityTypes();
        //check input is attribute id
        if (true === is_numeric($code)) {
            $entityCode = $this->_getEntityTypeReference($code);
            if ($entityCode !== null) {
                $code = $entityCode;
            }
        }
        //check entity with type exists
        if (false === isset($this->_entityTypes[$code])) {
            Mage::throwException(Mage::helper('eav')->__('Invalid entity_type specified: %s', $code));
        }
        return $this->_entityTypes[$code];
    }

    /**
     * Get attribute by code for entity type
     *
     * @param   Mage_Eav_Model_Entity_Type|string|int $entityType
     * @param   Mage_Eav_Model_Entity_Attribute_Abstract|string|int $code
     * @return  Mage_Eav_Model_Entity_Attribute_Abstract|false
     */
    public function getAttribute($entityType, $code)
    {
        if ($code instanceof Mage_Eav_Model_Entity_Attribute_Interface) {
            return $code;
        }

        //init all attributes
        $this->_initAllAttributes();
        //load entity
        $entityType = $this->getEntityType($entityType);
        //get entity type code as string
        $entityTypeCode = $entityType->getEntityTypeCode();

        //check code is an integer
        if (true === is_integer($code)) {
            $attributeCode = $this->_getAttributeReference($code, $entityTypeCode);
            if ($attributeCode) {
                $code = $attributeCode;
            }
        }

        if (false === isset($this->_attributes[$entityTypeCode][$code])) {
            $attribute = Mage::getModel($entityType->getAttributeModel())->setAttributeCode($code);
        } else {
            $attribute = $this->_attributes[$entityTypeCode][$code];
            $attribute->setEntityType($entityType);
        }
        return $attribute;
    }

    /**
     * Get attribute object for collection usage
     *
     * @param   Mage_Eav_Model_Entity_Type|string|int $entityType
     * @param   Mage_Eav_Model_Entity_Attribute_Abstract|string|int $attribute
     * @return  Mage_Eav_Model_Entity_Attribute_Abstract|false
     */
    public function getCollectionAttribute($entityType, $attribute)
    {
        return $this->getAttribute($entityType, $attribute);
    }

    /**
     * Get codes of all entity type attributes
     *
     * @see     Mage_Eav_Model_Config
     * @param   Mage_Eav_Model_Entity_Type|string|int $entityType
     * @param   Varien_Object $object
     * @return  array
     */
    public function getEntityAttributeCodes($entityType, $object = null)
    {
        //load all attributes
        $this->_initAllAttributes();

        //default attribute set id is 0 eq (default)
        $attributeSetId = 0;
        if (($object instanceof Varien_Object) && $object->getAttributeSetId()) {
            $attributeSetId = $object->getAttributeSetId();
        }

        //check attribute set id is valid
        if ($attributeSetId && true === isset($this->_attributeSets[$attributeSetId])) {
            $attributes = $this->_attributeSets[$attributeSetId]->getData('attribute_codes');
        } else {
            $entityType = $this->getEntityType($entityType);
            $attributes = $entityType->getAttributeCodes();
        }
        return empty($attributes) ? array() : $attributes;
    }
}