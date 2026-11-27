<?php

namespace NS6005;

// What was inferred about a property of $this is widened by a call of a method of $this that assigns the property
// (also through the methods that it calls, and as parent::method()), and is kept by a call of a method that does not.

class Conn {
    public function query(string $s): void { echo $s; }
}

abstract class Base {
    /** @var ?Conn */
    protected $connection = null;
    /** @var ?Conn */
    protected $other = null;

    protected function connect(): void {
        $this->connection = new Conn();
    }

    abstract protected function load(): void;
}

class Adapter extends Base {
    /** @var ?int */
    private $count = null;
    private ?string $name = null;

    public function afterParentCall(): void {
        if ($this->connection) {
            return;
        }
        parent::connect();
        $this->connection->query('x');
    }

    public function afterIndirectCall(): Conn {
        if ($this->connection) {
            return $this->connection;
        }
        $this->connectIndirectly();
        return $this->connection;
    }

    public function inBranch(): int {
        if ($this->count === null) {
            $this->init();
            return $this->count;
        }
        return $this->count;
    }

    public function afterAbstractMethod(): Conn {
        if ($this->other === null) {
            $this->load();
        }
        return $this->other;
    }

    public function afterUnrelatedMethod(): Conn {
        if ($this->other === null) {
            $this->init();
            // Still null: init() does not assign $other, and is private.
            return $this->other;
        }
        return $this->other;
    }

    public function afterMethodThatCanBeOverridden(): Conn {
        if ($this->other === null) {
            $this->hook();
            // Not known to be null: a class that extends this one can assign $other in hook().
            return $this->other;
        }
        return $this->other;
    }

    protected function hook(): void {
    }

    public function afterReset(): int {
        if ($this->name !== null && $this->connection) {
            $this->reset();
            // $name may be null again, $connection is kept.
            $this->connection->query('y');
            return strlen($this->name);
        }
        return 0;
    }

    public function afterStaticMethod(): Conn {
        if ($this->other === null) {
            self::helper();
            return $this->other;
        }
        return $this->other;
    }

    protected function load(): void {
        $this->other = new Conn();
    }

    private function connectIndirectly(): void {
        $this->connect();
    }

    private function init(): void {
        $this->count = 1;
    }

    private function reset(): void {
        $this->name = null;
    }

    private static function helper(): void {
    }
}
