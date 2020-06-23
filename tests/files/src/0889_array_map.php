<?php
/**
 * @param array{name:string,id:int}[] $names
 * @return array{newName:string,newId:int}[]
 */
function transformNames($names) {

    $x= array_map(
        function($names) {
            '@phan-debug-var $names';
            return [
                'newName' => $names['id'],
                'newId' => $names['name']
            ];
        },
        $names
    );
    '@phan-debug-var $x';
    return $x;

}

transformNames([
    ['name' => 'Louis', 'id' => 1],
    ['name' => 'Mike', 'id' => 2]
]);
