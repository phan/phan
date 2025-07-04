<?php

namespace NS994a {
    interface IA {}
    interface IB {}
    interface IC extends IA {}
    interface ID extends IA, IB {}

    class A {}
    class B {}
    class C extends A {}
}

namespace NS994b {
    interface IA {}
    /**
     * @extends IA
     */
    interface IB {}
    /**
     * @extends IA
     */
    interface IC extends IA {}
    /**
     * @extends IA
     * @extends IB
     */
    interface ID extends IA, IB {}

    class A {}
    /**
     * @extends A
     */
    class B {}
    /**
     * @extends A
     */
    class C extends A {}
}
