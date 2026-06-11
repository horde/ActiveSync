<?php

/**
 * Unit tests for Horde_ActiveSync_Find_QueryMapper.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

use PHPUnit\Framework\TestCase;

class Horde_ActiveSync_FindQueryMapperTest extends TestCase
{
    public function testMailboxQueryMapsToSearchAndCriterion()
    {
        $find = new Horde_ActiveSync_Find_Params(
            type: 'mailbox',
            searchId: '00000000-0000-0000-0000-000000000001',
            query: [
                'class' => 'Email',
                'collectionid' => 'F0cfc112a',
                'serverid' => 'INBOX',
                'freetext' => 'from:alice@example.com subject:report',
            ],
            options: [],
            start: 0,
            limit: 100,
            deepTraversal: true,
        );

        $search = Horde_ActiveSync_Find_QueryMapper::toSearchParams($find);

        $this->assertSame('mailbox', $search->type);
        $this->assertTrue($search->deepTraversal);
        $this->assertSame(0, $search->start);
        $this->assertSame(100, $search->limit);
        $this->assertCount(1, $search->query);
        $this->assertSame(
            Horde_ActiveSync_Request_Search::SEARCH_AND,
            $search->query[0]['op']
        );
        $this->assertSame('Email', $search->query[0]['value']['FolderType']);
        $this->assertSame('INBOX', $search->query[0]['value']['serverid']);
        $this->assertSame(
            'from:alice@example.com subject:report',
            $search->query[0]['value'][Horde_ActiveSync_Request_Search::SEARCH_FREETEXT]
        );
    }

    public function testGalQueryMapsToSearchGalFormat()
    {
        $find = new Horde_ActiveSync_Find_Params(
            type: 'gal',
            searchId: '00000000-0000-0000-0000-000000000002',
            query: ['text' => 'Michael'],
            options: [],
            start: 0,
            limit: 50,
            deepTraversal: false,
        );

        $search = Horde_ActiveSync_Find_QueryMapper::toSearchParams($find);

        $this->assertSame('gal', $search->type);
        $this->assertSame(['Michael'], $search->query);
        $this->assertFalse($search->deepTraversal);
    }
}
