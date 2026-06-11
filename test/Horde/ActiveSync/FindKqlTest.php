<?php

/**
 * Unit tests for Horde_ActiveSync_Find_Kql.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   ActiveSync
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Horde_ActiveSync_FindKqlTest extends TestCase
{
    public function testPlainTextQuery()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('meeting notes');
        $this->assertInstanceOf('Horde_Imap_Client_Search_Query', $q);
    }

    public function testFromToken()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('from:john@example.com');
        $this->assertInstanceOf('Horde_Imap_Client_Search_Query', $q);
    }

    public function testSubjectToken()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('subject:"quarterly report"');
        $this->assertInstanceOf('Horde_Imap_Client_Search_Query', $q);
    }

    public function testIphoneOrExpansionQuery()
    {
        $kql = 'to:"Stoewer" OR cc:"Stoewer" OR from:"Stoewer" OR subject:"Stoewer" OR "Stoewer" OR "Stoewer"';
        $q = Horde_ActiveSync_Find_Kql::toImapQuery($kql);
        $this->assertInstanceOf('Horde_Imap_Client_Search_Query', $q);
    }
}
