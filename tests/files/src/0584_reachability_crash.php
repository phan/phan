<?php
interface SomeInterface
{
    public function removed();
}

class Test2206 {
    private function removeData(bool $runAgain = true): bool
    {
        $responder = new class() implements \SomeInterface
        {
            public $removed = false;

            public function removed()
            {
                $this->removed = true;
            }
        };

        $dataRemoved = $responder->removed;

        if ($runAgain) {
            $dataRemoved = $dataRemoved || $this->removeData(false);
        }

        return $dataRemoved;
    }
}
