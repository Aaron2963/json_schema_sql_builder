# JSON Schema SQL Builder

This is a simple tool to generate SQL DDL statements from a JSON Schema.

## Install

```bash
$ composer require aaron-lin/json-schema-sql-builder
```

## Usage

### Build SELECT Statement from JSON Schema

For example, the JSON schema is as following:

```json
{
  "$schema": "http://json-schema.org/draft-06/schema#",
  "@table": "products",
  "@id": "id",
  "type": "object",
  "properties": {
    "id": {
      "type": "string"
    },
    "name": {
      "type": "string"
    },
    "_type_name": {
      "type": "string"
    },
    "_weight": {
      "type": "object",
      "properties": {
        "weight": {
          "type": "number"
        },
        "weight_unit": {
          "type": "string",
          "enum": [
            "g",
            "kg"
          ]
        }
      }
    },
    "_bids": {
      "type": "array",
      "@table": "bids",
      "@joinId": "product_id",
      "@id": "id",
      "@orderBy": "time DESC",
      "items": {
        "type": "object",
        "properties": {
          "id": {
            "type": "string"
          },
          "price": {
            "type": "number"
          },
          "time": {
            "type": "string",
            "format": "date-time"
          }
        }
      }
    }
  }
}
```

Use the following code to run the SQL SELECT query:

```php
use Lin\JsonSchemaSqlBuilder\Storage;
use Lin\JsonSchemaSqlBuilder\SelectSQLBuilder;

$SchemaURI = 'path/to/schema.json#';
$DSN = 'mysql:host=db;dbname=test;charset=utf8mb4';
$DB = new \PDO($DSN, 'test', 'test');

try {
  Storage::SetSchemaFromURI($SchemaURI);
} catch (\Exception $e) {
  echo $e->getMessage();
  exit;
}
Storage::AddSelectExpression($SchemaURI . '#/properties/_type_name', '(SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1)');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', 'products.weight_unit');
$Builder = new SelectSQLBuilder($SchemaURI, $DB);
$Builder->SetSelectExpressions()
  ->AddWhere("products.keywords like :keywords", ['keywords' => '%apple%'])
  ->AddOrderBy('products.price', 'DESC')
  ->SetLimit(10)
  ->SetOffset(0);
$Result = $Builder->Execute();
echo json_encode($Result, JSON_PRETTY_PRINT);
// [
//   {
//     "id": "1",
//     "name": "Apple",
//     "_type_name": "Fruit",
//     "_weight": {
//       "weight": "100.00",
//       "weight_unit": "g"
//     },
//     "_bids": [
//       {
//         "id": "7",
//         "price": "400",
//         "time": "2018-01-04 00:00:00"
//       },
//       {
//         "id": "5",
//         "price": "300",
//         "time": "2018-01-03 00:00:00"
//       },
//       {
//         "id": "3",
//         "price": "200",
//         "time": "2018-01-02 00:00:00"
//       },
//       {
//         "id": "1",
//         "price": "100",
//         "time": "2018-01-01 00:00:00"
//       }
//     ]
//   }
// ]
```

As you can see, after initializing JSON schema storage, you can add select expressions to indirect corresponding properties (direct properties are automatically added to select expressions as `table_name.property_key`). Then you can build the SQL SELECT statement with `SelectSQLBuilder::Build` method. For more information, please refer to [the test script](test/select-sql-builder.php), you can also find database schema and data in [the test directory](test/).


### Build INSERT/UPDATE Statement from JSON Schema

For example, the JSON schema is as following:

```json
{
  "$schema": "http://json-schema.org/draft-06/schema#",
  "@table": "products",
  "@id": "id",
  "type": "object",
  "properties": {
    "id": {
      "type": "string"
    },
    "name": {
      "type": "string"
    },
    "_type_name": {
      "type": "string",
      "readonly": true
    },
    "_weight": {
      "type": "object",
      "properties": {
        "weight": {
          "type": "number"
        },
        "weight_unit": {
          "type": "string",
          "enum": [
            "g",
            "kg"
          ]
        }
      }
    },
    "_bids": {
      "type": "array",
      "@table": "bids",
      "@joinId": "product_id",
      "@id": "id",
      "@orderBy": "time DESC",
      "items": {
        "type": "object",
        "properties": {
          "id": {
            "type": "string"
          },
          "price": {
            "type": "number"
          },
          "time": {
            "type": "string",
            "format": "date-time"
          }
        }
      }
    }
  }
}
```

And the data is as following:

```json
{
  "id": "1",
  "name": "Apple",
  "_type_name": "Fruit",
  "_weight": {
    "weight": "100.00",
    "weight_unit": "g"
  },
  "_bids": [
    {
      "id": "7",
      "price": "400",
      "time": "2018-01-04 00:00:00"
    },
    {
      "id": "5",
      "price": "300",
      "time": "2018-01-03 00:00:00"
    },
    {
      "id": "3",
      "price": "200",
      "time": "2018-01-02 00:00:00"
    },
    {
      "id": "1",
      "price": "100",
      "time": "2018-01-01 00:00:00"
    }
  ]
}
```

Use the following code to run the SQL INSERT/UPDATE query:

```php
use Lin\JsonSchemaSqlBuilder\Storage;
use Lin\JsonSchemaSqlBuilder\UpsertSQLBuilder;

$SchemaURI = __DIR__ . '/schema.json#';
$DSN = 'mysql:host=db;dbname=test;charset=utf8mb4';
$DB = new \PDO($DSN, 'test', 'test');

try {
  Storage::SetSchemaFromURI($SchemaURI);
} catch (\Exception $e) {
    echo $e->getMessage();
  exit;
}

Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', 'products.weight_unit');
$Builder = new UpsertSQLBuilder($SchemaURI, $DB, $Data);
$Builder->SetAssignmentList();
$ResultCount = $Builder->Execute();
echo $ResultCount;
// for every new rows added, the return value is 1,
// for every existing rows updated, the return value is 2.
// data contains 1 row in products, 4 rows in bids,
// therefore, if data are all new rows, the return value is 5,
// or data are all existing rows, the return value is 10
```

For every new rows added, the return value of `UpsertSQLBuilder::Execute` is `1`, for every existing rows updated, the return value is `2`. For more information, please refer to [the test script](test/upsert-sql-builder.php), you can also find database schema and data in [the test directory](test/).
