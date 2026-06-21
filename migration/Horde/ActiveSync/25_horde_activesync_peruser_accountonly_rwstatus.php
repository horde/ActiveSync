<?php

class HordeActiveSyncPeruserAccountonlyRwstatus extends Horde_Db_Migration_Base
{
    public function up()
    {
        $columns = $this->_connection->columns('horde_activesync_device_users');
        if (!isset($columns['device_accountonly_rwstatus'])) {
            $this->addColumn(
                'horde_activesync_device_users',
                'device_accountonly_rwstatus',
                'integer',
                ['default' => 0]
            );
        }

        // Account-only wipe status was incorrectly stored per device_id.
        $this->_connection->update(
            'UPDATE horde_activesync_device SET device_rwstatus = ?'
            . ' WHERE device_rwstatus IN (?, ?)',
            [
                Horde_ActiveSync::RWSTATUS_OK,
                Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING,
                Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_WIPED,
            ]
        );
    }

    public function down()
    {
        $this->removeColumn(
            'horde_activesync_device_users',
            'device_accountonly_rwstatus'
        );
    }
}
