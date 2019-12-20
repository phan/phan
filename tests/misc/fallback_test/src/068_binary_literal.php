<?php

echo spl_object_hash(0b01010);
echo spl_object_hash(0B11111);
echo spl_object_hash(0b11111111111111111111111111111111111111111111111111111111111111111111);
echo spl_object_hash(0xaaaaaaaaaaaaaaaaa);
echo spl_object_hash(0712377777777777777777777777777777777);
echo spl_object_hash(0.1e1);
echo spl_object_hash(0.1E2);
echo spl_object_hash(3E2);
echo spl_object_hash(03E2);
{
