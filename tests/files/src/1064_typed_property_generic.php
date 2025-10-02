<?php

declare(strict_types=1);

namespace Foo\Bar\Core\Job\Entity;

use ArrayObject;
use stdClass;
use Traversable;

class Job
{
    /**
     * @var Traversable<int, stdClass>|stdClass[]
     */
    private Traversable $activities;

    /**
     * @var Traversable<int, stdClass>|stdClass[]
     */
    private Traversable $invalidActivities = null;

    public function __construct()
    {
        $this->activities = new ArrayObject();
    }
}
