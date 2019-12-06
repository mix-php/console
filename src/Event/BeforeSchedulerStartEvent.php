<?php

namespace Mix\Console\Event;

/**
 * Class BeforeSchedulerStartEvent
 * @package Mix\Console\Event
 */
class BeforeSchedulerStartEvent
{

    /**
     * @var string
     */
    public $class;

    /**
     * BeforeSchedulerStartEvent constructor.
     * @param string $class
     */
    public function __construct(string $class)
    {
        $this->class = $class;
    }

}
