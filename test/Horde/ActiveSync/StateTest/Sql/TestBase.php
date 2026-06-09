<?php

/**
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */

namespace Horde\ActiveSync\StateTest\Sql;

use PHPUnit\Framework\Attributes\Depends;
use Horde\ActiveSync\StateTest\TestBase as ExtTestBase;

class TestBase extends ExtTestBase
{
    protected static $db;
    protected static $migrator;
    protected static $reason;

    public function testGetDeviceInfo()
    {
        $this->_testGetDeviceInfo();
    }

    /**
     * @depends testGetDeviceInfo
     */
    #[Depends('testGetDeviceInfo')]
    public function testCacheInitialState()
    {
        $this->_testCacheInitialState();
    }

    /**
     * @depends testCacheInitialState
     */
    #[Depends('testCacheInitialState')]
    public function testCacheFolders()
    {
        $this->_testCacheFolders();
    }

    /**
     * @depends testCacheFolders
     */
    #[Depends('testCacheFolders')]
    public function testCacheDataRestrictFields()
    {
        $this->_testCacheDataRestrictFields();
    }

    /**
     * @depends testCacheFolders
     */
    #[Depends('testCacheFolders')]
    public function testCacheFoldersPersistence()
    {
        $this->_testCacheFoldersPersistence();
    }

    /**
     * @depends testCacheFolders
     */
    #[Depends('testCacheFolders')]
    public function testCacheUniqueness()
    {
        $this->_testCacheUniqueness();
    }

    /**
     * @depends testCacheFolders
     */
    #[Depends('testCacheFolders')]
    public function testCacheCollections()
    {
        $this->_testCacheCollections();
    }

    /**
     * @depends testCacheCollections
     */
    #[Depends('testCacheCollections')]
    public function testLoadCollectionsFromCache()
    {
        return $this->_testLoadCollectionsFromCache();
    }

    /**
     * @depends testCacheCollections
     */
    #[Depends('testCacheCollections')]
    public function testGettingImapId()
    {
        $this->_testGettingImapId();
    }

    /**
     * @depends testCacheCollections
     */
    #[Depends('testCacheCollections')]
    public function testCacheRefreshCollections()
    {
        $this->_testCacheRefreshCollections();
    }

    /**
     * @depends testCacheCollections
     */
    #[Depends('testCacheCollections')]
    public function testCollectionsFromCache()
    {
        $this->_testCollectionsFromCache();
    }

    /**
     * @depends testCacheFolders
     */
    #[Depends('testCacheFolders')]
    public function testGetStateWithNoState()
    {
        $this->_testGetStateWithNoState();
        $this->markTestIncomplete();
    }

    /**
     * @depends testCollectionsFromCache
     */
    #[Depends('testCollectionsFromCache')]
    public function testCollectionHandler()
    {
        $this->_testCollectionHandler();
    }

    /**
     * @depends testCollectionHandler
     */
    #[Depends('testCollectionHandler')]
    public function testPartialSyncWithChangedCollections()
    {
        $this->_testPartialSyncWithChangedCollections();
    }

    /**
     * @depends testCollectionHandler
     */
    #[Depends('testCollectionHandler')]
    public function testPartialSyncWithUnchangedCollections()
    {
        $this->_testPartialSyncWithUnchangedCollections();
    }

    /**
     * @depends testCollectionHandler
     */
    #[Depends('testCollectionHandler')]
    public function testMissingCollections()
    {
        $this->_testMissingCollections();
    }

    /**
     * @depends testCollectionHandler
     */
    #[Depends('testCollectionHandler')]
    public function testChangingFilterType()
    {
        $this->_testChangingFilterType();
    }

    /**
     * @depends testCollectionHandler
     */
    #[Depends('testCollectionHandler')]
    public function testEmptyResponse()
    {
        $this->_testEmptyResponse();
    }

    /**
     * @depends testGetDeviceInfo
     */
    #[Depends('testGetDeviceInfo')]
    public function testHierarchy()
    {
        $this->_testHierarchy();
    }

    /**
     * @depends testGetDeviceInfo
     */
    #[Depends('testGetDeviceInfo')]
    public function testListDevices()
    {
        $this->_testListDevices();
    }

    /**
     * @depends testListDevices
     */
    #[Depends('testListDevices')]
    public function testPolicyKeys()
    {
        $this->_testPolicyKeys();
    }

    /**
     * @depends testCollectionHandler
     */
    #[Depends('testCollectionHandler')]
    public function testPartialSyncWithOnlyChangedHbInterval()
    {
        $this->_testPartialSyncWithOnlyChangedHbInterval();
    }

    public static function setUpBeforeClass(): void
    {
        $dir = dirname(__FILE__) . '/../../../../../migration/Horde/ActiveSync';
        if (!is_dir($dir)) {
            error_reporting(E_ALL & ~E_DEPRECATED);
            $dir = PEAR_Config::singleton()
                ->get('data_dir', null, 'pear.horde.org')
                . '/Horde_ActiveSync/migration';
            error_reporting(E_ALL | E_STRICT);
        }
        self::$logger = new Horde_Test_Log();
        if (self::$db) {
            self::$migrator = new Horde_Db_Migration_Migrator(
                self::$db,
                self::$logger->getLogger(),
                ['migrationsPath' => $dir,
                    'schemaTableName' => 'horde_activesync_schema_info']
            );
            self::$migrator->up();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db) {
            if (self::$migrator) {
                self::$migrator->down();
            }
            self::$db->disconnect();
            self::$db = null;
        }
        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        if (!self::$db) {
            $this->markTestSkipped(self::$reason);
            return;
        }
        self::$state = new Horde_ActiveSync_State_Sql(['db' => self::$db]);
        $backend = $this->getMockBuilder('Horde_ActiveSync_Driver_Base')->disableOriginalConstructor()->getMock();
        $backend->expects($this->any())->method('getUser')->will($this->returnValue('mike'));
        self::$state->setBackend($backend);
    }
}
