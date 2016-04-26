<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Plugin;
use ast\Node;

/**
 * The root plugin that calls out each hook
 * on any plugins defined in the configuration.
 */
class ConfigPluginSet extends Plugin {

    use \Phan\Memoize;

    /**
     * Call `ConfigPluginSet::instance()` instead.
     */
    private function __construct() {}

    /**
     * @return ConfigPluginSet
     * A shared single instance of this plugin
     */
    public static function instance() : ConfigPluginSet
    {
        return self::memoizeStatic(__METHOD__, function() {
            return new self;
        });
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node
     * The php-ast Node being analyzed.
     *
     * @param Node $node
     * The parent node of the given node (if one exists).
     *
     * @return void
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        foreach ($this->getPlugins() as $plugin) {
            $plugin->analyzeNode(
                $code_base,
                $context,
                $node,
                $parent_node
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        foreach ($this->getPlugins() as $plugin) {
            $plugin->analyzeClass(
                $code_base,
                $class
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        foreach ($this->getPlugins() as $plugin) {
            $plugin->analyzeMethod(
                $code_base,
                $method
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        foreach ($this->getPlugins() as $plugin) {
            $plugin->analyzeFunction(
                $code_base,
                $function
            );
        }
    }

    /**
     * @return Plugin[]
     */
    private function getPlugins() : array {
        return $this->memoize(__METHOD__, function() {
            return array_map(
                function(string $plugin_file_name) {
                    $plugin_instance =
                        require($plugin_file_name);

                    assert(!empty($plugin_instance),
                        "Plugins must return an instance of the plugin. The plugin at $plugin_file_name does not.");

                    assert($plugin_instance instanceof Plugin,
                        "Plugins must extends \Phan\Plugin. The plugin at $plugin_file_name does not.");

                    return $plugin_instance;
                },
                Config::get()->plugins
            );
        });
    }

}
