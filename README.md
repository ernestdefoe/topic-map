# Topic Map

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/ernestdefoe/topic-map.svg)](https://packagist.org/packages/ernestdefoe/topic-map)

A [Flarum 2](https://flarum.org) extension. A Discourse-style **topic map** under the first post of busy discussions: views, likes, outbound links, top participants and estimated read time at a glance — plus a **Top Replies** panel that jumps you straight to the most-liked answers.

## Features

- **Stats bar** on the first post once a discussion passes a reply threshold (admin setting): views · likes · links · participants (with avatars) · minutes-to-read.
- **Top Replies** — the most-liked replies with author + excerpt; click one to jump to that post. Needs `flarum/likes`; hides without it.
- **Links panel** — the discussion's outbound links grouped by URL, tap the links stat to expand.
- **View counting** built in (guests count, one per browser session, visibility-checked). On forums running `ernestdefoe/bespoke`, Bespoke's view counter is used instead — one source of truth, never double-counted.
- Everything is computed server-side and cached; a 400-post scan cap keeps huge topics cheap.
- Fully translatable.

## Installation

```sh
composer require ernestdefoe/topic-map
php flarum migrate
php flarum cache:clear
```

## Updating

```sh
composer update ernestdefoe/topic-map
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/ernestdefoe/topic-map)
- [GitHub](https://github.com/ernestdefoe/topic-map)
