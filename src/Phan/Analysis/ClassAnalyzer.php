<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Language\Element\Clazz;

interface ClassAnalyzer {
    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    );
}
