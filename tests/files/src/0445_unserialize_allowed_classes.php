<?php

$serialized = serialize(new stdClass());
unserialize($serialized, []);
unserialize($serialized, ['allowed_classes' => [['stdClass']]]);  // TODO: Decide on specifics of when TypeMismatchArgumentInternal vs InternalReal will be emitted
unserialize($serialized, ['allowed_classes' => ['stdClass']]);
