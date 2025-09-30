<?php
/**
 * Add support for deep-cloning of readonly properties <https://wiki.php.net/rfc/readonly_amendments>
 * @phan-file-suppress PhanUnreferencedPublicProperty, PhanUndeclaredFunction
 */
class C0 {
    public string $version = '8.2';
}
readonly class C1 {
    public function __construct(
        public C0 $php
    ) {}
    public function __clone(): void {
        $this->php = clone $this->php;
    }
}
class UnrelatedClass {
    public function __clone() {
        $c1 = new C1(new C0());
        $c1->php = new C0();
    }
}
$instance = new C1(new C0());
$cloned = clone $instance;
echo $cloned->php->version;
$cloned->php->version = '8.3';
echo $cloned->php->version;
$instance2 = new UnrelatedClass();
