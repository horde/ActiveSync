<?php

/**
 * Unit tests for streaming ActiveSync Search keep-alives.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Request;

use Horde_ActiveSync_Log_Logger;
use Horde_ActiveSync_Request_Search;
use Horde_ActiveSync_Wbxml;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Log_Handler_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Request_Search::class)]
class SearchStreamingTest extends TestCase
{
    public function testEmitKeepAliveHonorsInterval()
    {
        $search = $this->_searchRequestWithoutConstructor();
        $ref = new ReflectionClass($search);

        $stream = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder(
            $stream,
            Horde_ActiveSync_Wbxml::LOG_PROTOCOL
        );
        $encoder->setLogger(
            new Horde_ActiveSync_Log_Logger(new Horde_Log_Handler_Null())
        );

        foreach ([
            '_encoder' => $encoder,
            '_keepAliveInterval' => 15,
            '_lastKeepAlive' => microtime(true),
        ] as $property => $value) {
            $prop = $ref->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($search, $value);
        }

        $method = $ref->getMethod('_emitKeepAlive');
        $method->setAccessible(true);

        $this->assertSame(0, $method->invoke($search));

        $last = $ref->getProperty('_lastKeepAlive');
        $last->setAccessible(true);
        $last->setValue($search, microtime(true) - 20);

        $this->assertSame(1, $method->invoke($search));
        $this->assertGreaterThan(0, ftell($stream));
    }

    protected function _searchRequestWithoutConstructor()
    {
        $ref = new ReflectionClass(Horde_ActiveSync_Request_Search::class);

        return $ref->newInstanceWithoutConstructor();
    }
}
