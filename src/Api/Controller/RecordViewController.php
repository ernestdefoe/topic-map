<?php

namespace Ernestdefoe\TopicMap\Api\Controller;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/topicmap/{id}/view — count a discussion view into this
 * extension's own table. The frontend only calls this when
 * ernestdefoe/bespoke is absent (Bespoke's counter is the source of
 * truth on forums that run it); guests count, visibility-checked, one
 * hit per discussion per browser session enforced client-side.
 */
class RecordViewController implements RequestHandlerInterface
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $id = (int) Arr::get($request->getQueryParams(), 'id', 0);

        $visible = $id > 0 && Discussion::whereVisibleTo($actor)->whereKey($id)->exists();
        if (! $visible || ! $this->db->getSchemaBuilder()->hasTable('topicmap_discussion_views')) {
            return new EmptyResponse(204);
        }

        $views = fn () => $this->db->table('topicmap_discussion_views')->where('discussion_id', $id);

        // Counted first, inserted only for a discussion's first view. The
        // other way round, every view after the first was a failed INSERT,
        // and PostgreSQL reports that as SQLSTATE 23505, not MySQL's 23000,
        // so it was rethrown as a 500.
        if ($views()->increment('count') === 0) {
            try {
                $this->db->table('topicmap_discussion_views')->insert(['discussion_id' => $id, 'count' => 1]);
            } catch (QueryException $e) {
                // Two first views at once: the other one inserted the row.
                if (! str_starts_with((string) $e->getCode(), '23')) {
                    throw $e;
                }
                $views()->increment('count');
            }
        }

        return new EmptyResponse(204);
    }
}
