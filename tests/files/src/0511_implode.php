<?php

echo implode();
echo implode('literal string');
echo implode(['hello', 'world']);
echo implode([2, 3]);
echo implode(['hello', 'world'], ' ');
echo implode(['hello', 'world'], 2.2);
echo implode(' ', [new stdClass()]);
echo implode([new stdClass()], ' ');
echo implode(' ', ['hello', 'world']);
echo implode(' ', [new stdClass()]);
echo implode(['hello'], ['hello', 'world']);
echo implode(' ', ['hello', 'world'], 'extra');
echo strlen([]);
