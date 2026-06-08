<?php

class HordeActiveSyncAddCollectionLock extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (in_array('horde_activesync_collection_lock', $this->tables())) {
            return;
        }

        $t = $this->createTable('horde_activesync_collection_lock', ['autoincrementKey' => false]);
        $t->column('sync_user', 'string', ['limit' => 255, 'null' => false]);
        $t->column('sync_devid', 'string', ['limit' => 255, 'null' => false]);
        $t->column('sync_folderid', 'string', ['limit' => 255, 'null' => false]);
        $t->column('lock_token', 'bigint');
        $t->column('lock_time', 'integer');
        $t->primaryKey(['sync_user', 'sync_devid', 'sync_folderid']);
        $t->end();
    }

    public function down()
    {
        $this->dropTable('horde_activesync_collection_lock');
    }

}
