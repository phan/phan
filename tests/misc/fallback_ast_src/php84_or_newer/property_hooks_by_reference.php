<?php
class HooksByReference {
    private array $data = [];
    public array $short { &get => $this->data; }
    public array $block {
        &get {
            return $this->data;
        }
    }
    public array $withSet {
        final &get => $this->data;
        set {
            $this->data = $value;
        }
    }
    public array $multiline {
        &
        get
        =>
        $this->data;
    }
}
