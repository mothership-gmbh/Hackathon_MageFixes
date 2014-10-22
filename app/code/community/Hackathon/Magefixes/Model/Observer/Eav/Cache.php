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
 * this observer handles cache regeneration after attribute change action
 *
 */
class Hackathon_Magefixes_Model_Observer_Eav_Cache
{
    /**
     * Clean eav cache on attribute set add/save/delete
     */
    public function flushEavCache()
    {
        //flush eav config array cache to force init of all entities
        Mage::getSingleton('eav/config')->flushEavConfig();
        //flush eav cache in storage
        Mage::app()->cleanCache(array(Mage_Eav_Model_Entity_Attribute::CACHE_TAG));
    }
}