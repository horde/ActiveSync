<?php

class HordeActiveSyncAddmailmap extends Horde_Db_Migration_Base
{
    public function up()
    {
        $t = $this->createTable('horde_activesync_mailmap', ['autoincrementKey' => false]);
        $t->column('message_uid', 'string', ['limit' => 255, 'null' => false]);
        $t->column('sync_key', 'string', ['limit' => 255, 'null' => false]);
        $t->column('sync_devid', 'string', ['limit' => 255, 'null' => false]);
        $t->column('sync_folderid', 'string', ['limit' => 255, 'null' => false]);
        $t->column('sync_user', 'string', ['limit' => 255]);
        $t->column('sync_read', 'integer');
        $t->column('sync_deleted', 'integer');
        $t->end();

        $this->addIndex('horde_activesync_mailmap', ['message_uid']);
        $this->addIndex('horde_activesync_mailmap', ['sync_devid']);
        $this->addIndex('horde_activesync_mailmap', ['sync_folderid']);
    }

    public function down()
    {
        $this->dropTable('horde_activesync_mailmap');
    }

}
