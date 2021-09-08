<?php
class TestSpecific {
   public $m=5;
   public $validList=false;
   /**
    * @return array{s:?int,e:?int}
    */
   public function item() {
      $s=rand(0,$this->m);$e=rand(0,$this->m);
      if($s==0)$s=null;
      if($e==0)$e=null;
      return ['s'=>$s,'e'=>$e];
   }

   /**
    * @return array<array{s:?int,e:?int}>
    */
   public function list() {
      $result=[];
      $l=[0,1,2,3,4,5];  // Array to use foreach to be close to project case
      foreach($l as $_) {
          $result[]=$this->item();
      }
      return $result;
   }


   /**
    * @return array<array{s:?int,e:?int}>
    */
   public function validList() {
      $result=[];
      if($this->validList) {
          $result=$this->list();
      }
      return $result;
   }
}
$t = new TestSpecific();
var_dump($t->validList());
$t->validList=true;
var_dump($t->validList());
