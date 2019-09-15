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
    /** @var Position the position of the cursor within $this->uri where information is being requested. */
    protected $position;
    /** @var Promise this should be resolve()d with the requested information, or resolve()d with null (or rejected) on failure or if the request was aborted */
    protected $promise;

    /**
     * @var bool was a response sent back to the client already
     */
    protected $fulfilled = false;

    public function __construct(
        string $uri,
        Position $position
    ) {
        $this->uri = $uri;
        $this->path = Utils::uriToPath($uri);
        $this->position = $position;
        $this->promise = new Promise();
    }

    /**
     * If a response hasn't been sent to the client, then send a null response
     * so that it knows it should stop waiting.
     */
    abstract public function finalize() : void;

    /**
     * Returns the file URL for which info is being requested
     *
     * @suppress PhanUnreferencedPublicMethod TODO: Compare against the context->getPath() to be sure we're looking up the right node
     */
    final public function getUrl() : string
    {
        return $this->uri;
    }

    /**
     * Returns the path for which info is being requested
     */
    final public function getPath() : string
    {
        return $this->path;
    }

    /**
     * Returns the position (line+column) for which info is being requested
     */
    final public function getPosition() : Position
    {
        return $this->position;
    }

    /**
     * Returns the promise for the result of this NodeInfoRequest.
     *
     * (to be used to fetch/await the value)
     */
    final public function getPromise() : Promise
    {
        return $this->promise;
    }
}
