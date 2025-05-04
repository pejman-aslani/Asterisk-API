<?php

use yii\db\Migration;

class m250504_080203_tbl_operator_codes extends Migration
{
    public function safeUp()
    {
        $this->createTable('tbl_operator_codes', [
            'id' => $this->primaryKey(),  // از primaryKey استفاده می‌کنیم چون INT است و نیاز به AUTO_INCREMENT داریم
            'user_id' => $this->integer()->notNull(),  // اپراتور
            'call_log_id' => $this->bigInteger()->notNull(),  // کلید خارجی به call_logs
            'code' => $this->string(32)->notNull(),  // کد وارد شده
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),  // تاریخ ساخت
        ]);

        // ایندکس برای call_log_id
        $this->createIndex(
            'idx_tbl_operator_codes_call_log_id',
            'tbl_operator_codes',
            'call_log_id'
        );

        // ایجاد کلید خارجی برای call_log_id
        $this->addForeignKey(
            'fk_tbl_operator_codes_call_log_id',
            'tbl_operator_codes',
            'call_log_id',
            'tbl_call_logs',
            'id'
        );
    }

    public function safeDown()
    {
        // حذف کلید خارجی
        $this->dropForeignKey('fk_tbl_operator_codes_call_log_id', 'tbl_operator_codes');

        // حذف ایندکس
        $this->dropIndex('idx_tbl_operator_codes_call_log_id', 'tbl_operator_codes');

        // حذف جدول
        $this->dropTable('tbl_operator_codes');
    }
}
