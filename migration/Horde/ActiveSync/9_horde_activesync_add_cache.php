<?php

class HordeActiveSyncAddCache extends Horde_Db_Migration_Base
{
    public function up()
    {
        $t = $this->createTable('horde_activesync_cache', ['autoincrementKey' => false]);
        $t->column('cache_devid', 'string', ['limit' => 255]);
        $t->column('cache_user', 'string', ['limit' => 255]);
        $t->column('cache_data', 'text');
        $t->end();

        $this->addIndex('horde_activesync_cache', ['cache_devid']);
        $this->addIndex('horde_activesync_cache', ['cache_user']);
    }

    public function down()
    {
        $this->dropTable('horde_activesync_cache');
    }

}
