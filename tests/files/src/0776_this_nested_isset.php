<?php
class Beta776
{
    protected $some_acl = array ();
    /**
     * [checkAcl description]
     * @param  array $set_acl array intput
     * @return void
     */
    public function checkAcl(array $set_acl): void
    {
        // $this->some_acl = $set_acl;
        $this->some_acl = array (
            'acl' => array (
                'set' => array (
                    'foo' => 20
                )
            )
        );
        if (isset($this->some_acl['acl']['set'])) {
            echo count($this->some_acl['acl']['set']);
        }
        if (isset($this->some_acl['acl']['other'])) {
            echo count($this->some_acl['acl']['other']);
            if (is_string($this->some_acl['acl']['other'])) {
                echo count($this->some_acl['acl']['other']);
            }
        }
    }
}
