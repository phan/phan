<?php

class C1 {
    use T;
}

class C2 {
    use T;
}

trait T {
    public function handleSelf(self $x) : self
    {
        if (rand() % 2 > 0) {
            return $this;
        }
        return $x;  // should not warn
    }

    /**
     * @param static $x neither return should warn
     */
    public function handleStatic($x) : self
    {
        if (rand() % 2 > 0) {
            return $this;
        }
        return $x;
    }
}
$c1 = new C1();
$c2 = new C2();
var_export($c1->handleSelf($c1));
var_export($c1->handleSelf($c2));  // should warn
var_export($c1->handleStatic($c1));
var_export($c1->handleStatic($c2));  // should warn
