<?php

class PSMBase106 {
    public function examplePublic() {}

    protected function exampleProtected() {}

    private function examplePrivate() {}

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

class PSMChild106 extends PSMBase106 {
    public function examplePublic() {}

    protected function exampleProtected() {}

    private function examplePrivate() {}

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

(new PSMBase106())->main();
(new PSMChild106())->main();
