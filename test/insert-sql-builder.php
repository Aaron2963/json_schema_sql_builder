<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

// require all file in ../src
foreach (glob(__DIR__ . '/../src/*.php') as $filename) {
  require_once $filename;
}

use Lin\JsonSchemaSqlBuilder\Storage;
use Lin\JsonSchemaSqlBuilder\InsertSQLBuilder;

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

$Data = json_decode('{"id":"1","name":"Apple","type_id":"1","_type_name":"Fruit","_weight":{"weight":"100.00","weight_unit":"g"},"_bidTimes":["2018-01-04 00:00:00","2018-01-03 00:00:00","2018-01-02 00:00:00","2018-01-01 00:00:00"],"_bids":[{"id":"7","price":"400","time":"2018-01-04 00:00:00"},{"id":"5","price":"300","time":"2018-01-03 00:00:00"},{"id":"3","price":"200","time":"2018-01-02 00:00:00"},{"id":"1","price":"100","time":"2018-01-01 00:00:00"}]}', 1);
Storage::AddSelectExpression($SchemaURI . '#/properties/_type_name', '(SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1)');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', 'products.weight_unit');
$Builder = new InsertSQLBuilder($SchemaURI, $DB, $Data);
$Builder->SetAssignmentList();
echo $Builder->Build();
echo "\n";
