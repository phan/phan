<?php
/**
 * @param array<string,int|string> $row
 */
function take_row($row): void {
    var_dump($row);
}

/**
 * @param list<array<string,int|string>> $rows
 */
function take_rows($rows): void {
    var_dump($rows);
}

$row = ['a' => 1, 'b' => '2'];
take_row($row);

$rows = [];
foreach ([1, 2, 3] as $_) {
    $rows[] = $row;
}
take_rows($rows);
