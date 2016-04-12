<?php

$date = new DateTime("2014-06-20 11:45 Europe/London");

$immutable = DateTimeImmutable::createFromMutable($date);
