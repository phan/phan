<?php

class PSMBase112 {
    public function examplePublic() {}

    protected function exampleProtected() {}

    private function examplePrivate() {}

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

class PSMChild112 extends PSMBase112 {
    public function examplePublic() {}

    protected function exampleProtected() {}

    private function examplePrivate() {}

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

(new PSMBase112())->main();
(new PSMChild112())->main();
