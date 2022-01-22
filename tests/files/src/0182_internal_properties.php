<?php
$interval = new DateInterval('P2Y4DT6H8M');
echo $interval->y;

$period = new DatePeriod('R5/2008-03-01T13:00:00Z/P1Y2M10DT2H30M');
var_dump($period->start);
var_dump($period->current);
var_dump($period->end);
var_dump($period->interval);
var_dump($period->recurrences);
var_dump($period->include_start_date);
