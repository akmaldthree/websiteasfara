<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace legacy brand text in content owned by the application.
     *
     * Customer names, email addresses, reviews, questions, and other
     * user-authored fields are intentionally left unchanged.
     */
    public function up(): void
    {
        $contentColumns = [
            'settings' => ['value'],
            'categories' => ['name', 'description', 'seo_title', 'seo_description'],
            'series' => ['name', 'description', 'material', 'care', 'size_guide', 'seo_title', 'seo_description'],
            'pages' => ['title', 'body', 'seo_title', 'seo_description'],
            'content_entries' => ['title', 'summary', 'body', 'link_label', 'seo_title', 'seo_description'],
            'content_revisions' => ['snapshot'],
            'product_media' => ['alt_text'],
            'media_assets' => ['name', 'alt_text'],
            'curated_links' => ['title', 'eyebrow'],
            'store_locations' => ['name'],
            'order_events' => ['message'],
            'case_events' => ['message'],
            'notification_deliveries' => ['payload'],
            'orders' => ['bank_accounts'],
            'order_items' => ['name'],
        ];

        $grammar = DB::connection()->getQueryGrammar();

        foreach ($contentColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $wrapped = $grammar->wrap($column);
                $replacement = DB::raw(
                    "REPLACE(REPLACE(REPLACE($wrapped, 'DTHREE', 'ASFARA'), 'Dthree', 'Asfara'), 'dthree', 'asfara')"
                );

                DB::table($table)
                    ->whereNotNull($column)
                    ->update([$column => $replacement]);
            }
        }
    }

    public function down(): void
    {
        // Brand cleanup is intentionally irreversible; restoring legacy text
        // could overwrite content edited after this migration was applied.
    }
};
