<?php

/**
 * Unit tests for Horde_ActiveSync_Request_Find helpers.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org/)
 * @package   ActiveSync
 */

namespace Horde\ActiveSync\Request;

use Horde_ActiveSync_Driver_Mock;
use Horde_ActiveSync_Find_Params;
use Horde_ActiveSync_Request_Find;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Horde_ActiveSync_Request_Find::class)]
class FindRequestTest extends TestCase
{
    public function testParseFindRangeDefaultsWithoutRange()
    {
        $paging = $this->_parseFindRange(null);

        $this->assertSame(0, $paging['start']);
        $this->assertSame(100, $paging['limit']);
        $this->assertSame(
            Horde_ActiveSync_Request_Find::STORE_STATUS_SUCCESS,
            $paging['storeStatus']
        );
    }

    public function testParseFindRangeHonorsClientWindow()
    {
        $paging = $this->_parseFindRange('100-200');

        $this->assertSame(100, $paging['start']);
        $this->assertSame(101, $paging['limit']);
        $this->assertSame(
            Horde_ActiveSync_Request_Find::STORE_STATUS_SUCCESS,
            $paging['storeStatus']
        );
    }

    public function testParseFindRangeCapsIosPageSize()
    {
        $paging = $this->_parseFindRange('0-100');

        $this->assertSame(0, $paging['start']);
        $this->assertSame(100, $paging['limit']);
    }

    public function testParseFindRangeRejectsInvertedWindow()
    {
        $paging = $this->_parseFindRange('50-10');

        $this->assertSame(
            Horde_ActiveSync_Request_Find::STORE_STATUS_RANGEERR,
            $paging['storeStatus']
        );
    }

    public function testParseFindRangeRejectsMalformedValue()
    {
        $paging = $this->_parseFindRange('invalid');

        $this->assertSame(
            Horde_ActiveSync_Request_Find::STORE_STATUS_RANGEERR,
            $paging['storeStatus']
        );
    }

    public function testMockDriverReturnsSuccessfulEmptyFindResults()
    {
        $driver = new Horde_ActiveSync_Driver_Mock([
            'connector' => null,
            'imap' => null,
        ]);
        $params = new Horde_ActiveSync_Find_Params(
            type: 'mailbox',
            searchId: '00000000-0000-0000-0000-000000000001',
            query: ['freetext' => 'from:alice@example.com'],
            options: [],
            start: 0,
            limit: 100,
            deepTraversal: false,
        );

        $results = $driver->getFindResults($params);

        $this->assertSame(
            Horde_ActiveSync_Request_Find::STATUS_SUCCESS,
            $results->status
        );
        $this->assertSame(
            Horde_ActiveSync_Request_Find::STORE_STATUS_SUCCESS,
            $results->storeStatus
        );
        $this->assertSame(0, $results->total);
        $this->assertSame([], $results->rows);
    }

    /**
     * @param string|null $range
     *
     * @return array{start: int, limit: int, storeStatus: int}
     */
    protected function _parseFindRange(?string $range): array
    {
        $method = (new ReflectionClass(Horde_ActiveSync_Request_Find::class))
            ->getMethod('_parseFindRange');
        $method->setAccessible(true);

        return $method->invoke(null, $range);
    }
}
