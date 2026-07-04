import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import TopicMapBar from './forum/components/TopicMapBar';

app.initializers.add('ernestdefoe-topic-map', () => {
  // The map lives in the FIRST post's footer, once the discussion has
  // enough replies to be worth summarizing (admin threshold).
  extend(CommentPost.prototype, 'footerItems', function (items) {
    const post = this.attrs.post;
    if (!post || post.number() !== 1) return;

    const discussion = post.discussion();
    if (!discussion) return;

    const threshold = Number(app.forum.attribute('topicMapMinReplies')) || 2;
    if ((discussion.replyCount() || 0) < threshold) return;

    items.add('topicMap', m(TopicMapBar, { discussion }), -100);
  });

  // View counting for forums WITHOUT ernestdefoe/bespoke (Bespoke's own
  // counter is the source of truth when it's installed — never double-count).
  if (!('ernestdefoe-bespoke' in flarum.extensions)) {
    extend(DiscussionPage.prototype, 'oncreate', function () {
      const id = String(m.route.param('id') || '').match(/\d+/);
      if (!id) return;
      const key = 'topicmap-viewed-' + id[0];
      try {
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
      } catch (e) { /* storage unavailable — count anyway */ }
      app
        .request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/topicmap/' + id[0] + '/view' })
        .catch(() => {});
    });
  }
});
