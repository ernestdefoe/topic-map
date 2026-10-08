<?php

namespace Ernestdefoe\TopicMap\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

class TopicMapTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-likes', 'ernestdefoe-topic-map');

        $link = fn (string $url) => '<r><p>See <URL url="'.$url.'">'.$url.'</URL> for more</p></r>';

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'carol', 'email' => 'carol@machine.local', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Open', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 5, 'participant_count' => 2],
                ['id' => 2, 'title' => 'Hidden', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 10, 'comment_count' => 1, 'hidden_at' => Carbon::now()],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => $link('https://flarum.org/docs')],
                ['id' => 2, 'discussion_id' => 1, 'number' => 2, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => $link('https://flarum.org/docs')],
                ['id' => 3, 'discussion_id' => 1, 'number' => 3, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>The most liked reply</p></t>'],
                ['id' => 4, 'discussion_id' => 1, 'number' => 4, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>A hidden reply</p></t>', 'hidden_at' => Carbon::now()],
                // Awaiting approval, which flarum/approval marks private.
                ['id' => 5, 'discussion_id' => 1, 'number' => 5, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => $link('https://spam.example/buy'), 'is_private' => true],
                ['id' => 10, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Secret</p></t>'],
            ],
            'post_likes' => [
                ['post_id' => 3, 'user_id' => 1], ['post_id' => 3, 'user_id' => 3],
                ['post_id' => 2, 'user_id' => 2],
                ['post_id' => 4, 'user_id' => 2],
                ['post_id' => 5, 'user_id' => 2], ['post_id' => 5, 'user_id' => 3], ['post_id' => 5, 'user_id' => 1],
            ],
        ]);
    }

    private function map(int $id, ?int $actor = null): array
    {
        $response = $this->send($this->request('GET', "/api/topicmap/$id", $actor ? ['authenticatedAs' => $actor] : []));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function view(int $id, ?int $actor = null): int
    {
        $request = $this->request('POST', "/api/topicmap/$id/view", $actor ? ['authenticatedAs' => $actor] : []);

        return $this->send($actor ? $request : $this->withGuestSession($request))->getStatusCode();
    }

    /** A guest's write needs a session and its CSRF token, as a browser has. */
    private function withGuestSession(ServerRequestInterface $request): ServerRequestInterface
    {
        $initial = $this->send($this->request('GET', '/api'));

        return $this->requestWithCookiesFrom($request->withHeader('X-CSRF-Token', $initial->getHeaderLine('X-CSRF-Token')), $initial);
    }

    #[Test]
    public function a_discussion_the_viewer_cannot_see_has_no_map()
    {
        [$status] = $this->map(2);
        $this->assertSame(404, $status);
        [$status] = $this->map(999);
        $this->assertSame(404, $status);

        [$status] = $this->map(2, 1);
        $this->assertSame(200, $status, 'An admin sees the hidden discussion');
    }

    #[Test]
    public function the_map_summarises_only_visible_replies()
    {
        [$status, $map] = $this->map(1);

        $this->assertSame(200, $status);
        $this->assertSame(1, $map['linkCount'], 'The spam link in a post awaiting approval is not listed');
        $this->assertSame(['url' => 'https://flarum.org/docs', 'host' => 'flarum.org', 'count' => 2], $map['links'][0]);
        $this->assertSame(['normal', 'carol'], array_column($map['users']['top'], 'username'));
        $this->assertSame(1, $map['readMinutes']);
        $this->assertSame(0, $map['views']);
        $this->assertSame('likes', $map['likesSource']);
        $this->assertSame(3, $map['likes'], 'Likes on hidden and private posts are not counted');
    }

    #[Test]
    public function top_replies_are_the_most_liked_visible_replies()
    {
        [, $map] = $this->map(1);

        $this->assertSame([3, 2], array_column($map['topReplies'], 'number'));
        $this->assertSame(2, $map['topReplies'][0]['likes']);
        $this->assertSame('The most liked reply', $map['topReplies'][0]['excerpt']);
        $this->assertSame('normal', $map['topReplies'][0]['username']);
    }

    #[Test]
    public function a_view_is_counted_for_a_visible_discussion_only()
    {
        $this->assertSame(204, $this->view(1));
        $this->assertSame(204, $this->view(1, 2));
        $this->assertSame(204, $this->view(1));
        $this->assertSame(204, $this->view(2));
        $this->assertSame(204, $this->view(999));

        $this->assertSame(3, (int) $this->database()->table('topicmap_discussion_views')->where('discussion_id', 1)->value('count'));
        $this->assertSame(0, $this->database()->table('topicmap_discussion_views')->where('discussion_id', 2)->count(), 'A guest cannot count a view of a hidden discussion');

        [, $map] = $this->map(1);
        $this->assertSame(3, $map['views']);
    }

    #[Test]
    public function the_forum_carries_the_minimum_replies()
    {
        $response = $this->send($this->request('GET', '/api'));
        $this->assertSame(2, json_decode((string) $response->getBody(), true)['data']['attributes']['topicMapMinReplies']);
    }

    #[Test]
    public function a_busy_thread_is_not_a_query_per_participant()
    {
        $users = $posts = $likes = [];
        for ($id = 20; $id < 32; $id++) {
            $users[] = ['id' => $id, 'username' => "user$id", 'email' => "user$id@machine.local", 'is_email_confirmed' => 1];
            $posts[] = ['id' => $id, 'discussion_id' => 1, 'number' => $id, 'created_at' => Carbon::now(), 'user_id' => $id, 'type' => 'comment', 'content' => "<t><p>Reply $id</p></t>"];
            $likes[] = ['post_id' => $id, 'user_id' => 2];
        }
        // The last of them is the most active, though the last to arrive.
        for ($n = 40; $n < 43; $n++) {
            $posts[] = ['id' => $n, 'discussion_id' => 1, 'number' => $n, 'created_at' => Carbon::now(), 'user_id' => 31, 'type' => 'comment', 'content' => "<t><p>More $n</p></t>"];
        }
        $this->prepareDatabase([User::class => $users, Post::class => $posts, 'post_likes' => $likes]);
        $this->setting('topic-map.top_replies_count', 10);

        [$status, $map] = $this->map(1);
        $this->assertSame(200, $status);
        $this->assertCount(10, $map['topReplies']);
        $this->assertCount(5, $map['users']['top']);
        $this->assertSame(['username' => 'user31', 'posts' => 4], array_intersect_key($map['users']['top'][0], ['username' => 1, 'posts' => 1]));
    }
}
