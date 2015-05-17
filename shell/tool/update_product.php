<?php
ini_set('memory_limit', '20000M');
require_once '../../app/Mage.php';
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

/*
 * assign product meta
 */

$collection = Mage::getModel('catalog/product')->getCollection();

foreach ($collection as $product_all) {
    $id = $product_all["entity_id"];
    $sku = $product_all['sku'];

    echo $sku."\n";
    $product = Mage::getModel('catalog/product')->load($id);

    $product->setStockData(array(
        'qty' => 500,
    ));
    $product->save();

}
