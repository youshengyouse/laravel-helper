<?php

namespace Yousheng\LaravelHelper;
class Helper
{
    protected $container;
    public function __construct($container)
    {
        $this->container  = $container;
    }
}