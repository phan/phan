<?php declare(strict_types = 1);

namespace Phan\LanguageServer;

/**
 * A class to keep track of overrides by language server clients with open files.
 * Created for Phan.
 *
 * TODO: remove all overrides when a language server disconnects.
 */
class FileMapping
{
    /**
     * @var string[] maps the absolute paths on disks to the currently edited versions of those files.
     * TODO: Won't work with more than one client.
     */
    private $overrides = [];

    public function __construct()
    {
    }

    public function getOverrides()
    {
        return $this->overrides;
    }

    /**
     * @param string $uri
     * @param ?string $new_contents
     * @return void
     */
    public function addOverrideURI(string $uri, $new_contents)
    {
        $this->addOverride(Utils::uriToPath($uri), $new_contents);
    }

    /**
     * @param string $path
     * @param ?string $new_contents
     * @return void
     */
    public function addOverride(string $path, $new_contents)
    {
        if ($new_contents === null) {
            $this->removeOverride($path);
            return;
        }
        $this->overrides[$path] = $new_contents;
    }

    /**
     * @return void
     */
    public function removeOverrideURI(string $uri)
    {
        $path = Utils::uriToPath($uri);
        $this->removeOverride($path);
    }

    /**
     * @return void
     */
    public function removeOverride(string $path)
    {
        unset($this->overrides[$path]);
    }
}
