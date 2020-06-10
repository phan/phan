<?php
/**
 * Test
 *
 * Code for testing only.
 *
 * @param string $s
 * @param int $i
 * @param bool $b
 */
function test($s,$i,$b) {
   $done=false;
   while(!$done) {
      $done=true;
      $vardefinedinloop="";
      if($i++<0) {
          $done=false;
      }
      if($b) {
         $vardefinedinloop=$s;
      }
   }

   if($vardefinedinloop !== '') {
       return $s;
   } else {
       return "";
   }
}
