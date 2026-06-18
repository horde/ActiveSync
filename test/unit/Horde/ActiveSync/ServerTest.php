<?php

/*
 * Unit tests for Horde_ActiveSync_Policies
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Horde\ActiveSync\Test\Support\ActiveSyncServerTrait;
use Horde_ActiveSync;

#[CoversNothing]
class ServerTest extends TestCase
{
    use ActiveSyncServerTrait;

    public function testSupportedVersions()
    {
        $fixture = $this->createActiveSyncServer([
            'maxVersion' => Horde_ActiveSync::VERSION_SIXTEENONE,
        ]);

        $this->assertEquals('2.5,12.0,12.1,14.0,14.1,16.0,16.1', $fixture->server->getSupportedVersions());
        $fixture->server->setSupportedVersion(Horde_ActiveSync::VERSION_TWELVEONE);
        $this->assertEquals('2.5,12.0,12.1', $fixture->server->getSupportedVersions());

        $fixture->server->setSupportedVersion(Horde_ActiveSync::VERSION_FOURTEEN);
        $this->assertEquals('2.5,12.0,12.1,14.0', $fixture->server->getSupportedVersions());
    }

    public function testSupportedCommands()
    {
        $fixture = $this->createActiveSyncServer();
        $this->assertEquals('Sync,SendMail,SmartForward,SmartReply,GetAttachment,GetHierarchy,CreateCollection,DeleteCollection,MoveCollection,FolderSync,FolderCreate,FolderDelete,FolderUpdate,MoveItems,GetItemEstimate,MeetingResponse,Search,Settings,Ping,ItemOperations,Provision,ResolveRecipients,ValidateCert,Find', $fixture->server->getSupportedCommands());
        $fixture->server->setSupportedVersion(Horde_ActiveSync::VERSION_TWOFIVE);
        $this->assertEquals('Sync,SendMail,SmartForward,SmartReply,GetAttachment,GetHierarchy,CreateCollection,DeleteCollection,MoveCollection,FolderSync,FolderCreate,FolderDelete,FolderUpdate,MoveItems,GetItemEstimate,MeetingResponse,ResolveRecipients,ValidateCert,Provision,Search,Ping', $fixture->server->getSupportedCommands());
    }

}
