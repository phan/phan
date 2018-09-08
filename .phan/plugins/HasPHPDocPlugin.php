<?php declare(strict_types=1);

use Phan\CodeBase;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\MarkupDescription;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeClassCapability;

/**
 * This file checks if a class has a PHPDoc comment,
 * and that Phan can generate a hover description for that comment.
 *
 * It hooks into this event:
 *
 * - analyzeClass
 *   Once all classes are parsed, this method will be called
 *   on every method in the code base
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV2
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class HasPHPDocPlugin extends PluginV2 implements
    AnalyzeClassCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        $doc_comment = $class->getDocComment();
        if (!$doc_comment) {
            $this->emitIssue(
                $code_base,
                $class->getContext(),
                'PhanPluginNoCommentOnClass',
                'Class {CLASS} has no doc comment',
                [$class->getFQSEN()]
            );
            return;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $description = MarkupDescription::extractDescriptionFromDocComment($class);
        if (!$description) {
            $this->emitIssue(
                $code_base,
                $class->getContext(),
                'PhanPluginDescriptionlessCommentOnClass',
                'Class {CLASS} has no readable description: {STRING_LITERAL}',
                [$class->getFQSEN(), json_encode($class->getDocComment(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
            );
            return;
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new HasPHPDocPlugin();
