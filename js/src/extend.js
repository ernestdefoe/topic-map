import { Admin } from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Admin()
    .setting(() => ({
      setting: 'topic-map.min_replies',
      type: 'number',
      label: app.translator.trans('ernestdefoe-topic-map.admin.min_replies_label'),
      help: app.translator.trans('ernestdefoe-topic-map.admin.min_replies_help'),
      default: 2,
    }))
    .setting(() => ({
      setting: 'topic-map.top_replies_count',
      type: 'number',
      label: app.translator.trans('ernestdefoe-topic-map.admin.top_replies_label'),
      help: app.translator.trans('ernestdefoe-topic-map.admin.top_replies_help'),
      default: 5,
    })),
];
