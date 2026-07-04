<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('topicmap_discussion_views')) {
            return;
        }

        $schema->create('topicmap_discussion_views', function (Blueprint $table) {
            $table->unsignedInteger('discussion_id')->primary();
            $table->unsignedInteger('count')->default(0);

            $table->foreign('discussion_id')
                ->references('id')->on('discussions')
                ->cascadeOnDelete();
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('topicmap_discussion_views');
    },
];
