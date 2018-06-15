<?php

class ParentC {
}

class SubC extends ParentC {
    /** @var parent $parentProp */
    private $parentProp;

    /**
     * @param parent $realParent
     */
    public function setRealParent(parent $realParent) : parent
    {
        $this->parentProp = $realParent;
        return $realParent;
    }

    /**
     * @param ?parent $realParent
     */
    public function setRealParent2(parent $realParent = null)
    {
        var_export($realParent);
    }

    public function setRealParentNoDoc(parent $realParent)
    {
        var_export($realParent);
    }


    /**
     * @param parent[] $realParentArr
     */
    public function setRealParentArr($realParentArr)
    {
        var_export($realParentArr);
    }

    /**
     * @param array{p:parent} $realParentArr
     */
    public function setParentArrShape($realParentArr)
    {
        var_export($realParentArr);
    }
}



$c = new SubC();
$c->setRealParentNoDoc(new ParentC());
$c->setRealParentNoDoc(new SubC());
$c->setRealParentNoDoc(new stdClass());  // should warn
$c->setRealParent(new ParentC());
$c->setRealParent(null);  // should warn
$c->setRealParent2(new ParentC());
$c->setRealParent2(null);  // should allow nullable
$c->setRealParent2(new stdClass());  // should warn
$c->setRealParentArr(new ParentC());
$c->setRealParentArr([new ParentC()]);
$c->setRealParentArr([new SubC()]);
$c->setRealParentArr([new stdClass()]);  // should warn
$c->setParentArrShape([]);  // should warn
$c->setParentArrShape(['p' => new ParentC()]);
$c->setParentArrShape(['p' => new stdClass()]);
