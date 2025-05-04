<?php

use yii\db\Migration;

class m250504_080150_tbl_callback_requests extends Migration
{
    public function safeUp()
    {
        $this->createTable('tbl_callback_requests', [
            'id' => $this->bigPrimaryKey(),
            'phone_number' => $this->string(32)->notNull(),
            'requested_at' => $this->dateTime()->notNull(),
            'scheduled_at' => $this->dateTime(),
            'status' => "ENUM('pending', 'called', 'failed') NOT NULL",
            'call_log_id' => $this->bigInteger(),
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // ایجاد ایندکس‌ها
        $this->createIndex(
            'idx_tbl_callback_requests_phone_number',
            'tbl_callback_requests',
            'phone_number'
        );

        $this->createIndex(
            'idx_tbl_callback_requests_requested_at',
            'tbl_callback_requests',
            'requested_at'
        );

        $this->createIndex(
            'idx_tbl_callback_requests_status',
            'tbl_callback_requests',
            'status'
        );

        $this->createIndex(
            'idx_tbl_callback_requests_call_log_id',
            'tbl_callback_requests',
            'call_log_id'
        );
    }

    public function safeDown()
    {
        $this->dropTable('tbl_callback_requests');
    }
}
