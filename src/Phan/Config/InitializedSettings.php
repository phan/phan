<?php

declare(strict_types=1);

namespace Phan\Config;

/**
 * This class is used by `phan --init`
 * as a representation of the data to use to create a phan config for a composer project.
 * @phan-immutable
 */
class InitializedSettings
{
    /** @var array<string,mixed> the values for setting names*/
    public $settings;

    /** @var array<string,list<string>> comments for settings */
    public $comment_lines;

    /** @var int the init-level CLI option used to generate the settings. Smaller numbers mean a stricter config. */
    public $init_level;

    /**
     * @param array<string,mixed> $data
     * @param array<string,list<string>> $comment_lines
     */
    public function __construct(
        array $data,
        array $comment_lines,
        int $init_level
    ) {
        $this->settings = $data;
        $this->comment_lines = $comment_lines;
        $this->init_level = $init_level;
    }
}
