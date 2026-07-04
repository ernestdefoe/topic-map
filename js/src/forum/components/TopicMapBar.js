import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';

/**
 * The Discourse-style topic map rendered under a discussion's first post:
 * views · likes · links · users · read time, plus expandable Top Replies
 * and Links panels. Data comes from GET /api/topicmap/{id} (cached
 * server-side); one fetch per discussion per page session.
 */

const cache = new Map();

function fetchMap(discussionId) {
  if (cache.has(discussionId)) return cache.get(discussionId);
  const p = app
    .request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/topicmap/' + discussionId })
    .catch(() => null);
  cache.set(discussionId, p);
  return p;
}

export default class TopicMapBar extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.data = null;
    this.loading = true;
    this.panel = null;

    fetchMap(this.attrs.discussion.id()).then((d) => {
      this.data = d && d.ok ? d : null;
      this.loading = false;
      m.redraw();
    });
  }

  t(key, params) {
    return app.translator.trans('ernestdefoe-topic-map.forum.' + key, params);
  }

  view() {
    if (this.loading) return m('.TopicMap.TopicMap--loading', m(LoadingIndicator, { size: 'small' }));
    if (!this.data) return null;

    const d = this.data;
    const stats = [];

    if (d.views !== null && d.views !== undefined) {
      stats.push(this.stat('views', fmt(d.views), this.t('views')));
    }
    if (d.likes !== null && d.likes !== undefined) {
      stats.push(this.stat('likes', fmt(d.likes), this.t('likes')));
    }
    if (d.linkCount > 0) {
      stats.push(this.stat('links', fmt(d.linkCount), this.t('links'), () => this.toggle('links')));
    }
    stats.push(
      m('.TopicMap-stat.TopicMap-users', [
        m('.TopicMap-avatars', (d.users.top || []).map((u) =>
          u.avatarUrl
            ? m('img.Avatar.TopicMap-avatar', { src: u.avatarUrl, alt: u.username, title: u.username + ' · ' + u.posts, loading: 'lazy' })
            : m('span.Avatar.TopicMap-avatar.TopicMap-avatar--letter', { title: u.username }, (u.username || '?').charAt(0).toUpperCase())
        )),
        m('.TopicMap-statMeta', [m('b', fmt(d.users.count)), m('span', this.t('users'))]),
      ])
    );
    stats.push(this.stat('read', d.readMinutes, this.t('min_read')));

    return m('.TopicMap', [
      m('.TopicMap-bar', [
        m('.TopicMap-stats', stats),
        (d.topReplies || []).length
          ? m(Button, {
              className: 'Button TopicMap-topBtn' + (this.panel === 'top' ? ' is-open' : ''),
              icon: 'fa-solid fa-layer-group',
              onclick: () => this.toggle('top'),
            }, this.t('top_replies'))
          : null,
      ]),
      this.panel === 'top' ? this.topRepliesPanel(d) : null,
      this.panel === 'links' ? this.linksPanel(d) : null,
    ]);
  }

  stat(kind, value, label, onclick) {
    return m(onclick ? 'button.TopicMap-stat.TopicMap-stat--btn' : '.TopicMap-stat', onclick ? { type: 'button', onclick } : {}, [
      m('b', value),
      m('span', label),
    ]);
  }

  toggle(panel) {
    this.panel = this.panel === panel ? null : panel;
    m.redraw();
  }

  topRepliesPanel(d) {
    const discussion = this.attrs.discussion;
    return m('.TopicMap-panel', d.topReplies.map((r) =>
      m('button.TopicMap-reply', {
        type: 'button',
        onclick: () => m.route.set(app.route.discussion(discussion, r.number)),
      }, [
        r.avatarUrl
          ? m('img.Avatar.TopicMap-avatar', { src: r.avatarUrl, alt: r.username, loading: 'lazy' })
          : m('span.Avatar.TopicMap-avatar.TopicMap-avatar--letter', (r.username || '?').charAt(0).toUpperCase()),
        m('.TopicMap-replyBody', [
          m('.TopicMap-replyMeta', [m('b', r.username), m('span.TopicMap-replyLikes', '♥ ' + r.likes)]),
          m('.TopicMap-replyExcerpt', r.excerpt),
        ]),
      ])
    ));
  }

  linksPanel(d) {
    return m('.TopicMap-panel', d.links.map((l) =>
      m('a.TopicMap-link', { href: l.url, target: '_blank', rel: 'noopener noreferrer nofollow' }, [
        m('span.TopicMap-linkHost', l.host),
        m('span.TopicMap-linkUrl', l.url),
        l.count > 1 ? m('span.TopicMap-linkCount', '×' + l.count) : null,
      ])
    ));
  }
}

function fmt(n) {
  n = Number(n) || 0;
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
  if (n >= 1_000) return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'k';
  return String(n);
}
