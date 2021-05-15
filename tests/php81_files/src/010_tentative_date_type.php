<?php
declare(strict_types=1);
$iso = 'R4/2012-07-01T00:00:00Z/P7D';
$dateTime = new DatePeriod($iso);
var_export($dateTime);
// Should emit PhanTypeMismatchReal due to DatePeriod->getStartDate having a tentative return type of DateTimeInterface in php 8.1
echo strlen($dateTime->getStartDate());
