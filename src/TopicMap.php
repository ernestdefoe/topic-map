<?php

namespace Ernestdefoe\TopicMap;

use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\ConnectionInterface;

/**
 * Builds the topic-map payload for one discussion: view count, total
 * likes, outbound links, top participants, estimated read time and the
 * most-liked replies. Cached five minutes, keyed by the discussion's
 * comment count so new replies naturally invalidate it.
 *
 * Soft integrations, all table-presence gated:
 *  - views come from ernestdefoe/bespoke's counter when it's installed
 *    (single source of truth), else from this extension's own table;
 *  - likes + top replies need flarum/likes and drop out without it.
 */
class TopicMap
{
    protected const CACHE_TTL = 300;
    protected const SCAN_LIMIT = 400;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ConnectionInterface $db,
        protected ?Store $cache = null,
    ) {
    }

    public function build(Discussion $discussion): array
    {
        $key = 'topicmap.d.'.$discussion->id.'.'.$discussion->comment_count;
        $cached = $this->cache?->get($key);
        if (is_array($cached)) {
            $cached['views'] = $this->views((int) $discussion->id);

            return $cached;
        }

        $posts = $this->db->table('posts')
            ->where('discussion_id', $discussion->id)
            ->where('type', 'comment')
            ->whereNull('hidden_at')
            ->orderBy('number')
            ->limit(self::SCAN_LIMIT)
            ->get(['id', 'number', 'user_id', 'content']);

        $words = 0;
        $links = [];
        foreach ($posts as $post) {
            $text = trim(strip_tags((string) $post->content));
            if ($text !== '') {
                $words += count(preg_split('/\s+/u', $text) ?: []);
            }
            if (preg_match_all('/<URL url="([^"]+)"/', (string) $post->content, $m)) {
                foreach ($m[1] as $url) {
                    $url = html_entity_decode($url);
                    $links[$url] = ($links[$url] ?? 0) + 1;
                }
            }
        }

        arsort($links);
        $linkRows = [];
        foreach (array_slice($links, 0, 8, true) as $url => $count) {
            $linkRows[] = [
                'url' => $url,
                'host' => (string) (parse_url($url, PHP_URL_HOST) ?: $url),
                'count' => $count,
            ];
        }

        $source = $this->engagementSource();

        $map = [
            'ok' => true,
            'likes' => $source ? $this->likes((int) $discussion->id, $source['table']) : null,
            'likesSource' => $source['label'] ?? null,
            'linkCount' => count($links),
            'links' => $linkRows,
            'users' => $this->participants($discussion, $posts),
            'readMinutes' => max(1, (int) ceil($words / 200)),
            'topReplies' => $source ? $this->topReplies((int) $discussion->id, $source['table']) : [],
            'truncated' => $posts->count() >= self::SCAN_LIMIT,
        ];

        $this->cache?->put($key, $map, self::CACHE_TTL);

        $map['views'] = $this->views((int) $discussion->id);

        return $map;
    }

    /** ---- pieces --------------------------------------------------- */

    protected function views(int $discussionId): ?int
    {
        $schema = $this->db->getSchemaBuilder();
        foreach (['bespoke_discussion_views', 'topicmap_discussion_views'] as $table) {
            if ($schema->hasTable($table)) {
                return (int) ($this->db->table($table)->where('discussion_id', $discussionId)->value('count') ?? 0);
            }
        }

        return null;
    }

    /**
     * Whichever engagement extension the forum runs: flarum/likes wins
     * when both exist, fof/reactions otherwise. Both tables share the
     * post_id shape this class needs.
     *
     * @return array{table: string, label: string}|null
     */
    protected function engagementSource(): ?array
    {
        $schema = $this->db->getSchemaBuilder();
        if ($schema->hasTable('post_likes')) {
            return ['table' => 'post_likes', 'label' => 'likes'];
        }
        if ($schema->hasTable('post_reactions')) {
            return ['table' => 'post_reactions', 'label' => 'reactions'];
        }

        return null;
    }

    protected function likes(int $discussionId, string $table): int
    {
        return (int) $this->db->table($table)
            ->join('posts', 'posts.id', '=', $table.'.post_id')
            ->where('posts.discussion_id', $discussionId)
            ->count();
    }

    protected function participants(Discussion $discussion, $posts): array
    {
        $byUser = [];
        foreach ($posts as $post) {
            if ($post->user_id) {
                $byUser[$post->user_id] = ($byUser[$post->user_id] ?? 0) + 1;
            }
        }
        arsort($byUser);
        $topIds = array_slice(array_keys($byUser), 0, 5);

        // Eloquent models, not a raw table read: the avatar_url COLUMN holds
        // a bare filename; only the model accessor builds the public URL.
        $users = $topIds
            ? \Flarum\User\User::query()->whereIn('id', $topIds)->get()->keyBy('id')
            : collect();

        $top = [];
        foreach ($topIds as $id) {
            $u = $users->get($id);
            if (! $u) {
                continue;
            }
            $top[] = [
                'username' => (string) $u->username,
                'avatarUrl' => $u->avatar_url ?: null,
                'posts' => $byUser[$id],
            ];
        }

        return [
            'count' => max((int) $discussion->participant_count, count($byUser)),
            'top' => $top,
        ];
    }

    protected function topReplies(int $discussionId, string $table): array
    {
        $count = max(1, min(10, (int) $this->settings->get('topic-map.top_replies_count', 5)));

        $rows = $this->db->table('posts')
            ->join($table, $table.'.post_id', '=', 'posts.id')
            ->where('posts.discussion_id', $discussionId)
            ->where('posts.number', '>', 1)
            ->where('posts.type', 'comment')
            ->whereNull('posts.hidden_at')
            ->groupBy('posts.id', 'posts.number', 'posts.content', 'posts.user_id')
            ->orderByRaw('COUNT('.$table.'.post_id) DESC')
            ->orderBy('posts.number')
            ->limit($count)
            ->selectRaw('posts.id, posts.number, posts.content, posts.user_id, COUNT('.$table.'.post_id) as likes')
            ->get();

        $userIds = array_values(array_filter(array_unique($rows->pluck('user_id')->all())));
        $users = $userIds
            ? \Flarum\User\User::query()->whereIn('id', $userIds)->get()->keyBy('id')
            : collect();

        $out = [];
        foreach ($rows as $row) {
            $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $row->content))));
            if (mb_strlen($excerpt) > 180) {
                $excerpt = mb_substr($excerpt, 0, 180).'…';
            }
            $u = $row->user_id ? $users->get($row->user_id) : null;
            $out[] = [
                'number' => (int) $row->number,
                'likes' => (int) $row->likes,
                'excerpt' => $excerpt,
                'username' => (string) ($u->username ?? ''),
                'avatarUrl' => $u ? ($u->avatar_url ?: null) : null,
            ];
        }

        return $out;
    }
}
