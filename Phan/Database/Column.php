<?php declare(strict_types=1);
namespace Phan\Database;

class Column {

    const TYPE_STRING = 'STRING';
    const TYPE_INT = 'INTEGER';
    const TYPE_BOOL = 'BOOL';

    private $name;
    private $sql_type;
    private $is_primary_key;
    private $is_auto_increment;
    private $is_unique;

    public function __construct(
        string $name,
        string $sql_type,
        bool $is_primary_key = false,
        bool $is_auto_increment = false,
        bool $is_unique = false
    ) {
        $this->name = $name;
        $this->sql_type = $sql_type;
        $this->is_primary_key = $is_primary_key;
        $this->is_auto_increment = $is_auto_increment;
        $this->is_unique = $is_unique;
    }

    public function name() : string {
        return $this->name;
    }

    public function sqlType() : string {
        return $this->sql_type;
    }

    public function isAutoIncrement() : bool {
        return $this->is_auto_increment;
    }

    public function isPrimaryKey() : bool {
        return $this->is_primary_key;
    }

    public function isUnique() : bool {
        return $this->is_unique;
    }

    public function __toString() : string {
        $string = $this->name();

        $string .= " {$this->sqlType()}";

        if ($this->isUnique()) {
            $string .= ' UNIQUE';
        }

        if ($this->isAutoIncrement()) {
            $string .= ' AUTOINCREMENT';
        }

        return $string;
    }

}
