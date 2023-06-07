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

try {
  Storage::SetSchemaFromURI($SchemaURI);
} catch (\Exception $e) {
  echo $e->getMessage();
  exit;
}
// add select expressions for indirect corresponding properties
Storage::AddSelectExpression($SchemaURI . '#/properties/_type_name', '(SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1)');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
// Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', 'products.unit');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', null);
Storage::AddSelectExpression($SchemaURI . '#/properties/_colors', null);
$Builder = new SelectSQLBuilder($SchemaURI, null);
$Builder->SetSelectExpressions()
  ->AddWhere("products.keywords LIKE '%:keywords%'", ['keywords' => 'apple'])
  ->AddWhere("products.price >= :price", ['price' => 100])
  ->AddOrderBy('products.price', 'DESC')
  ->SetLimit(10)
  ->SetOffset(0);
echo $Builder->Build(true);
// SELECT products.id AS id, products.name AS name, (SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1) AS _type_name, products.weight AS _weight/weight FROM products WHERE products.keywords LIKE '%:keywords%' AND products.price >= :price ORDER BY products.price DESC LIMIT 10 OFFSET 0;