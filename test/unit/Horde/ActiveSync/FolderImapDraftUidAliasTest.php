<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @package ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Folder_Imap;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the draft UID alias map in the IMAP folder state
 * (Issue #92): an EAS 16 Draft Modify moves the message to a new IMAP UID
 * while the client keeps the ServerId it sent.
 */
#[CoversNothing]
class FolderImapDraftUidAliasTest extends TestCase
{
    public function testAliasLookupBothDirections(): void
    {
        $folder = $this->_folder();
        $folder->setDraftUidAlias('55700', '55500');

        $this->assertSame('55500', $folder->draftClientIdForUid('55700'));
        $this->assertSame('55700', $folder->draftUidForClientId('55500'));
        $this->assertNull($folder->draftClientIdForUid('55500'));
        $this->assertNull($folder->draftUidForClientId('55700'));
    }

    public function testRepeatedEditsKeepSingleAliasPerClientId(): void
    {
        $folder = $this->_folder();
        // First edit: client keeps 55500, message now at 55700.
        $folder->setDraftUidAlias('55700', '55500');
        // Second edit: message moves again to 55900.
        $folder->setDraftUidAlias('55900', '55500');

        $this->assertSame(['55900' => '55500'], $folder->draftUidAliases());
        $this->assertSame('55900', $folder->draftUidForClientId('55500'));
        $this->assertNull($folder->draftClientIdForUid('55700'));
    }

    public function testSelfAndEmptyAliasesAreIgnored(): void
    {
        $folder = $this->_folder();
        $folder->setDraftUidAlias('55500', '55500');
        $folder->setDraftUidAlias('', '55500');
        $folder->setDraftUidAlias('55700', '');

        $this->assertSame([], $folder->draftUidAliases());
    }

    public function testRemoveDraftUidAliases(): void
    {
        $folder = $this->_folder();
        $folder->setDraftUidAlias('55700', '55500');
        $folder->setDraftUidAlias('55701', '55501');

        $folder->removeDraftUidAliases(['55700']);

        $this->assertNull($folder->draftClientIdForUid('55700'));
        $this->assertSame('55501', $folder->draftClientIdForUid('55701'));
    }

    public function testAliasesSurviveSerializationRoundTrip(): void
    {
        $folder = $this->_folder();
        $folder->setChanges([100, 101]);
        $folder->updateState();
        $folder->setDraftUidAlias('55700', '55500');

        $restored = new Horde_ActiveSync_Folder_Imap(
            'INBOX/Drafts',
            Horde_ActiveSync::CLASS_EMAIL
        );
        $restored->unserialize($folder->serialize());

        $this->assertSame('55500', $restored->draftClientIdForUid('55700'));
        $this->assertSame('55700', $restored->draftUidForClientId('55500'));
    }

    public function testEmptyAliasMapNotSerialized(): void
    {
        $folder = $this->_folder();

        $this->assertArrayNotHasKey('da', $folder->__serialize());
    }

    protected function _folder(): Horde_ActiveSync_Folder_Imap
    {
        return new Horde_ActiveSync_Folder_Imap(
            'INBOX/Drafts',
            Horde_ActiveSync::CLASS_EMAIL
        );
    }
}
