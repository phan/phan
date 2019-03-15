<?php declare(strict_types=1);

namespace Phan\LanguageServer;

/**
 * A class to keep track of overrides by language server clients with open files.
 * Created for Phan.
 *
 * TODO: remove all overrides when a language client disconnects.
 * (Right now, we only have a single client, and shut down when the client disconnects)
 *
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod TODO: Document
 */
class FileMapping
{
    /**
     * @var array<string,string> maps the absolute paths on disks to the currently edited versions of those files.
     * TODO: Won't work with more than one client.
     */
    private $overrides = [];

    /**
     * @var array<string,string> maps the absolute path on disk to the URI sent by the language server.
     * This may or may not help avoid creating duplicate requests for a given path.
     */
    private $uri_for_path = [];

    public function __construct()
    {
    }

    /**
     * @return array<string,string> maps the absolute paths on disks to the currently edited versions of those files.
     */
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
        $path = Utils::uriToPath($uri);
        if ($new_contents === null) {
            $this->removeOverride($path);
        }
        $this->uri_for_path[$path] = $uri;
        $this->addOverride($path, $new_contents);
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
     * Returns the file URI for the path $path.
     *
     * This will prefer to return the URI that the client first sent (that got converted to $path)
     */
    public function getURIForPath(string $path) : string
    {
        return $this->uri_for_path[$path] ?? Utils::pathToUri($path);
    }

    /**
     * @return void
     */
    public function removeOverride(string $path)
    {
        unset($this->uri_for_path[$path]);
        unset($this->overrides[$path]);
    }
}
