<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Initial schema for the keyword import, cleaning, campaign preview and export.
 *
 * SQLite is the default local database. The column types are intentionally
 * portable so the same migration can be used on MySQL/MariaDB in production.
 */
class m261111_000000_init_schema extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%admin_user}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(190)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createTable('{{%imports}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->null(),
            'source' => $this->string(32)->notNull(),
            'original_filename' => $this->string(255)->notNull(),
            'format' => $this->string(8)->notNull(),
            'rows_total' => $this->integer()->notNull()->defaultValue(0),
            'rows_imported' => $this->integer()->notNull()->defaultValue(0),
            'rows_rejected' => $this->integer()->notNull()->defaultValue(0),
            'errors_json' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_imports_source', '{{%imports}}', 'source');
        $this->createIndex('idx_imports_created_at', '{{%imports}}', 'created_at');
        $this->createIndex('idx_imports_user_id', '{{%imports}}', 'user_id');

        $this->createTable('{{%keywords}}', [
            'id' => $this->primaryKey(),
            'import_id' => $this->integer()->notNull(),
            'source' => $this->string(32)->notNull(),
            'keyword' => $this->string(255)->notNull(),
            'keyword_norm' => $this->string(255)->notNull(),
            'volume' => $this->integer()->notNull()->defaultValue(0),
            'language' => $this->string(16)->notNull(),
            'url' => $this->string(2048)->null(),
            'status' => $this->string(16)->notNull()->defaultValue('included'),
            'reason' => $this->string(32)->null(),
            'reasons_json' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_keywords_import_id', '{{%keywords}}', 'import_id');
        $this->createIndex('idx_keywords_source', '{{%keywords}}', 'source');
        $this->createIndex('idx_keywords_language', '{{%keywords}}', 'language');
        $this->createIndex('idx_keywords_status', '{{%keywords}}', 'status');
        $this->createIndex('idx_keywords_reason', '{{%keywords}}', 'reason');
        $this->createIndex('idx_keywords_norm_lang', '{{%keywords}}', ['keyword_norm', 'language']);

        $this->createTable('{{%settings}}', [
            'id' => $this->primaryKey(),
            'brands_json' => $this->text()->notNull(),
            'forbidden_json' => $this->text()->notNull(),
            'min_volume' => $this->integer()->notNull()->defaultValue(50),
            'language_urls_json' => $this->text()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createTable('{{%campaigns}}', [
            'id' => $this->primaryKey(),
            'created_by' => $this->integer()->null(),
            'summary_json' => $this->text()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_campaigns_created_at', '{{%campaigns}}', 'created_at');
        $this->createIndex('idx_campaigns_created_by', '{{%campaigns}}', 'created_by');

        $this->createTable('{{%campaign_groups}}', [
            'id' => $this->primaryKey(),
            'campaign_id' => $this->integer()->notNull(),
            'language' => $this->string(8)->notNull(),
            'campaign_name' => $this->string(255)->notNull(),
            'ad_group_name' => $this->string(255)->notNull(),
            'keyword' => $this->string(255)->notNull(),
            'keyword_norm' => $this->string(255)->notNull(),
            'volume' => $this->integer()->notNull()->defaultValue(0),
            'sources_json' => $this->text()->notNull(),
            'target_url' => $this->string(2048)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_campaign_groups_campaign', '{{%campaign_groups}}', 'campaign_id');
        $this->createIndex('idx_campaign_groups_language', '{{%campaign_groups}}', 'language');

        $this->createTable('{{%campaign_ads}}', [
            'id' => $this->primaryKey(),
            'campaign_group_id' => $this->integer()->notNull(),
            'ad_name' => $this->string(255)->notNull(),
            'headlines_json' => $this->text()->notNull(),
            'descriptions_json' => $this->text()->notNull(),
            'path1' => $this->string(15)->notNull(),
            'path2' => $this->string(15)->notNull(),
            'final_url' => $this->string(2048)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_campaign_ads_group', '{{%campaign_ads}}', 'campaign_group_id');

        $this->createTable('{{%campaign_exclusions}}', [
            'id' => $this->primaryKey(),
            'campaign_id' => $this->integer()->notNull(),
            'keyword' => $this->string(255)->notNull(),
            'keyword_norm' => $this->string(255)->notNull(),
            'language' => $this->string(16)->notNull(),
            'volume' => $this->integer()->notNull()->defaultValue(0),
            'sources_json' => $this->text()->notNull(),
            'reason' => $this->string(32)->notNull(),
            'detail' => $this->string(255)->notNull(),
            'created_at' => $this->integer()->notNull(),
        ]);
        $this->createIndex('idx_campaign_exclusions_campaign', '{{%campaign_exclusions}}', 'campaign_id');
        $this->createIndex('idx_campaign_exclusions_reason', '{{%campaign_exclusions}}', 'reason');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%campaign_exclusions}}');
        $this->dropTable('{{%campaign_ads}}');
        $this->dropTable('{{%campaign_groups}}');
        $this->dropTable('{{%campaigns}}');
        $this->dropTable('{{%settings}}');
        $this->dropTable('{{%keywords}}');
        $this->dropTable('{{%imports}}');
        $this->dropTable('{{%admin_user}}');
    }
}
