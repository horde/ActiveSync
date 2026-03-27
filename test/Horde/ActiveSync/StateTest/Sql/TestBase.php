<?php
/**
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */
namespace Horde\ActiveSync\StateTest\Sql;
use Horde\ActiveSync\StateTest\TestBase as ExtTestBase;
use PHPUnit\Framework\Attributes\Depends;

class TestBase extends ExtTestBase
{
    protected static $db;
    protected static $migrator;
    protected static $reason;

    public function testGetDeviceInfo()
    {
        $this->_testGetDeviceInfo();
    }

    #[Depends('testGetDeviceInfo')]
    public function testCacheInitialState()
    {
        $this->_testCacheInitialState();
    }

    #[Depends('testCacheInitialState')]
    public function testCacheFolders()
    {
        $this->_testCacheFolders();
    }

    #[Depends('testCacheFolders')]
    public function testCacheDataRestrictFields()
    {
        $this->_testCacheDataRestrictFields();
    }

    #[Depends('testCacheFolders')]
    public function testCacheFoldersPersistence()
    {
        $this->_testCacheFoldersPersistence();
    }

    #[Depends('testCacheFolders')]
    public function testCacheUniqueness()
    {
        $this->_testCacheUniqueness();
    }

    #[Depends('testCacheFolders')]
    public function testCacheCollections()
    {
        $this->_testCacheCollections();
    }

    #[Depends('testCacheCollections')]
    public function testLoadCollectionsFromCache()
    {
        return $this->_testLoadCollectionsFromCache();
    }

    #[Depends('testCacheCollections')]
    public function testGettingImapId()
    {
        $this->_testGettingImapId();
    }

    #[Depends('testCacheCollections')]
    public function testCacheRefreshCollections()
    {
        $this->_testCacheRefreshCollections();
    }

    #[Depends('testCacheCollections')]
    public function testCollectionsFromCache()
    {
        $this->_testCollectionsFromCache();
    }

    #[Depends('testCacheFolders')]
    public function testGetStateWithNoState()
    {
        $this->_testGetStateWithNoState();
        $this->markTestIncomplete();
    }

    #[Depends('testCollectionsFromCache')]
    public function testCollectionHandler()
    {
        $this->_testCollectionHandler();
    }

    #[Depends('testCollectionHandler')]
    public function testPartialSyncWithChangedCollections()
    {
        $this->_testPartialSyncWithChangedCollections();
    }

    #[Depends('testCollectionHandler')]
    public function testPartialSyncWithUnchangedCollections()
    {
        $this->_testPartialSyncWithUnchangedCollections();
    }

    #[Depends('testCollectionHandler')]
    public function testMissingCollections()
    {
        $this->_testMissingCollections();
    }

    #[Depends('testCollectionHandler')]
    public function testChangingFilterType()
    {
        $this->_testChangingFilterType();
    }

    #[Depends('testCollectionHandler')]
    public function testEmptyResponse()
    {
        $this->_testEmptyResponse();
    }

    #[Depends('testGetDeviceInfo')]
    public function testHierarchy()
    {
        $this->_testHierarchy();
    }

    #[Depends('testGetDeviceInfo')]
    public function testListDevices()
    {
        $this->_testListDevices();
    }

    #[Depends('testListDevices')]
    public function testPolicyKeys()
    {
        $this->_testPolicyKeys();
    }

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
        self::$logger = \Horde\ActiveSync\Test\Helpers\LogHelper::createMockLogger();
        if (self::$db) {
            self::$migrator = new Horde_Db_Migration_Migrator(
                self::$db,
                self::$logger,
                array('migrationsPath' => $dir,
                      'schemaTableName' => 'horde_activesync_schema_info'));
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
        self::$state = new Horde_ActiveSync_State_Sql(array('db' => self::$db));
        $backend = $this->getMockBuilder('Horde_ActiveSync_Driver_Base')->disableOriginalConstructor()->getMock();
        $backend->expects($this->any())->method('getUser')->will($this->returnValue('mike'));
        self::$state->setBackend($backend);
    }
}