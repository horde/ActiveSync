<?php

/**
 * Unit tests for recurring task ActiveSync import patterns.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @package ActiveSync
 */

namespace Horde\ActiveSync;

use Horde_ActiveSync;
use Horde_ActiveSync_Message_Task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_ActiveSync_Message_Task::class)]
class TaskRecurrenceImportTest extends TestCase
{
    public function testIsRecurrenceInstanceMasterChangeDetectsIosPattern()
    {
        $message = new Horde_ActiveSync_Message_Task();
        $recurrence = Horde_ActiveSync::messageFactory('TaskRecurrence');
        $recurrence->occurrences = 4;
        $message->recurrence = $recurrence;

        $this->assertTrue($message->isRecurrenceInstanceMasterChange());
    }

    public function testIsRecurrenceInstanceMasterChangeDetectsCompleteOnMaster()
    {
        $message = new Horde_ActiveSync_Message_Task();
        $message->recurrence = Horde_ActiveSync::messageFactory('TaskRecurrence');
        $message->complete = Horde_ActiveSync_Message_Task::TASK_COMPLETE_TRUE;

        $this->assertTrue($message->isRecurrenceInstanceMasterChange());
    }

    public function testIsRecurrenceInstanceMasterChangeDetectsDeadOccurOnTask()
    {
        $message = new Horde_ActiveSync_Message_Task();
        $message->recurrence = Horde_ActiveSync::messageFactory('TaskRecurrence');
        $message->deadoccur = true;

        $this->assertTrue($message->isRecurrenceInstanceMasterChange());
    }

    public function testIsRecurrenceInstanceMasterChangeDetectsDeadOccurOnRecurrence()
    {
        $message = new Horde_ActiveSync_Message_Task();
        $recurrence = Horde_ActiveSync::messageFactory('TaskRecurrence');
        $recurrence->deadoccur = true;
        $message->recurrence = $recurrence;

        $this->assertTrue($message->isRecurrenceInstanceMasterChange());
    }

    public function testIsRecurrenceInstanceMasterChangeIgnoresPlainEdit()
    {
        $message = new Horde_ActiveSync_Message_Task();
        $message->recurrence = Horde_ActiveSync::messageFactory('TaskRecurrence');
        $message->subject = 'Updated title';

        $this->assertFalse($message->isRecurrenceInstanceMasterChange());
    }
}
