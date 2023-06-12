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
    protected array $RowAssignments = [];

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
        $SortByRows = [];
        foreach ($this->RowAssignments as $Assignment) {
            $SortByRows[$Assignment['Table']][$Assignment['Index']][] = $Assignment;
        }
        foreach ($SortByRows as $Table => $Rows) {
            foreach ($Rows as $Row) {
                $SQLString = "INSERT INTO $Table SET ";
                foreach ($Row as $Assignment) {
                    $SQLString .= "{$Assignment['Column']} = {$Assignment['Value']}, ";
                }
                $SQLString = substr($SQLString, 0, -2);
                $SQLStrings[] = $SQLString;
            }
        }
        return implode(";", $SQLStrings) . ';';
    }

    public function Execute(): int
    {
        $RowCount = 0;
        $this->SetAssignmentList();
        $SQLStrings = $this->Build();
        $SQLStrings = explode(";", $SQLStrings);
        foreach ($SQLStrings as $SQL) {
            if (empty(trim($SQL))) continue;
            $SQL .= ';';
            $Binds = [];
            $i = 0;
            foreach ($this->BindValues as $Key => $Value) {
                $EncodedKey = "var$i";
                $OriSQL = $SQL;
                $SQL = str_replace(":$Key,", ":$EncodedKey,", $SQL);
                $SQL = str_replace(":$Key;", ":$EncodedKey;", $SQL);
                if ($SQL === $OriSQL) {
                    continue;
                }
                $Binds["$EncodedKey"] = $Value;
                $i++;
            }
            $Stmt = $this->DB->prepare($SQL);
            foreach ($Binds as $Key => $Value) {
                $Stmt->bindValue(":$Key", $Value);
            }
            $Result = $Stmt->execute();
            if (!$Result) {
                print_r($Stmt->errorInfo());
                echo "\n";
                echo $SQL . "\n";
                throw new \Exception("SQL Error: $SQL");
            }
            $RowCount += $Stmt->rowCount();
        }
        return $RowCount;
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
                $i = 0;
                while ($i >= 0) {
                    $HasMatch = false;
                    foreach (array_keys($this->BindValues) as $ArrayKey) {
                        if (preg_match("/^" . addcslashes($Key, '/') . "\/items\/$i/", $ArrayKey, $Matches)) {
                            $HasMatch = true;
                            if (substr_compare($ArrayKey, "/items/$i", -strlen("/items/$i")) === 0) {
                                $Queue[] = [$ArrayKey => $Attr['items']];
                            } else {
                                $Queue[] = [$ArrayKey => $Attr['items']['properties']];
                            }
                        }
                    }
                    if ($HasMatch) {
                        if (isset($Attr['@joinId'])) {
                            $Assignments[] = [
                                'Table' => $Attr['@table'],
                                'Index' => $i,
                                'Column' => $Attr['@joinId'],
                                'Value' => ":$SchemaURI#/properties/{$Schema['@id']}"
                            ];
                        }
                        $i++;
                    } else {
                        $i = -1;
                    }
                }
            } else {
                preg_match('/\/items\/(\d+)\//', $Key, $ItemIndex);
                if (isset($ItemIndex[1])) {
                    $ItemIndex = $ItemIndex[1];
                    $ColumnKey = preg_replace('/\/items\/\d+\//', '/items/', $Key);
                } else {
                    $ItemIndex = 0;
                    $ColumnKey = $Key;
                }
                $Column = Storage::GetSelectExpression($ColumnKey);
                $Table = explode('.', $Column)[0];
                $Assignments[] = [
                    'Table' => $Table,
                    'Index' => $ItemIndex,
                    'Column' => $Column,
                    'Value' => ":$Key"
                ];
            }
        }
        $this->RowAssignments = $Assignments;
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
        $Iterate = function ($Key, $Value, $ParentPath) use (&$Iterate) {
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
    }
}
