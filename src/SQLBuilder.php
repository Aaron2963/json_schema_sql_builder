<?php

namespace Lin\JsonSchemaSQLBuilder;

abstract class SQLBuilder
{
    protected string $Table;
    protected array $BindValues = [];

    public abstract function Build(): string;
    public abstract function Execute();

    public function __construct(string $Table)
    {
        $this->Table = $Table;
    }

    public function GetBindValues(): array
    {
        return $this->BindValues;
    }

    public function GetTable(): string
    {
        return $this->Table;
    }

    public function SetTable($Table): self
    {
        $this->Table = $Table;

        return $this;
    }
}
