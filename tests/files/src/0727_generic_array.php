<?php declare(strict_types=1);

/**
 * @template T
 */
class Base {

    /**
     * @var T
     */
    private $item;

    /**
     * @param T $item
     */
    public function __construct($item) {
        $this->item = $item;
    }

    /**
     * @return T[]
     */
    public function getArray() {
        return [$this->item, $this->item];
    }

    /**
     * @return T
     */
    public function getOne() {
        return $this->item;
    }
}

/**
 * @inherits Base<string>
 */
class ItemBase extends Base {
}

class Main {

    public function run() {
        $base = new ItemBase("asd");

        $this->one($base->getOne());
        $this->two($base->getArray());
        $this->one($base->getArray());
    }

    /**
     * @param string $item
     */
    private function one($item) {
        echo $item;
    }

    /**
     * @param string[] $items
     */
    private function two($items) {
        foreach ($items as $item) {
            echo $item;
        }
    }
}

$m = new Main();
$m->run();
