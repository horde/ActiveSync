<?php

class HordeActiveSyncBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_activesync_state', $this->tables())) {
            $t = $this->createTable('horde_activesync_state', ['autoincrementKey' => false]);
            $t->column('sync_time', 'integer');
            $t->column('sync_key', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sync_data', 'text');
            $t->column('sync_devid', 'string', ['limit' => 255]);
            $t->column('sync_folderid', 'string', ['limit' => 255]);
            $t->column('sync_user', 'string', ['limit' => 255]);
            $t->primaryKey(['sync_key']);
            $t->end();

            $this->addIndex('horde_activesync_state', ['sync_folderid']);
            $this->addIndex('horde_activesync_state', ['sync_devid']);
        }
        if (!in_array('horde_activesync_map', $this->tables())) {
            $t = $this->createTable('horde_activesync_map', ['autoincrementKey' => false]);
            $t->column('message_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sync_modtime', 'integer');
            $t->column('sync_key', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sync_devid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sync_folderid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sync_user', 'string', ['limit' => 255]);
            $t->end();

            $this->addIndex('horde_activesync_map', ['sync_devid']);
            $this->addIndex('horde_activesync_map', ['message_uid']);
            $this->addIndex('horde_activesync_map', ['sync_user']);
        }
        if (!in_array('horde_activesync_device', $this->tables())) {
            $t = $this->createTable('horde_activesync_device', ['autoincrementKey' => false]);
            $t->column('device_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('device_type', 'string', ['limit' => 255, 'null' => false]);
            $t->column('device_agent', 'string', ['limit' => 255, 'null' => false]);
            $t->column('device_supported', 'text');
            $t->column('device_policykey', 'bigint', ['default' => 0]);
            $t->column('device_rwstatus', 'integer');
            $t->primaryKey(['device_id']);
            $t->end();
        }
        if (!in_array('horde_activesync_device_users', $this->tables())) {
            $t = $this->createTable('horde_activesync_device_users', ['autoincrementKey' => false]);
            $t->column('device_id', 'string', ['limit' => 255, 'null' => false]);
            $t->column('device_user', 'string', ['limit' => 255, 'null' => false]);
            $t->column('device_ping', 'text');
            $t->column('device_folders', 'text');
            $t->end();

            $this->addIndex('horde_activesync_device_users', ['device_user']);
            $this->addIndex('horde_activesync_device_users', ['device_id']);
        }
    }

    public function down()
    {
        $this->dropTable('horde_activesync_device_users');
        $this->dropTable('horde_activesync_device');
        $this->dropTable('horde_activesync_map');
        $this->dropTable('horde_activesync_state');
    }
}
