<?php

function a(DateTime $start, DateTime $end) {
  $interval = DateInterval::createFromDateString('1 day');
  $period = new DatePeriod($start, $interval, 1);
  $period = new DatePeriod($start, $interval, $end);
  $period = new DatePeriod('P1D');
}