<?php

/**
 * Horde_ActiveSync_Message_MeetingRequestRecurrence
 *
 * Recurrence pattern for MeetingRequest mail messages (POOMMAIL namespace).
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @license   http://www.horde.org/licenses/gpl GPLv2
 * @copyright 2026 The Horde Project (http://www.horde.org)
 * @package   ActiveSync
 *
 * @property integer   $type
 * @property Horde_Date   $until
 * @property string   $occurrences
 * @property integer   $interval
 * @property integer   $dayofweek
 * @property integer   $dayofmonth
 * @property integer   $weekofmonth
 * @property integer   $monthofyear
 */
class Horde_ActiveSync_Message_MeetingRequestRecurrence extends Horde_ActiveSync_Message_Base
{
    /**
     * Property mapping.
     *
     * @var array
     */
    protected $_mapping = [
        Horde_ActiveSync_Message_Mail::POOMMAIL_TYPE => [self::KEY_ATTRIBUTE => 'type'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_UNTIL => [self::KEY_ATTRIBUTE => 'until', self::KEY_TYPE => self::TYPE_DATE],
        Horde_ActiveSync_Message_Mail::POOMMAIL_OCCURRENCES => [self::KEY_ATTRIBUTE => 'occurrences'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_INTERVAL => [self::KEY_ATTRIBUTE => 'interval'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_DAYOFWEEK => [self::KEY_ATTRIBUTE => 'dayofweek'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_DAYOFMONTH => [self::KEY_ATTRIBUTE => 'dayofmonth'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_WEEKOFMONTH => [self::KEY_ATTRIBUTE => 'weekofmonth'],
        Horde_ActiveSync_Message_Mail::POOMMAIL_MONTHOFYEAR => [self::KEY_ATTRIBUTE => 'monthofyear'],
    ];

    /**
     * Property values.
     *
     * @var array
     */
    protected $_properties = [
        'type' => false,
        'until' => false,
        'occurrences' => false,
        'interval' => false,
        'dayofweek' => false,
        'dayofmonth' => false,
        'weekofmonth' => false,
        'monthofyear' => false,
    ];

    /**
     * Const'r
     *
     * @see Horde_ActiveSync_Message_Base::__construct()
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEEN) {
            $this->_mapping += [
                Horde_ActiveSync_Message_Mail::POOMMAIL2_CALENDARTYPE => [self::KEY_ATTRIBUTE => 'calendartype'],
                Horde_ActiveSync_Message_Mail::POOMMAIL2_ISLEAPMONTH => [self::KEY_ATTRIBUTE => 'isleapmonth'],
            ];
            $this->_properties += [
                'calendartype' => false,
                'isleapmonth' => false,
            ];
        }
        if ($this->_version >= Horde_ActiveSync::VERSION_FOURTEENONE) {
            $this->_mapping += [
                Horde_ActiveSync_Message_Mail::POOMMAIL2_FIRSTDAYOFWEEK => [self::KEY_ATTRIBUTE => 'firstdayofweek'],
            ];
            $this->_properties += [
                'firstdayofweek' => false,
            ];
        }
    }

    /**
     * Create from a calendar-sync recurrence object.
     */
    public static function fromCalendarRecurrence(
        Horde_ActiveSync_Message_Recurrence $recurrence,
        array $options = []
    ): self {
        $message = new self($options);

        foreach (array_keys($message->_properties) as $property) {
            if (!$recurrence->propertyExists($property)) {
                continue;
            }

            $value = $recurrence->getProperty($property);
            if ($value !== false && $value !== '') {
                $message->setProperty($property, $value);
            }
        }

        return $message;
    }

    protected function _validateDecodedValues()
    {
        if ($this->_properties['type'] == Horde_ActiveSync_Message_Recurrence::TYPE_WEEKLY
            && $this->_properties['dayofweek'] < 1) {
            return false;
        }

        return true;
    }

    protected function _preEncodeValidation()
    {
        return $this->_validateDecodedValues();
    }
}
