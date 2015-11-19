<?php declare(strict_types=1);
namespace Phan\Persistent;

class Column {

    private $name;
    private $sql_type;
    private $is_primary_key;
    private $is_unique;
    private $is_auto_increment;

    public function __construct(
        string $name,
        string $sql_type,
        bool $is_primary_key = false,
        bool $is_unique = false,
        bool $is_auto_increment = false
    ) {
        $this->name = $name;
        $this->sql_type = $sql_type;
        $this->is_primary_key = $is_primary_key;
        $this->is_unique = $is_unique;
        $this->is_auto_increment = $is_auto_increment;
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
        $string = $this->name;

        if ($this->isAutoIncrement()) {
            $string .= ' AUTOINCREMENT';
        }

        if ($this->isPrimaryKey()) {
            $string .= ' PRIMARY KEY';
        } else if ($this->isUnique()) {
            $string .= ' UNIQUE';
        }

        return $string;
    }

}
