<?php

namespace Lin\JsonSchemaSQLBuilder;

use Lin\JsonSchemaSQLBuilder\Storage;
use Lin\JsonSchemaSQLBuilder\SQLBuilder;

class SelectSQLBuilder extends SQLBuilder
{
    protected string $SchemaURI;
    protected array $SelectExpressions = [];
    protected array $WhereConditions = [];
    protected array $OrderByExpressions = [];
    protected array $GroupByExpressions = [];
    protected array $HavingConditions = [];
    protected int $Limit = 10;
    protected int $Offset = 0;

    public function __construct(string $SchemaURI)
    {
        $this->SchemaURI = $SchemaURI;
        if (Storage::GetSchema($SchemaURI) == null) {
            Storage::SetSchemaFromURI($SchemaURI);
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

    public function Build(): string
    {
        $Result = "";
        $Result .= 'SELECT ' . implode(', ', $this->SelectExpressions) . ' FROM ' . $this->Table;
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

    public function SetSelectExpressions(): self
    {
        $Columns = [];
        $Schema = Storage::GetSchema($this->SchemaURI);
        $$SchemaURI = \trim($this->SchemaURI, '#');
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
                $Queue[] = ["$Key/items" => $Attr['items']];
            } else {
                $SQLString = Storage::GetSelectExpression($Key);
                if ($SQLString !== null) {
                    $Columns[] = "$SQLString as $Key";
                }
            }
        }
        $this->SelectExpressions = $Columns;
        return $this;
    }

    public function AddSelect(string $SQLExpression, string $Alias): self
    {
        $this->SelectExpressions[] = "$SQLExpression as $Alias";
        return $this;
    }

    public function RemoveSelect(string $Alias): self
    {
        $this->SelectExpressions = \array_filter($this->SelectExpressions, function ($Expression) use ($Alias) {
            return !\str_ends_with($Expression, "as $Alias");
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
        $this->GroupByExpressions[] = $Expression;
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

    public function ConvertDataKeys(array $Data): array
    {
        //TODO: Implement
        throw new \Exception("Not implemented");
    }
}
