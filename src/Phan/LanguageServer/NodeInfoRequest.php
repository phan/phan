<?php declare(strict_types=1);
namespace Phan\LanguageServer;

use Phan\LanguageServer\Protocol\Position;
use Sabre\Event\Promise;

/**
 * Represents the Language Server Protocol's request for information about a location of a file
 *
 * @see \Phan\LanguageServer\DefinitionResolver for how this maps the found node to the type in the context.
 * @see \Phan\Plugin\Internal\NodeSelectionPlugin for how the node is found
 * @see \Phan\AST\TolerantASTConverter\TolerantASTConverterWithNodeMapping for how isSelected is set
 */
abstract class NodeInfoRequest
{
    /** @var string file URI */
    protected $uri;
    /** @var string absolute path for $this->uri */
    protected $path;
    /** @var Position */
    protected $position;
    /** @var Promise|null */
    protected $promise;

    public function __construct(
        string $uri,
        Position $position
    ) {
        $this->uri = $uri;
        $this->path = Utils::uriToPath($uri);
        $this->position = $position;
        $this->promise = new Promise();
    }

    /** @return void */
    public abstract function finalize();

    /**
     * @suppress PhanUnreferencedPublicMethod TODO: Compare against the context->getPath() to be sure we're looking up the right node
     */
    public final function getUrl() : string
    {
        return $this->uri;
    }

    public final function getPath() : string
    {
        return $this->path;
    }

    public final function getPosition() : Position
    {
        return $this->position;
    }

    /** @return ?Promise */
    public final function getPromise()
    {
        return $this->promise;
    }
}
