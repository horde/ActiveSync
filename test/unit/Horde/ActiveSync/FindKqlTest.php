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

class Horde_ActiveSync_FindKqlTest extends TestCase
{
    public function testPlainTextQuery()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('meeting notes');
        $imap = (string) $q;
        $this->assertStringContainsString('TEXT', $imap);
        $this->assertStringContainsString('meeting', $imap);
        $this->assertStringContainsString('notes', $imap);
    }

    public function testFromToken()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('from:john@example.com');
        $this->assertStringContainsString('FROM', (string) $q);
        $this->assertStringContainsString('john@example.com', (string) $q);
    }

    public function testSubjectToken()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('subject:"quarterly report"');
        $this->assertStringContainsString('SUBJECT', (string) $q);
        $this->assertStringContainsString('quarterly report', (string) $q);
    }

    public function testClientOrExpansionQuery()
    {
        $term = 'Example Contact';
        $kql = 'to:"' . $term . '" OR cc:"' . $term . '" OR from:"' . $term
            . '" OR subject:"' . $term . '" OR "' . $term . '" OR "' . $term . '"';
        $q = Horde_ActiveSync_Find_Kql::toImapQuery($kql);
        $imap = (string) $q;
        $this->assertStringContainsString('OR', $imap);
        $this->assertStringContainsString('Example Contact', $imap);
    }

    public function testImplicitAndBetweenRestrictions()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('from:john@example.com subject:meeting');
        $imap = (string) $q;
        $this->assertStringContainsString('FROM', $imap);
        $this->assertStringContainsString('SUBJECT', $imap);
        $this->assertStringContainsString('meeting', $imap);
    }

    public function testExplicitAnd()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('from:john@example.com AND hasattachment:true');
        $imap = (string) $q;
        $this->assertStringContainsString('FROM', $imap);
        $this->assertStringContainsString('HEADER', $imap);
        $this->assertStringContainsString('CONTENT-TYPE', $imap);
    }

    public function testNotRestriction()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('NOT from:newsletter@example.com');
        $imap = (string) $q;
        $this->assertStringContainsString('NOT', $imap);
        $this->assertStringContainsString('FROM', $imap);
        $this->assertStringContainsString('newsletter@example.com', $imap);
    }

    public function testParenthesesGrouping()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('subject:meeting AND (from:a@example.com OR from:b@example.com)');
        $imap = (string) $q;
        $this->assertStringContainsString('SUBJECT', $imap);
        $this->assertStringContainsString('OR', $imap);
        $this->assertStringContainsString('a@example.com', $imap);
        $this->assertStringContainsString('b@example.com', $imap);
    }

    public function testParticipantsRestriction()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('participants:alice@example.com');
        $imap = (string) $q;
        $this->assertStringContainsString('OR', $imap);
        $this->assertStringContainsString('alice@example.com', $imap);
    }

    public function testBodyRestriction()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('body:"sales figures"');
        $this->assertStringContainsString('BODY', (string) $q);
        $this->assertStringContainsString('sales figures', (string) $q);
    }

    public function testHasAttachmentFalse()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('hasattachment:false');
        $imap = (string) $q;
        $this->assertStringContainsString('NOT', $imap);
        $this->assertStringContainsString('CONTENT-TYPE', $imap);
    }

    public function testIsReadFalse()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('isread:false');
        $this->assertStringContainsString('UNSEEN', (string) $q);
    }

    public function testIsFlaggedTrue()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('isflagged:true');
        $this->assertStringContainsString('FLAGGED', (string) $q);
    }

    public function testReceivedDateRange()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('received>=2026-01-01');
        $this->assertStringContainsString('SINCE', (string) $q);
        $this->assertStringContainsString('2026', (string) $q);
    }

    public function testSizeComparison()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('size>5000');
        $imap = (string) $q;
        $this->assertStringContainsString('LARGER', $imap);
        $this->assertStringContainsString('5000', $imap);
    }

    public function testCategoryRestriction()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('category:"Red Category"');
        $this->assertStringContainsString('KEYWORD', (string) $q);
        $this->assertStringContainsString('RED_CATEGORY', (string) $q);
    }

    public function testImportanceHigh()
    {
        $q = Horde_ActiveSync_Find_Kql::toImapQuery('importance:high');
        $imap = (string) $q;
        $this->assertStringContainsString('OR', $imap);
        $this->assertStringContainsString('IMPORTANCE', $imap);
    }
}
