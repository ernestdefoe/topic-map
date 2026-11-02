<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTableIfNotExists('topicmap_discussion_views', function (Blueprint $table) {
    $table->unsignedInteger('discussion_id')->primary();
    $table->unsignedInteger('count')->default(0);

    $table->foreign('discussion_id')
        ->references('id')->on('discussions')
        ->cascadeOnDelete();
});
