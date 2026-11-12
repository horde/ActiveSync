<?php

/**
 * In-memory stand-in for a legacy MongoDB database object that serves one
 * InMemoryMongoCollection for every collection name.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Mongo;

class InMemoryMongoDb
{
    private $_collection;

    public function __construct(InMemoryMongoCollection $collection)
    {
        $this->_collection = $collection;
    }

    public function selectCollection($name)
    {
        return $this->_collection;
    }
}
