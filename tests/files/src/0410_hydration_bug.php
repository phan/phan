<?php
class HydrateExample
{
    protected $messageTemplates = [
        self::INVALID      => "Invalid",
    ];
}

class HydrateExampleStep extends HydrateExample
{
    protected $messageTemplates = [
        self::INVALID      => "Invalid",
    ];

    protected $baseValue = 'a value';
}
