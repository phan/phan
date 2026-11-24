<?php
class PropertyMagicConstant {
    public string $name {
        get => __PROPERTY__;
        set {
            echo __PROPERTY__,
                __property__;
        }
    }
    public string $other {
        get
        =>
        __PROPERTY__;
    }
}
