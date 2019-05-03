<?php declare(strict_types=1);

namespace Phan\Tests\LanguageServer;

use Exception;
use InvalidArgumentException;
use Phan\LanguageServer\Server\TextDocument;
use Phan\LanguageServer\Server\Workspace;
use Phan\Tests\BaseTest;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;

/**
 * Tests that the dispatcher is capable of parsing the doc comments of public methods
 * of the language server's methods (i.e. workspace/*, textDocument/*)
 * (should not throw InvalidArgumentException)
 *
 * @see https://microsoft.github.io/language-server-protocol/specification
 */
final class DocBlockTest extends BaseTest
{
    public function testAllCommentsValid()
    {
        $doc_block_factory = DocBlockFactory::createInstance();
        $context_factory = new ContextFactory();
        $classes = [
            TextDocument::class,
            Workspace::class,
        ];
        foreach ($classes as $class_name) {
            $reflection_class = new ReflectionClass($class_name);
            $context = $context_factory->createFromReflector($reflection_class);
            foreach ($reflection_class->getMethods() as $method) {
                if (!$method->isPublic()) {
                    continue;
                }
                $comment = $method->getDocComment();
                if (!$comment) {
                    continue;
                }
                try {
                    $doc_block = $doc_block_factory->create($comment, $context);
                    $param_tags = $doc_block->getTagsByName('param');
                    $this->assertIsArray($param_tags);
                } catch (Exception $e) {
                    $fqsen = $reflection_class->getName() . '::' . $method->getName();
                    throw new InvalidArgumentException("Invalid doc comment for $fqsen: " . $e->getMessage(), 1, $e);
                }
            }
        }
    }
}
