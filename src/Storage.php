<?php

namespace Lin\JsonSchemaSQLBuilder;

class Storage
{
    static protected $Indexes = [];
    static protected $SelectExpressions = [];

    static public function GetSchema($URI): ?array
    {
        return self::$Indexes[$URI];
    }

    static public function SetSchema(string $URI, array $Schema)
    {
        $URI = \trim($URI, '#');
        self::$Indexes[$URI] = $Schema;
        $Queue = [["$URI#" => $Schema]];
        while (count($Queue) > 0) {
            $Prop = \array_shift($Queue);
            $Key = \array_keys($Prop)[0];
            $Attr = \array_values($Prop)[0];
            if ($Attr['type'] === 'object') {
                foreach ($Attr['properties'] as $SubKey => $SubAttr) {
                    $Queue[] = ["$Key/properties/$SubKey" => $SubAttr];
                }
            }
            if ($Attr['type'] === 'array') {
                $Queue[] = ["$Key/items" => $Attr['items']];
            }
            if (isset($Attr['$id'])) {
                self::$Indexes[$Attr['$id']] = $Attr;
            }
            self::$Indexes[$Key] = $Attr;
        }
    }

    static public function SetSchemaFromURI(string $URI)
    {
        $URI = \trim($URI, '#');
        $Schema = file_get_contents($URI);
        $Schema = json_decode($Schema, true);
        if ($Schema === false) {
            throw new \Exception("Invalid JSON: $URI");
        }
        self::SetSchema($URI, $Schema);
    }

    static public function AddSelectExpression(string $URI, ?string $SQLString)
    {
        $URI = str_replace('##/', '#/', $URI);
        self::$SelectExpressions[$URI] = $SQLString;
    }

    static public function GetSelectExpression(string $URI): ?string
    {
        $Schema = self::GetSchema($URI);
        if (!isset($Schema)) {
            throw new \Exception("Schema not found: $URI");
        }
        if (array_key_exists($URI, self::$SelectExpressions)) {
            if (self::$SelectExpressions[$URI] == null) {
                return null;
            }
            return self::$SelectExpressions[$URI];
        }
        $Table = '';
        $ParentURI = $URI;
        while (empty($Table) && preg_match('/\/properties\/[^\/]+$/', $ParentURI) || preg_match('/\/items$/', $ParentURI)) {
            $ParentURI = preg_replace('/\/properties\/[^\/]+$/', '', $ParentURI);
            $ParentSchema = self::GetSchema($ParentURI);
            if (isset($ParentSchema['@table'])) {
                $Table = explode(':', $ParentSchema['@table'])[0];
            } else if (preg_match('/\/items$/', $ParentURI)) {
                $ParentURI = preg_replace('/\/items$/', '', $ParentURI);
                $ParentSchema = self::GetSchema($ParentURI);
                if (isset($ParentSchema['@table'])) {
                    $Table = explode(':', $ParentSchema['@table'])[0];
                }
            }
        }
        if (isset($Schema['@column'])) {
            $Key = $Schema['@column'];
        } else {
            $Keys = explode('/', $URI);
            $Key = array_pop($Keys);
        }
        return "$Table.$Key";
    }

    static public function HasSelectExpression(string $URI): bool
    {
        return array_key_exists($URI, self::$SelectExpressions);
    }

    static public function DumpSelectExpressions()
    {
        var_dump(self::$SelectExpressions);
    }

    static public function DumpIndexes()
    {
        echo json_encode(self::$Indexes, JSON_PRETTY_PRINT);
    }
}
