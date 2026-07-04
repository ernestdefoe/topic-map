<?php

use Ernestdefoe\TopicMap\Api\Controller\RecordViewController;
use Ernestdefoe\TopicMap\Api\Controller\ShowTopicMapController;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Routes('api'))
        ->get('/topicmap/{id}', 'topic-map.show', ShowTopicMapController::class)
        ->post('/topicmap/{id}/view', 'topic-map.view', RecordViewController::class),

    (new Extend\Settings())
        ->default('topic-map.min_replies', 2)
        ->default('topic-map.top_replies_count', 5)
        ->serializeToForum('topicMapMinReplies', 'topic-map.min_replies', 'intval', 2),
];
