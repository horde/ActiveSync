<?php

namespace Horde\ActiveSync\StateTest;

use Horde_ActiveSync;
use Horde_ActiveSync_State_Base;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversNothing]
class CutOffDateTest extends TestCase
{
    /**
     * @param integer $filtertype
     *
     * @return integer
     */
    protected function _cutOffDate($filtertype)
    {
        $method = new ReflectionMethod(Horde_ActiveSync_State_Base::class, '_getCutOffDate');
        $method->setAccessible(true);

        return $method->invoke(null, $filtertype);
    }

    public function testIncompleteTasksFilterIsNotEncodedAsTimestamp()
    {
        $this->assertSame(0, $this->_cutOffDate(Horde_ActiveSync::FILTERTYPE_INCOMPLETETASKS));
    }

    public function testOneWeekFilterReturnsPastTimestamp()
    {
        $cutoff = $this->_cutOffDate(Horde_ActiveSync::FILTERTYPE_1WEEK);
        $this->assertGreaterThan(0, $cutoff);
        $this->assertLessThanOrEqual(time(), $cutoff);
        $this->assertGreaterThan(time() - 604900, $cutoff);
    }

    public function testAllItemsFilterIsUnlimited()
    {
        $this->assertSame(0, $this->_cutOffDate(Horde_ActiveSync::FILTERTYPE_ALL));
    }
}
