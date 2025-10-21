<?php
class HasTypedProperties {
    public static ?int $intProp = null;
    private stdClass $s;
    protected static \foo\bar $s;
    public namespace\stdClass $s;
}
