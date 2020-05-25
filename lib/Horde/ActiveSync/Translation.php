<?php
/**
 * Horde_ActiveSync_Translation::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * Horde_ActiveSync_Translation is the translation wrapper class
 * for Horde_ActiveSync.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2010-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
class Horde_ActiveSync_Translation extends Horde_Translation_Autodetect
{
    /**
     * The translation domain
     *
     * @var string
     */
    protected static $_domain = 'Horde_ActiveSync';

    /**
     * The absolute PEAR path to the translations for the default gettext handler.
     *
     * @var string
     */
    protected static $_pearDirectory = '@data_dir@';
}
