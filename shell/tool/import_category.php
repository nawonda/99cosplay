<?php

require_once '../../app/Mage.php';
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

$row = 0;
$temp1 = 0;
$temp2 = 0;
$temp3 = 0;
$pathArray = array("99Cosplay" => 2);

$read_handle = fopen("category_tree.csv", "r");

while (($record = fgetcsv($read_handle)) !== FALSE) {
    echo $row."\n";
    if($row == 0){
        $temp1 = array_search('category', $record);
        $temp2 = array_search('url', $record);
        $temp3 = array_search('path', $record);
    }
    if($row > 0){
        $name = $record[$temp1];
        $url = $record[$temp2];
        $path = $record[$temp3];

        $arr = explode("/", $path);
        if(count($arr) > 2){
            $parent_name = $arr[1];
        }else{
            $parent_name = $arr[0];
        }

        $parentId = $pathArray[$parent_name];

        $id = saveCategory($name, $url,$parentId);

        $pathArray[$name] = $id;
    }
    $row++;
}

function saveCategory($name, $url, $parentId){

    $category = Mage::getModel('catalog/category');
    $category->setName($name);
    $category->setUrlKey($url);
    $category->setIsActive(1);
    $category->setDisplayMode('PRODUCTS');
    $category->setIsAnchor(1); //for active achor
    $category->setStoreId(Mage::app()->getStore()->getId());


    $parentCategory = Mage::getModel('catalog/category')->load($parentId);
    $category->setPath($parentCategory->getPath());

    $category->save();
    return $category->getId();

}