<?php

declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment\CustomAnnotation;

/**
 * CustomAnnotationCapability lets a plugin declare annotations of its own in doc comments, such as `(at)phan-my-assert $x`.
 *
 * Phan does not emit PhanMisspelledAnnotation for a declared annotation, lists it in the output of `phan --help-annotations`,
 * calls handleCustomAnnotation() for each occurrence in the parse phase, and keeps the occurrences for the element
 * (AddressableElementInterface::getCustomAnnotations()).
 *
 * The names are matched case-insensitively, like the annotations of Phan itself.
 * Only annotations at the start of a line of a doc comment are recognized, and the inline form `{(at)phan-name ...}`
 * after another tag at the start of the line (as for the annotations of Phan itself). See CustomAnnotation for the text.
 * Doc comments are only read when the config setting `read_type_annotations` is enabled.
 */
interface CustomAnnotationCapability
{
    /**
     * @return array<string,string> maps the name of each annotation that this plugin declares, in lowercase and without `@`
     * (e.g. `'phan-my-assert'`), to a description of one line for `phan --help-annotations` (may be empty).
     *
     * A name must match `/^phan-[a-z0-9_-]+$/` and must not be the name of an annotation of Phan itself (e.g. `phan-param`),
     * or Phan fails to load the plugins. Several plugins may declare the same name: each of them is called.
     * This is called once, when Phan loads the plugins.
     */
    public function getCustomAnnotations(): array;

    /**
     * Called in the parse phase for each occurrence of an annotation that this plugin declared,
     * after the element that the doc comment belongs to was created and added to the code base.
     * A plugin is only called for the names it declared. The occurrences in a doc comment are handled in the order
     * of the comment, and the plugins that declared the same name are called in the order they are loaded.
     * An exception thrown here ends the parsing of the file, as for any error in the parse phase.
     *
     * $element is a Clazz (class, interface, trait or enum), Method, Property (also a property promoted by a constructor),
     * ClassConstant (also an EnumCase), GlobalConstant (declared with `const`) or Func (function, closure or arrow function).
     * Phan does not check the kind of element an annotation is on: check the class of $element if that matters.
     *
     * The element is in the state of the parse phase: it is not hydrated, the elements declared in the files parsed
     * after this one do not exist yet, and a class has no inherited members. When this is called for a class,
     * its members are not parsed yet. $element->getCustomAnnotations($code_base) already returns the annotations
     * of its doc comment.
     *
     * This is called for the declaration only, not for the copies of a member that subclasses inherit or that classes
     * import from a trait (their getCustomAnnotations() returns the annotations of the declaration).
     * A doc comment shared by several properties (`public $a, $b;`) calls this for each of them.
     * It is called for the files excluded from analysis too, but the issues emitted for those files are not reported.
     * In daemon mode and in the language server, a file that changed is parsed again, and this is called again
     * for the annotations of the new elements of that file.
     *
     * For the (at)suppress annotations of the element to apply to an issue emitted here, emit it with
     * `$element->getInternalContext()` as the context for a Clazz, and with `new ElementContext($element)` for the other
     * elements, e.g. `(new ElementContext($element))->withLineNumberStart($annotation->getLineno())`.
     * (Phan does not record the (at)suppress annotations of a global constant.)
     */
    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void;
}
