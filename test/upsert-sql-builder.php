<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

// require all file in ../src
foreach (glob(__DIR__ . '/../src/*.php') as $filename) {
    require_once $filename;
}

use Lin\JsonSchemaSqlBuilder\Storage;
use Lin\JsonSchemaSqlBuilder\UpsertSQLBuilder;
use Lin\JsonSchemaSqlBuilder\SelectSQLBuilder;

$SchemaURI = __DIR__ . '/schema.json#';
$DSN = 'mysql:host=db;dbname=test;charset=utf8mb4';
$DB = new \PDO($DSN, 'test', 'test');
$FinalResult = true;

try {
    Storage::SetSchemaFromURI($SchemaURI);
} catch (\Exception $e) {
    echo $e->getMessage();
    exit;
}

$DataId = [
    'product_id' => ['3'],
    'bid_id' => ['8', '9']
];
$Data = [
    "id" => $DataId['product_id'][0],
    "name" => "Coconut",
    "type_id" => "1",
    "_type_name" => "Fruit",
    "_weight" => [
        "weight" => "100.00",
        "weight_unit" => "g"
    ],
    "_bidTimes" => [
        "2018-01-04 00:00:00",
        "2018-01-03 00:00:00"
    ],
    "_bids" => [
        [
            "id" => $DataId['bid_id'][0],
            "price" => "400",
            "time" => "2018-01-04 00:00:00"
        ],
        [
            "id" => $DataId['bid_id'][1],
            "price" => "300",
            "time" => "2018-01-03 00:00:00"
        ]
    ]
];

$ClearData = function () use ($DataId, $DB) {
    $SQLString = [
        "DELETE FROM bids WHERE id = :id1 or id = :id2;",
        "DELETE FROM products WHERE id = :id;"
    ];
    $SQLParams = [
        ['id1' => $DataId['bid_id'][0], 'id2' => $DataId['bid_id'][1]],
        ['id' => $DataId['product_id'][0]]
    ];
    foreach ($SQLString as $i => $SQL) {
        $Statement = $DB->prepare($SQL);
        $Statement->execute($SQLParams[$i]);
    }
};


/**
 * Test UpsertSQLBuilder::Execute()
 */

// build sql query with UpsertSQLBuilder
Storage::AddSelectExpression($SchemaURI . '#/properties/_type_name', '(SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1)');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', 'products.weight_unit');
call_user_func($ClearData);
$Builder = new UpsertSQLBuilder($SchemaURI, $DB, $Data);
$Builder->SetAssignmentList();
$ResultCount = $Builder->Execute();
$SelectBuilder = new SelectSQLBuilder($SchemaURI, $DB);
$SelectBuilder->SetSelectExpressions()
    ->AddWhere("products.id = :id", ['id' => $DataId['product_id'][0]]);
$Result = $SelectBuilder->Execute();

// expected result
$SQLString = [
    "INSERT INTO products SET id = :id, name = :name, type_id = :type_id, weight = :weight, weight_unit = :weight_unit ON DUPLICATE KEY UPDATE name = :name, type_id = :type_id, weight = :weight, weight_unit = :weight_unit;",
    "INSERT INTO bids SET id = :id, price = :price, time = :time, product_id = :product_id ON DUPLICATE KEY UPDATE price = :price, time = :time, product_id = :product_id;",
    "INSERT INTO bids SET id = :id, price = :price, time = :time, product_id = :product_id ON DUPLICATE KEY UPDATE price = :price, time = :time, product_id = :product_id;"
];
$SQLParams = [
    [
        'id' => $DataId['product_id'][0],
        'name' => $Data['name'],
        'type_id' => $Data['type_id'],
        'weight' => $Data['_weight']['weight'],
        'weight_unit' => $Data['_weight']['weight_unit']
    ],
    [
        'id' => $DataId['bid_id'][0],
        'price' => $Data['_bids'][0]['price'],
        'time' => $Data['_bids'][0]['time'],
        'product_id' => $DataId['product_id'][0]
    ],
    [
        'id' => $DataId['bid_id'][1],
        'price' => $Data['_bids'][1]['price'],
        'time' => $Data['_bids'][1]['time'],
        'product_id' => $DataId['product_id'][0]
    ]
];
call_user_func($ClearData);
$ExpectedCount = 0;
foreach ($SQLString as $i => $SQL) {
    $Statement = $DB->prepare($SQL);
    $Count = $Statement->execute($SQLParams[$i]);
    $ExpectedCount += $Count;
}
$SelectBuilder = new SelectSQLBuilder($SchemaURI, $DB);
$SelectBuilder->SetSelectExpressions()
    ->AddWhere("products.id = :id", ['id' => $DataId['product_id'][0]]);
$Expected = $SelectBuilder->Execute();

// compare
$FinalResult = $FinalResult && ($ResultCount === $ExpectedCount && $Result === $Expected);
if ($ResultCount === $ExpectedCount && $Result === $Expected) {
    echo "[PASS] UpsertSQLBuilder::Execute test passed\n";
} else {
    echo "[FAIL] UpsertSQLBuilder::Execute test failed\n";
    echo "ResultCount: {$ResultCount}\n";
    echo "ExpectedCount: {$ExpectedCount}\n";
    echo "Result: " . json_encode($Result) . "\n";
    echo "Expected: " . json_encode($Expected) . "\n";
}

exit($FinalResult ? 0 : 1);
