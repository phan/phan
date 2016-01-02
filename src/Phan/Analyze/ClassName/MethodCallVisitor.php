<?php declare(strict_types=1);
namespace Phan\Analyze\ClassName;

use \Phan\Analyze\ClassNameVisitor;
use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A visitor that can extract a class name from a few
 * types of nodes
 */
class MethodCallVisitor extends ClassElementVisitor {

    /** @var string */
    private $method_name;

    /**
     * @param CodeBase $code_base
     *
     * @param Context $context
     * The context of the current execution
     *
     * @param string|null $method_name
     * The name of hte method we're calling. Knowing this lets
     * us choose the right class if there are many options
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        string $method_name = null
    ) {
        parent::__construct($code_base, $context);
        $this->method_name = $method_name;
    }

    /**
     * @param FQSEN[] $fqsen_list
     * A list of possible FQSENs to return
     *
     * @return FQSEN
     * The most likely correct FQSEN is returned
     */
    protected function chooseSingleFQSEN(array $fqsen_list) : FQSEN {

        // If we have a method_name we're trying to execute on
        // the class,
        if (null !== $this->method_name) {

            // Check each possible class to see if it has that method,
            // and if so, return that
            foreach ($fqsen_list as $fqsen) {
                if ($this->code_base->hasClassWithFQSEN($fqsen)) {
                    $class = $this->code_base->getClassByFQSEN($fqsen);
                    if ($class->hasMethodWithName(
                        $this->code_base,
                        $this->method_name
                    )) {
                        return $fqsen;
                    }
                }
            }
        }

        return array_values($fqsen_list)[0];
    }

}
