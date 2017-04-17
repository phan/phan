<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Language\Element\Method;

interface MethodAnalyzer {
    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $function
    );
}
