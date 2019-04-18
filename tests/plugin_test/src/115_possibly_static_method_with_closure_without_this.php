<?php

class PSMClazz115 {
    public function examplePublic() {
        (function() {})();
    }

    protected function exampleProtected() {
        (function() {})();
    }

    private function examplePrivate() {
        (function() {})();
    }

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

(new PSMClazz115())->main();
