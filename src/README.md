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
    "_colors": {
      "type": "array",
      "items": {
        "type": "string"
      }
    }
  }
}
```

Use the following code to generate the SQL SELECT statement:

```php
use Lin\JsonSchemaSqlBuilder\Storage;
use Lin\JsonSchemaSqlBuilder\SelectSQLBuilder;

$SchemaURI = 'path/to/schema.json#';

try {
  Storage::SetSchemaFromURI($SchemaURI);
} catch (\Exception $e) {
  echo $e->getMessage();
  exit;
}
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
print_r($Result);
// Array
// (
//   [0] => Array
//     (
//       [id] => 1
//       [name] => Apple
//       [_weight] => Array
//         (
//             [weight] => 100.00
//             [weight_unit] => g
//         )

//       [_colors] => Array
//         (
//         )
//     )
// )
```

As you can see, after initializing JSON schema storage, you can add select expressions to indirect corresponding properties (direct properties are automatically added to select expressions as `table_name.property_key`). Then you can build the SQL SELECT statement with `SelectSQLBuilder::Build` method.
