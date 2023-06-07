<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

// require all file in ../src
foreach (glob(__DIR__ . '/../src/*.php') as $filename) {
    require_once $filename;
}

use Lin\JsonSchemaSqlBuilder\Storage;
use Lin\JsonSchemaSqlBuilder\SelectSQLBuilder;

$SchemaURI = __DIR__ . '/schema.json#';
$DSN = 'mysql:host=db;dbname=test;charset=utf8mb4';
$DB = new \PDO($DSN, 'test', 'test');

try {
  Storage::SetSchemaFromURI($SchemaURI);
} catch (\Exception $e) {
  echo $e->getMessage();
  exit;
}

/**
 * Test SelectSQLBuilder::Execute()
 */

// build sql query with SelectSQLBuilder
Storage::AddSelectExpression($SchemaURI . '#/properties/_type_name', '(SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1)');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', 'products.weight_unit');
Storage::AddSelectExpression($SchemaURI . '#/properties/_colors', null);
$Builder = new SelectSQLBuilder($SchemaURI, $DB);
$Builder->SetSelectExpressions()
  ->AddWhere("products.keywords like :keywords", ['keywords' => '%apple%'])
  ->AddOrderBy('products.price', 'DESC')
  ->SetLimit(10)
  ->SetOffset(0);
$Result = $Builder->Execute();

// expected result
$SQLString = "SELECT id, name, (SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1) AS _type_name, weight, weight_unit FROM products WHERE products.keywords like '%apple%' ORDER BY products.price DESC LIMIT 10 OFFSET 0;";
$Statement = $DB->query($SQLString);
$ExpectedArray = $Statement->fetchAll(\PDO::FETCH_ASSOC);
foreach ($ExpectedArray as $i => $Expected) {
  $ExpectedArray[$i]['_weight'] = [
    'weight' => $Expected['weight'],
    'weight_unit' => $Expected['weight_unit']
  ];
  $ExpectedArray[$i]['_colors'] = [];
  unset($ExpectedArray[$i]['weight']);
  unset($ExpectedArray[$i]['weight_unit']);
}

// compare result
if ($Result === $ExpectedArray) {
  echo "[PASS] SelectSQLBuilder::Execute test passed\n";
} else {
  echo "[FAIL] test failed\n";
  echo "Expected:\n";
  print_r($ExpectedArray);
  echo "Result:\n";
  print_r($Result);
}

/**
 * Test SelectSQLBuilder::Count()
 */

$Builder = new SelectSQLBuilder($SchemaURI, $DB);
$Builder->AddWhere("products.keywords like :keywords", ['keywords' => '%apple%']);
$Count = $Builder->Count();

$CountSQLString = "SELECT COUNT(*) FROM products WHERE products.keywords like '%apple%';";
$Statement = $DB->query($CountSQLString);
$ExpectedCount = (int) $Statement->fetchAll(\PDO::FETCH_ASSOC)[0]['COUNT(*)'];

if ($Count === $ExpectedCount) {
  echo "[PASS] SelectSQLBuilder::Count test passed\n";
} else {
  echo "[FAIL] test failed\n";
  echo "Expected:\n";
  print_r($ExpectedCount);
  echo "Result:\n";
  print_r($Count);
}