<?php

$r = new ResourceBundle('en', 'ICUDATA');
$r->count();
$r->getErrorCode();
$r->getErrorMessage();
$r->get(0);
ResourceBundle::getLocales('');

resourcebundle_get($r, 0);
resourcebundle_locales('');
