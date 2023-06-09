<?php

namespace Lin\JsonSchemaSQLBuilder;

use PDO;
use Lin\JsonSchemaSQLBuilder\Storage;
use Lin\JsonSchemaSQLBuilder\SQLBuilder;

class InsertSQLBuilder extends SQLBuilder
{
    protected PDO $DB;
    protected string $SchemaURI;
    protected string $Table;
    protected array $Data;
    protected array $AssignmentArray = [];

    public function __construct(string $SchemaURI, PDO $DB, $Data)
    {
        $this->SchemaURI = \trim($SchemaURI, '#');
        if (Storage::GetSchema($SchemaURI) == null) {
            Storage::SetSchemaFromURI($SchemaURI);
        }
        if ($DB != null) {
            $this->DB = $DB;
        }
        $this->Data = $Data;
        parent::__construct(Storage::GetSchema($SchemaURI)['@table']);
        $this->BindData();
    }

    public function Build(): string
    {
        $SQLStrings = [];
        $SortByTables = [];
        foreach ($this->AssignmentArray as $Column => $Value) {
            $SortByTables[explode('.', $Column)[0]][$Column] = $Value;
        }
        foreach ($SortByTables as $Table => $Assignments) {
            $AssignmentList = array_map(function ($Key, $Value) {
                return "$Key = $Value";
            }, array_keys($Assignments), $Assignments);
            $SQLString = "INSERT INTO $Table SET " . implode(', ', $AssignmentList);
            $SQLStrings[] = $SQLString;
        }
        return implode(";\n", $SQLStrings) . ';';
    }

    public function SetAssignmentList(): self
    {
        $Assignments = [];
        $Schema = Storage::GetSchema($this->SchemaURI);
        $SchemaURI = \trim($this->SchemaURI, '#');
        $Queue = [[$SchemaURI . '#' => $Schema]];
        while (\count($Queue) > 0) {
            $Prop = \array_shift($Queue);
            $Key = \array_keys($Prop)[0];
            $Attr = \array_values($Prop)[0];
            if ($Attr['readonly'] == true) {
                continue;
            }
            if ($Attr['type'] === 'object') {
                foreach ($Attr['properties'] as $SubKey => $SubAttr) {
                    $Queue[] = ["$Key/properties/$SubKey" => $SubAttr];
                }
            } else if ($Attr['type'] === 'array') {
                // TODO
                // $Queue[] = ["$Key/items" => $Attr['items']];
            } else {
                $Column = Storage::GetSelectExpression($Key);
                $Assignments[$Column] = ":$Key";
            }
        }
        $this->AssignmentArray = $Assignments;
        return $this;
    }

    public function Assign(string $Key, string $Value, bool $Quote = true): self
    {
        if ($Quote) {
            $Value = $this->DB->quote($Value);
        }
        $this->AssignmentArray[$Key] = $Value;
        return $this;
    }

    protected function BindData()
    {
        $Iterate = function($Key, $Value, $ParentPath) use (&$Iterate) {
            if (is_array($Value)) {
                if (array_keys($Value) === range(0, count($Value) - 1)) {
                    foreach ($Value as $i => $SubValue) {
                        call_user_func_array($Iterate, ["$Key/items/$i", $SubValue, $ParentPath]);
                    }
                } else {
                    foreach ($Value as $SubKey => $SubValue) {
                        call_user_func_array($Iterate, [$SubKey, $SubValue, "$ParentPath/properties/$Key"]);
                    }
                }
            } else {
                $this->BindValues["$ParentPath/properties/$Key"] = $Value;
            }
        };
        foreach ($this->Data as $Key => $Value) {
            call_user_func_array($Iterate, [$Key, $Value, $this->SchemaURI . '#']);
        }
        // echo json_encode($this->BindValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
