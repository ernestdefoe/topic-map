<?php

namespace Ernestdefoe\TopicMap\Api\Controller;

use Ernestdefoe\TopicMap\TopicMap;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/topicmap/{id} — the topic-map payload for one discussion. */
class ShowTopicMapController implements RequestHandlerInterface
{
    public function __construct(protected TopicMap $topicMap)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getQueryParams(), 'id', 0);

        $discussion = Discussion::whereVisibleTo($actor)->find($id);
        if (! $discussion) {
            throw new ModelNotFoundException();
        }

        return new JsonResponse($this->topicMap->build($discussion));
    }
}
