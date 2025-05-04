<?php

use yii\db\Migration;

class m250504_080547_tbl_call_reasons extends Migration
{
    public function safeUp()
    {
        $this->createTable('tbl_call_reasons', [
            'id' => $this->primaryKey(),  // از primaryKey استفاده می‌کنیم چون INT است و نیاز به AUTO_INCREMENT داریم
            'call_log_id' => $this->bigInteger()->notNull(),  // کلید خارجی به call_logs
            'reason' => $this->string(255)->notNull(),  // دلیل تماس (مثل پشتیبانی، شکایت، خرید)
            'created_at' => $this->dateTime()->defaultExpression('CURRENT_TIMESTAMP'),  // تاریخ ساخت
        ]);

        // ایندکس برای call_log_id
        $this->createIndex(
            'idx_tbl_call_reasons_call_log_id',
            'tbl_call_reasons',
            'call_log_id'
        );

        // ایجاد کلید خارجی برای call_log_id
        $this->addForeignKey(
            'fk_tbl_call_reasons_call_log_id',
            'tbl_call_reasons',
            'call_log_id',
            'tbl_call_logs',
            'id',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        // حذف کلید خارجی
        $this->dropForeignKey('fk_tbl_call_reasons_call_log_id', 'tbl_call_reasons');

        // حذف ایندکس
        $this->dropIndex('idx_tbl_call_reasons_call_log_id', 'tbl_call_reasons');

        // حذف جدول
        $this->dropTable('tbl_call_reasons');
    }
}
