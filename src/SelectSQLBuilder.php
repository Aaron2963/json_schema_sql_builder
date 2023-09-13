<?php

namespace Lin\JsonSchemaSQLBuilder;

use PDO;
use Lin\JsonSchemaSQLBuilder\Storage;
use Lin\JsonSchemaSQLBuilder\SQLBuilder;

class SelectSQLBuilder extends SQLBuilder
{
    protected PDO $DB;
    protected string $SchemaURI;
    protected array $SelectExpressions = [];
    protected array $MinifiedSelectExpressions = [];
    protected array $WhereConditions = [];
    protected array $OrderByExpressions = [];
    protected array $GroupByExpressions = [];
    protected array $HavingConditions = [];
    protected array $TableReferences = [];
    protected int $Limit = 10;
    protected int $Offset = 0;
    protected string $ArraySeparator = ',';

    public function __construct(string $SchemaURI, ?PDO $DB = null)
    {
        $this->SchemaURI = \trim($SchemaURI, '#');
        if (Storage::GetSchema($SchemaURI) == null) {
            Storage::SetSchemaFromURI($SchemaURI);
        }
        if ($DB != null) {
            $this->DB = $DB;
        }
        parent::__construct(Storage::GetSchema($SchemaURI)['@table']);
    }

    public function GetLimit(): int
    {
        return $this->Limit;
    }

    public function SetLimit(int $Limit): self
    {
        $this->Limit = $Limit;

        return $this;
    }

    public function GetOffset(): int
    {
        return $this->Offset;
    }

    public function SetOffset(int $Offset): self
    {
        $this->Offset = $Offset;

        return $this;
    }

    public function Build($Minify = false): string
    {
        $Result = "";
        if ($Minify) $this->MinifySelectExpressions();
        $Result .= 'SELECT ' . implode(', ', $Minify ? $this->MinifiedSelectExpressions : $this->SelectExpressions) . ' FROM ' . Storage::FilterTableName($this->Table);
        if (\count($this->TableReferences) > 0) {
            $Tables = array_keys($this->TableReferences);
            $Tables = array_map(function ($Table) {
                return Storage::FilterTableName($Table);
            }, $Tables);
            $Result .= ' LEFT JOIN (' . implode(', ', $Tables) . ') ON (' . implode(' AND ', array_values($this->TableReferences)) . ')';
        }
        if (\count($this->WhereConditions) > 0) {
            $Result .= ' WHERE ' . implode(' AND ', $this->WhereConditions);
        }
        if (\count($this->GroupByExpressions) > 0) {
            $Result .= ' GROUP BY ' . implode(', ', $this->GroupByExpressions);
        }
        if (\count($this->HavingConditions) > 0) {
            $Result .= ' HAVING ' . implode(' AND ', $this->HavingConditions);
        }
        if (\count($this->OrderByExpressions) > 0) {
            $Result .= ' ORDER BY ' . implode(', ', array_map(function ($Expression, $Sort) {
                return "$Expression $Sort";
            }, array_keys($this->OrderByExpressions), array_values($this->OrderByExpressions)));
        }
        $Result .= ' LIMIT ' . $this->Limit . ' OFFSET ' . $this->Offset . ';';
        return $Result;
    }

    public function Execute(): array
    {
        $SQL = $this->Build();
        $Statement = $this->DB->prepare($SQL);
        $Statement->execute($this->BindValues);
        if ($Statement->errorCode() !== '00000') {
            throw new \Exception($Statement->errorInfo()[2]);
        }
        $DataArray = $Statement->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($Data) {
            return $this->ConvertDataKeys($Data);
        }, $DataArray);
    }

    public function Count(): int
    {
        $SQL = $this->Build(true);
        $SQL = \preg_replace('/LIMIT \d+ OFFSET \d+/', '', $SQL);
        $SQL = 'SELECT COUNT(*) FROM (' . rtrim($SQL, ';') . ') t;';
        $Statement = $this->DB->prepare($SQL);
        $Statement->execute($this->BindValues);
        $DataArray = $Statement->fetchAll(PDO::FETCH_ASSOC);
        if ($Statement->errorCode() !== '00000') {
            throw new \Exception($Statement->errorInfo()[2]);
        }
        return (int) $DataArray[0]['COUNT(*)'];
    }

    public function SetSelectExpressions(): self
    {
        $Columns = [];
        $Schema = Storage::GetSchema($this->SchemaURI);
        $SchemaURI = \trim($this->SchemaURI, '#');
        $Table = Storage::FilterTableName($Schema['@table']);
        $TableKey = $Schema['@id'];
        $Queue = [["$SchemaURI#" => $Schema]];
        while (\count($Queue) > 0) {
            $Prop = \array_shift($Queue);
            $Key = \array_keys($Prop)[0];
            $Attr = \array_values($Prop)[0];
            if ($Attr['type'] === 'object') {
                foreach ($Attr['properties'] as $SubKey => $SubAttr) {
                    $Queue[] = ["$Key/properties/$SubKey" => $SubAttr];
                }
            } else if ($Attr['type'] === 'array') {
                $ItemTable = '';
                if (array_key_exists('@table', $Attr)) {
                    $ItemTable = Storage::FilterTableName($Attr['@table']);
                    if (array_key_exists('@joinId', $Attr)) {
                        $this->AddJoinOn($ItemTable, "$Table.$TableKey = $ItemTable.{$Attr['@joinId']}");
                    }
                }
                if (array_search($Attr['items']['type'], ['string', 'number', 'integer', 'boolean']) !== false) {
                    $this->AddGroupBy("$Table.$TableKey");
                    $SQLString = Storage::GetSelectExpression("$Key/items");
                    if ($SQLString == null) continue;
                    if (str_starts_with(strtoupper($SQLString), '(SELECT')) {
                        $Columns[] = "$SQLString AS '$Key'";
                        continue;
                    }
                    $OrderBy = '';
                    if (array_key_exists('@orderBy', $Attr)) {
                        $OrderBy = ' ORDER BY ' . $ItemTable . '.' . ($Attr['items']['properties'][$Attr['@orderBy']]['@column'] ?? $Attr['@orderBy']);
                    }
                    $Columns[] = "GROUP_CONCAT($SQLString$OrderBy SEPARATOR '{$this->ArraySeparator}') AS '$Key'";
                } else if ($Attr['items']['type'] === 'object' && !empty($ItemTable)) {
                    $this->AddGroupBy("$Table.$TableKey");
                    // 決定排序參考的鍵
                    $OrderBy = $ItemTable . '.' . array_key_first($Attr['items']['properties']);
                    if (isset($Attr['items']['properties'][$OrderBy]['@column'])) {
                        $OrderBy = $ItemTable . '.' . $Attr['items']['properties'][$OrderBy]['@column'];
                    }
                    if (array_key_exists('@id', $Attr['items'])) {
                        $OrderBy = $ItemTable . '.' . ($Attr['items']['properties'][$Attr['items']['@id']]['@column'] ?? $Attr['items']['@id']);
                    }
                    if (array_key_exists('@orderBy', $Attr)) {
                        $OrderBy = $ItemTable . '.' . ($Attr['items']['properties'][$Attr['@orderBy']]['@column'] ?? $Attr['@orderBy']);
                    }
                    foreach ($Attr['items']['properties'] as $SubKey => $SubAttr) {
                        $SQLString = Storage::GetSelectExpression("$Key/items/properties/$SubKey");
                        $Columns[] = "GROUP_CONCAT($SQLString ORDER BY $OrderBy SEPARATOR '{$this->ArraySeparator}') AS '$Key/items/properties/$SubKey'";
                    }
                } else {
                    continue;
                }
            } else {
                $SQLString = Storage::GetSelectExpression($Key);
                if ($SQLString != null) {
                    $Columns[] = "$SQLString AS '$Key'";
                }
            }
        }
        $this->SelectExpressions = $Columns;
        return $this;
    }

    protected function MinifySelectExpressions(): self
    {
        $this->MinifiedSelectExpressions = \array_map(function ($Expression) {
            $Escaped = \addcslashes($this->SchemaURI . '#/properties/', '\\/');
            $Replaced = \preg_replace('/ AS \'' . $Escaped . '/', ' AS \'', $Expression);
            return \preg_replace('/\/properties\//', '/', $Replaced);
        }, $this->SelectExpressions);
        return $this;
    }

    public function AddSelect(string $SQLExpression, string $Alias): self
    {
        $this->SelectExpressions[] = "$SQLExpression AS '$Alias'";
        return $this;
    }

    public function RemoveSelect(string $Alias): self
    {
        $this->SelectExpressions = \array_filter($this->SelectExpressions, function ($Expression) use ($Alias) {
            return !\str_ends_with($Expression, "AS '$Alias'");
        });
        return $this;
    }

    public function AddWhere(string $Condition, $BindValues = []): self
    {
        $this->WhereConditions[] = $Condition;
        $this->BindValues = \array_merge($this->BindValues, $BindValues);
        return $this;
    }

    public function RemoveWhere(string $Condition): self
    {
        $this->WhereConditions = \array_filter($this->WhereConditions, function ($Expression) use ($Condition) {
            return $Expression !== $Condition;
        });
        return $this;
    }

    public function AddOrderBy(string $Expression, string $Sort = 'ASC'): self
    {
        $Sort = strtoupper($Sort);
        if ($Sort !== 'ASC' && $Sort !== 'DESC') {
            throw new \Exception("Invalid sort order: $Sort");
        }
        $this->OrderByExpressions[$Expression] = $Sort;
        return $this;
    }

    public function RemoveOrderBy(string $Expression): self
    {
        $this->OrderByExpressions = \array_filter($this->OrderByExpressions, function ($Expr) use ($Expression) {
            return $Expr !== $Expression;
        });
        return $this;
    }

    public function AddGroupBy(string $Expression): self
    {
        if (!\in_array($Expression, $this->GroupByExpressions)) {
            $this->GroupByExpressions[] = $Expression;
        }
        return $this;
    }

    public function RemoveGroupBy(string $Expression): self
    {
        $this->GroupByExpressions = \array_filter($this->GroupByExpressions, function ($Expr) use ($Expression) {
            return $Expr !== $Expression;
        });
        return $this;
    }

    public function AddHaving(string $Condition, $BindValues = []): self
    {
        $this->HavingConditions[] = $Condition;
        $this->BindValues = \array_merge($this->BindValues, $BindValues);
        return $this;
    }

    public function RemoveHaving(string $Condition): self
    {
        $this->HavingConditions = \array_filter($this->HavingConditions, function ($Expression) use ($Condition) {
            return $Expression !== $Condition;
        });
        return $this;
    }

    public function AddJoinOn(string $Table, string $Condition): self
    {
        $this->TableReferences[$Table] = $Condition;
        if (count($this->TableReferences) > 1) {
            \trigger_error('Join multiple tables may lead to unexpected result', E_USER_WARNING);
        }
        return $this;
    }

    public function RemoveJoin(string $Table): self
    {
        unset($this->TableReferences[$Table]);
        return $this;
    }

    public function ConvertDataKeys(array $Data): array
    {
        $Schema = Storage::GetSchema($this->SchemaURI);
        $Iterate = function ($Schema, $Key, $Data) use (&$Iterate) {
            if ($Schema['type'] === 'object') {
                $Output = [];
                foreach ($Schema['properties'] as $SubKey => $SubSchema) {
                    $Output[$SubKey] = call_user_func_array($Iterate, [$SubSchema, $Key . '/properties/' . $SubKey, $Data]);
                }
                return $Output;
            } else if ($Schema['type'] === 'array') {
                $Output = [];
                if (array_search($Schema['items']['type'], ['string', 'number', 'integer', 'boolean']) !== false) {
                    $Output = \explode($this->ArraySeparator, $Data[$Key]);
                } else if ($Schema['items']['type'] === 'object') {
                    $Length = \count(\explode($this->ArraySeparator, $Data[$Key . '/items/properties/' . array_key_first($Schema['items']['properties'])]));
                    for ($i = 0; $i < $Length; $i++) {
                        $SubObject = [];
                        foreach ($Schema['items']['properties'] as $SubKey => $SubSchema) {
                            $SubObject[$SubKey] = \explode($this->ArraySeparator, $Data[$Key . '/items/properties/' . $SubKey])[$i];
                        }
                        $Output[] = $SubObject;
                    }
                }
                return $Output;
            } else {
                return $Data[$Key] ?? null;
            }
        };
        $Result = call_user_func_array($Iterate, [$Schema, $this->SchemaURI . '#', $Data]);
        return $Result;
    }
}
