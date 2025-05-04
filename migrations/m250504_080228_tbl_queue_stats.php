<?php

use yii\db\Migration;

class m250504_080228_tbl_queue_stats extends Migration
{
    public function safeUp()
    {
        $this->createTable('tbl_queue_stats', [
            'id' => $this->bigPrimaryKey(),
            'queue_name' => $this->string(64),
            'call_id' => $this->string(64),
            'position' => $this->integer(),
            'hold_time' => $this->integer(),  // زمان انتظار (ثانیه)
            'agent' => $this->string(64),
            'joined_at' => $this->dateTime(),
            'answered_at' => $this->dateTime(),
            'left_at' => $this->dateTime(),
            'status' => "ENUM('answered', 'abandoned') NOT NULL",
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // ایجاد ایندکس‌ها
        $this->createIndex(
            'idx_tbl_queue_stats_call_id',
            'tbl_queue_stats',
            'call_id'
        );

        $this->createIndex(
            'idx_tbl_queue_stats_queue_name',
            'tbl_queue_stats',
            'queue_name'
        );

        $this->createIndex(
            'idx_tbl_queue_stats_status',
            'tbl_queue_stats',
            'status'
        );
    }

    public function safeDown()
    {
        // حذف ایندکس‌ها
        $this->dropIndex('idx_tbl_queue_stats_call_id', 'tbl_queue_stats');
        $this->dropIndex('idx_tbl_queue_stats_queue_name', 'tbl_queue_stats');
        $this->dropIndex('idx_tbl_queue_stats_status', 'tbl_queue_stats');

        // حذف جدول
        $this->dropTable('tbl_queue_stats');
    }
}
