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
// add select expressions for indirect corresponding properties
Storage::AddSelectExpression($SchemaURI . '#/properties/_type_name', '(SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1)');
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight', 'products.weight');
// skip `_weight.weight_unit` property
Storage::AddSelectExpression($SchemaURI . '#/properties/_weight/properties/weight_unit', null);
$Builder = new SelectSQLBuilder($SchemaURI, null);
$Builder->SetSelectExpressions()
  ->AddWhere("products.keywords LIKE '%:keywords%'", ['keywords' => 'apple'])
  ->AddWhere("products.price >= :price", ['price' => 100])
  ->AddOrderBy('products.price', 'DESC')
  ->SetLimit(10)
  ->SetOffset(0);
echo $Builder->Build(true);
// SELECT products.id AS id, products.name AS name, (SELECT name FROM product_types WHERE product_types.id = products.type_id LIMIT 1) AS _type_name, products.weight AS _weight/weight FROM products WHERE products.keywords LIKE '%:keywords%' AND products.price >= :price ORDER BY products.price DESC LIMIT 10 OFFSET 0;
```

As you can see, after initializing JSON schema storage, you can add select expressions to indirect corresponding properties (direct properties are automatically added to select expressions as `table_name.property_key`). Then you can build the SQL SELECT statement with `SelectSQLBuilder::Build` method.
