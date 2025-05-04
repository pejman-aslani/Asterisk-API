<?php

use yii\db\Migration;

class m250504_075735_tbl_call_logs extends Migration
{
    public function safeUp()
    {
        $this->createTable('tbl_call_logs', [
            'id' => $this->bigPrimaryKey(),
            'call_id' => $this->string(64)->notNull(),
            'direction' => "ENUM('inbound', 'outbound') NOT NULL",
            'source' => $this->string(32)->notNull(),
            'destination' => $this->string(32)->notNull(),
            'started_at' => $this->dateTime()->notNull(),
            'answered_at' => $this->dateTime()->null(),
            'ended_at' => $this->dateTime()->notNull(),
            'duration' => $this->integer()->notNull(),
            'billsec' => $this->integer()->notNull(),
            'status' => "ENUM('answered', 'missed', 'busy', 'no-answer') NOT NULL",
            'channel' => $this->string(64)->notNull(),
            'recording_url' => $this->text()->null(),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_tbl_call_logs_call_id', 'tbl_call_logs', 'call_id');
        $this->createIndex('idx_tbl_call_logs_source', 'tbl_call_logs', 'source');
        $this->createIndex('idx_tbl_call_logs_destination', 'tbl_call_logs', 'destination');
        $this->createIndex('idx_tbl_call_logs_started_at', 'tbl_call_logs', 'started_at');
        $this->createIndex('idx_tbl_call_logs_status', 'tbl_call_logs', 'status');
        $this->createIndex('idx_tbl_call_logs_direction', 'tbl_call_logs', 'direction');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_tbl_call_logs_call_id', 'tbl_call_logs');
        $this->dropIndex('idx_tbl_call_logs_source', 'tbl_call_logs');
        $this->dropIndex('idx_tbl_call_logs_destination', 'tbl_call_logs');
        $this->dropIndex('idx_tbl_call_logs_started_at', 'tbl_call_logs');
        $this->dropIndex('idx_tbl_call_logs_status', 'tbl_call_logs');
        $this->dropIndex('idx_tbl_call_logs_direction', 'tbl_call_logs');
        $this->dropTable('tbl_call_logs');
    }
}
