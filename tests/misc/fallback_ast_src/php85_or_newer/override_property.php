<?php

class OverrideBaseProperty {
    protected string $value = 'base';
}

class OverrideChildProperty extends OverrideBaseProperty {
    #[Override]
    protected string $value = 'child';
}
