<?php

/**
 * Base exception class for Horde_ActiveSync
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Base exception class for Horde_ActiveSync
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Exception extends Horde_Exception_Wrapped
{
    /** Error codes **/

    // Defauld, unspecified.
    public const UNSPECIFIED = 0;

    // Unsupported action was attempted.
    public const UNSUPPORTED = 3;
}
