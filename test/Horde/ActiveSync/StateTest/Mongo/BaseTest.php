<?php
/**
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license http://www.horde.org/licenses/gpl GPLv2
 * @category Horde
 * @package Horde_ActiveSync
 * @subpackage UnitTests
 */
namespace Horde\ActiveSync\StateTest\Mongo;
use Horde\ActiveSync\StateTest\TestBase;
use PHPUnit\Framework\Attributes\Depends;

class BaseTest extends TestBase
{
    protected static $mongo;
    protected static $reason;

    public function testGetDeviceInfo()
    {
        $this->_testGetDeviceInfo();
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

    #[Depends('testListDevices')]
    public function testDuplicatePIMAddition()
    {
        // @TODO. For now, cheat and add the data directly to the db.
        $doc = array(
            'sync_clientid' => 'abc',
            'sync_user' => 'mike',
            'message_uid' => 'def',
            'sync_devid' => 'dev123'
        );
        self::$mongo->horde_activesync_test->HAS_map->insert($doc);
        self::$state->loadDeviceInfo('dev123', 'mike');
        $this->assertEquals('def', self::$state->isDuplicatePIMAddition('abc'));
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

    #[Depends('testCollectionHandler')]
    public function testPartialSyncWithOnlyChangedHbInterval()
    {
        $this->_testPartialSyncWithOnlyChangedHbInterval();
    }

    public static function setUpBeforeClass(): void
    {
        if (!(extension_loaded('mongo') || extension_loaded('mongodb')) ||
            !class_exists('Horde_Mongo_Client')) {
            self::$reason = 'MongoDB extension not loaded.';
            return;
        }
        if (($config = self::getConfig('ACTIVESYNC_MONGO_TEST_CONFIG', __DIR__ . '/../..')) &&
            isset($config['activesync']['mongo']['hostspec'])) {
            self::$mongo = \Horde\ActiveSync\Test\Helpers\MongoHelper::createMongoClient([
                'config' => $config['activesync']['mongo']['hostspec'],
                'dbname' => 'horde_activesync_test'
            ]);
        }
        if (empty(self::$mongo)) {
            self::$reason = 'Mongo connection failed.';
            return;
        }
        self::$state = new Horde_ActiveSync_State_Mongo(array('connection' => self::$mongo));
        self::$logger = \Horde\ActiveSync\Test\Helpers\LogHelper::createMockLogger();
    }

    public function setUp(): void
    {
        if (empty(self::$mongo)) {
            $this->markTestSkipped(self::$reason);
        }
        $backend = $this->getMockBuilder(\Horde_ActiveSync_Driver_Base::class)
            ->disableOriginalConstructor()
            ->getMock();
        $backend->expects($this->any())->method('getUser')->will($this->returnValue('mike'));
        self::$state->setBackend($backend);
    }

    public static function tearDownAfterClass(): void
    {
        if ((extension_loaded('mongo') || extension_loaded('mongodb')) &&
            class_exists('Horde_Mongo_Client') &&
            ($config = self::getConfig('ACTIVESYNC_MONGO_TEST_CONFIG', __DIR__ . '/../..')) &&
            isset($config['activesync']['mongo']['hostspec'])) {
            try {
                $mongo = \Horde\ActiveSync\Test\Helpers\MongoHelper::createMongoClient([
                    'config' => $config['activesync']['mongo']['hostspec'],
                    'dbname' => 'horde_activesync_test'
                ]);
                $mongo->activesync_test->drop();
            } catch (MongoConnectionException $e) {
            }
        }
        parent::tearDownAfterClass();
    }
}
