<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Language\AST\Element;
use \Phan\Language\AST\Visitor\ClassNameKindVisitor;
use \Phan\Language\AST\Visitor\ClassNameValidationKindVisitor;

trait AST {

    /**
     * @return
     * The class name associated with nodes of various types
     */
    protected function classNameFromNode($node) : string {

        // Extract the class name
        $class_name = (new Element($node))->acceptKindVisitor(
            new ClassNameKindVisitor();
        );

        if (!$class_name) {
            return '';
        }

        // Validate that the class name is correct
        (new Element($node))->acceptKindVisitor(
            new ClassNameValidationVisitor()
        );

        return $class_name;
    }

}
