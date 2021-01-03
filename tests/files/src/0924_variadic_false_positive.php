<?php
class LibClass {
    protected $options;
    /**
     * @return static The object itself
     */
    public function funcVarArgs() {
        if(func_num_args()>0) {
            $this->options=func_get_args();
        }
        return $this;
   }
}

/**
 * @method ProjectClass funcVarArgs(...$args)
 */
class ProjectClass extends LibClass {
    public function testMe() {
        $this->funcVarArgs("abc")->dumpOptions();
    }

    public function dumpOptions() {
        print "I am ".get_class($this)." with options:\n";
        var_dump($this->options);
    }
}
