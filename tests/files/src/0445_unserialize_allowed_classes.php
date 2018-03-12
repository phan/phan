<?php

$serialized = serialize(new stdClass());
unserialize($serialized, []);
unserialize($serialized, ['allowed_classes' => [['stdClass']]]);
unserialize($serialized, ['allowed_classes' => ['stdClass']]);
