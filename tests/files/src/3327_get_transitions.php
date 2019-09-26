<?php
declare(strict_types=1);

$comparison = (new DateTimeZone('UTC'))->getTransitions() === false;
