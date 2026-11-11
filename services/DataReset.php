<?php

declare(strict_types=1);

namespace app\services;

use Yii;

/**
 * Deletes imported rows and generated campaigns, keeping the admin user and
 * the configured rules, because those are setup rather than imported data.
 */
final class DataReset
{
    private const TABLES = [
        '{{%campaign_ads}}' => 'ads',
        '{{%campaign_exclusions}}' => 'exclusions',
        '{{%campaign_groups}}' => 'groups',
        '{{%campaigns}}' => 'campaigns',
        '{{%keywords}}' => 'keywords',
        '{{%imports}}' => 'imports',
    ];

    /**
     * @return array{ads: int, exclusions: int, groups: int, campaigns: int, keywords: int, imports: int}
     */
    public function run(): array
    {
        $db = Yii::$app->db;
        $removed = [];

        $transaction = $db->beginTransaction();
        try {
            foreach (self::TABLES as $table => $key) {
                $removed[$key] = (int) (new \yii\db\Query())
                    ->from($table)
                    ->count('*', $db);
                $db->createCommand()->delete($table)->execute();
            }

            $transaction->commit();
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            throw $exception;
        }

        return $removed;
    }
}
