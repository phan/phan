<?php
/**
 * @template T0
 * @template T1
 * @template T2
 */
class ManyParams {
    /** @var T0 */
    public $t0;
    /** @var T1 */
    public $t1;
    /** @var T2 */
    public $t2;
    /**
     * @param T0 $t0
     * @param T1 $t1
     * @param T2 $t2
     */
    function __construct($t0, $t1, $t2) {
        $this->t0 = $t0;
        $this->t1 = $t1;
        $this->t2 = $t2;
    }
}
/**
 * @template U1
 * @template U2
 * @extends ManyParams<int, U1, U2>
 */
class LessParams extends ManyParams {
    /**
     * @param U1 $u1
     * @param U2 $u2
     */
    function __construct($u1, $u2) {
        parent::__construct(0, $u1, $u2);
    }
}
/**
 * @template V2
 * @extends LessParams<int, V2>
 */
class LessLessParams extends LessParams {
    /**
     * @param V2 $v2
     */
    function __construct($v2) {
        parent::__construct(1, $v2);
    }
}
/**
 * @extends LessLessParams<int>
 */
class LessLessLessParams extends LessLessParams {
    function __construct() {
        parent::__construct(2);
    }
}

class NoneParams {}
/**
 * @template T0
 */
class MoreParams extends NoneParams {
    /** @var T0 */
    public $t0;
    /**
     * @param T0 $t0
     */
    function __construct($t0) {
        $this->t0 = $t0;
    }
}
/**
 * @template U0
 * @template U1
 * @extends MoreParams<U0>
 */
class MoreMoreParams extends MoreParams {
    /** @var U1 */
    public $u1;
    /**
     * @param U0 $u0
     * @param U1 $u1
     */
    function __construct($u0, $u1) {
        parent::__construct($u0);
        $this->u1 = $u1;
    }
}
/**
 * @template V0
 * @template V1
 * @template V2
 * @extends MoreMoreParams<V0, V1>
 */
class MoreMoreMoreParams extends MoreMoreParams {
    /** @var V2 */
    public $v2;
    /**
     * @param V0 $v0
     * @param V1 $v1
     * @param V2 $v2
     */
    function __construct($v0, $v1, $v2) {
        parent::__construct($v0, $v1);
        $this->v2 = $v2;
    }
}


/** @param ManyParams<int,int,int> $x */
function acceptManyParams($x) { var_dump($x); }
/** @param LessParams<int,int> $x */
function acceptLessParams($x) { var_dump($x); }
/** @param LessLessParams<int> $x */
function acceptLessLessParams($x) { var_dump($x); }
/** @param LessLessLessParams $x */
function acceptLessLessLessParams($x) { var_dump($x); }
/** @param NoneParams $x */
function acceptNoneParams($x) { var_dump($x); }
/** @param MoreParams<int> $x */
function acceptMoreParams($x) { var_dump($x); }
/** @param MoreMoreParams<int,int> $x */
function acceptMoreMoreParams($x) { var_dump($x); }
/** @param MoreMoreMoreParams<int,int,int> $x */
function acceptMoreMoreMoreParams($x) { var_dump($x); }


const X = 'X';


acceptManyParams(        new ManyParams        (1, 2, 3)); // OK
acceptManyParams(        new LessParams        (2, 3)   ); // OK
acceptManyParams(        new LessLessParams    (3)      ); // OK
acceptManyParams(        new LessLessLessParams()       ); // OK

acceptLessParams(        new ManyParams        (1, 2, 3)); // Err
acceptLessParams(        new LessParams        (2, 3)   ); // OK
acceptLessParams(        new LessLessParams    (3)      ); // OK
acceptLessParams(        new LessLessLessParams()       ); // OK

acceptLessLessParams(    new ManyParams        (1, 2, 3)); // Err
acceptLessLessParams(    new LessParams        (2, 3)   ); // Err
acceptLessLessParams(    new LessLessParams    (3)      ); // OK
acceptLessLessParams(    new LessLessLessParams()       ); // OK

acceptLessLessLessParams(new ManyParams        (1, 2, 3)); // Err
acceptLessLessLessParams(new LessParams        (2, 3)   ); // Err
acceptLessLessLessParams(new LessLessParams    (3)      ); // Err
acceptLessLessLessParams(new LessLessLessParams()       ); // OK

acceptManyParams(        new ManyParams        (X, 2, 3)); // Err
acceptManyParams(        new ManyParams        (1, X, 3)); // Err
acceptManyParams(        new ManyParams        (1, 2, X)); // Err
acceptManyParams(        new LessParams        (X, 3)   ); // Err
acceptManyParams(        new LessParams        (2, X)   ); // Err
acceptManyParams(        new LessLessParams    (X)      ); // Err

acceptLessParams(        new LessParams        (X, 3)   ); // Err
acceptLessParams(        new LessParams        (2, X)   ); // Err
acceptLessParams(        new LessLessParams    (X)      ); // Err

acceptLessLessParams(    new LessLessParams    (X)      ); // Err


acceptNoneParams(        new NoneParams        ()       ); // OK
acceptNoneParams(        new MoreParams        (1)      ); // OK
acceptNoneParams(        new MoreMoreParams    (1, 2)   ); // OK
acceptNoneParams(        new MoreMoreMoreParams(1, 2, 3)); // OK

acceptMoreParams(        new NoneParams        ()       ); // Err
acceptMoreParams(        new MoreParams        (1)      ); // OK
acceptMoreParams(        new MoreMoreParams    (1, 2)   ); // OK
acceptMoreParams(        new MoreMoreMoreParams(1, 2, 3)); // OK

acceptMoreMoreParams    (new NoneParams        ()       ); // Err
acceptMoreMoreParams    (new MoreParams        (1)      ); // Err
acceptMoreMoreParams    (new MoreMoreParams    (1, 2)   ); // OK
acceptMoreMoreParams    (new MoreMoreMoreParams(1, 2, 3)); // OK

acceptMoreMoreMoreParams(new NoneParams        ()       ); // Err
acceptMoreMoreMoreParams(new MoreParams        (1)      ); // Err
acceptMoreMoreMoreParams(new MoreMoreParams    (1, 2)   ); // Err
acceptMoreMoreMoreParams(new MoreMoreMoreParams(1, 2, 3)); // OK

acceptMoreParams(        new MoreParams        (X)      ); // Err
acceptMoreParams(        new MoreMoreParams    (X, 2)   ); // Err
acceptMoreParams(        new MoreMoreMoreParams(X, 2, 3)); // Err

acceptMoreMoreParams    (new MoreMoreParams    (X, 2)   ); // Err
acceptMoreMoreParams    (new MoreMoreMoreParams(X, 2, 3)); // Err

acceptMoreMoreMoreParams(new MoreMoreMoreParams(X, 2, 3)); // Err
