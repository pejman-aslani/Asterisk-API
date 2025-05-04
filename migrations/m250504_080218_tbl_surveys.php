<?php

use yii\db\Migration;

class m250504_080218_tbl_surveys extends Migration
{
    public function safeUp()
    {
        $this->createTable('tbl_surveys', [
            'id' => $this->bigPrimaryKey(),
            'call_log_id' => $this->bigInteger()->notNull(),
            'question_number' => $this->integer()->notNull(),
            'answer_value' => $this->string(64), // عدد یا متن
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // ایجاد ایندکس برای call_log_id
        $this->createIndex(
            'idx_tbl_surveys_call_log_id',
            'tbl_surveys',
            'call_log_id'
        );

        // ایجاد کلید خارجی برای ارتباط با جدول call_logs
        $this->addForeignKey(
            'fk_tbl_surveys_call_log_id',
            'tbl_surveys',
            'call_log_id',
            'tbl_call_logs',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        // حذف کلید خارجی
        $this->dropForeignKey('fk_tbl_surveys_call_log_id', 'tbl_surveys');

        // حذف ایندکس
        $this->dropIndex('idx_tbl_surveys_call_log_id', 'tbl_surveys');

        // حذف جدول
        $this->dropTable('tbl_surveys');
    }
}
