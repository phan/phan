<?php declare(strict_types=1);
namespace Phan\LanguageServer;

use Phan\Language\FileRef;
use Phan\Language\Element\AddressableElementInterface;
use Phan\LanguageServer\Protocol\Location;
use Phan\LanguageServer\Protocol\Position;

use Exception;
use Sabre\Event\Promise;

final class GoToDefinitionRequest
{
    /** @var string file URI */
    private $uri;
    /** @var string absolute path for $this->uri */
    private $path;
    /** @var Position */
    private $position;
    /** @var Promise|null */
    private $promise;

    /** @var array<int,Location> */
    private $locations = [];

    public function __construct(string $uri, Position $position)
    {
        $this->uri = $uri;
        $this->path = Utils::uriToPath($uri);
        $this->position = $position;
        $this->promise = new Promise();
    }

    /**
     * @return void
     */
    public function recordDefinitionElement(AddressableElementInterface $element)
    {
        $this->recordDefinitionContext($element->getContext());
    }

    public function recordDefinitionContext(FileRef $context) {
        if ($context->isPHPInternal()) {
            // We don't have complete stubs to show the user for internal functions such as is_string(), etc.
            return;
        }
        $this->recordDefinitionLocation(Location::fromContext($context));
    }


    /**
     * @return void
     */
    public function recordDefinitionLocation(Location $location)
    {
        $this->locations[$location->uri . ':' . \json_encode($location->range)] = $location;
    }

    /**
     * @param ?Location|?array<int,Location> $locations
     * @return void
     */
    public function recordDefinitionLocationList($locations)
    {
        if ($locations instanceof Location || isset($locations['uri'])) {
            $locations = [$locations];
        }
        foreach ($locations ?? [] as $location) {
            if (is_array($location)) {
                $location = Location::fromArray($location);
            }
            $this->recordDefinitionLocation($location);
        }
    }

    /**
     * @return array<int,Location>
     */
    public function getDefinitionLocations() : array
    {
        return array_values($this->locations);
    }

    public function finalize()
    {
        $promise = $this->promise;
        if ($promise) {
            $promise->fulfill($this->locations ? array_values($this->locations) : null);
            $this->promise = null;
        }
    }

    public function getUrl() : string
    {
        return $this->uri;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function getPosition() : Position
    {
        return $this->position;
    }

    /** @return ?Promise */
    public function getPromise()
    {
        return $this->promise;
    }

    public function __destruct()
    {
        $promise = $this->promise;
        if ($promise) {
            $promise->reject(new Exception('Failed to send a valid textDocument/definition result'));
            $this->promise = null;
        }
    }
}
