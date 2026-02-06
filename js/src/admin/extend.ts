import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';

export default [
  ...commonExtend,

  new Extend.Admin() //
    .setting(
      () => ({
        setting: 'fof-releases.username_mappings',
        label: app.translator.trans('fof-releases.admin.settings.username_mappings_label'),
        help: app.translator.trans('fof-releases.admin.settings.username_mappings_help'),
        type: 'textarea',
        placeholder: '{\n  "github_username": "flarum_username",\n  "another_github": "another_flarum"\n}',
      })
    )
    .permission(
      () => ({
        icon: 'fas fa-rocket',
        label: app.translator.trans('fof-releases.admin.permissions.publish_release_updates_label'),
        permission: 'fof-releases.publishReleaseUpdates',
      }),
      'reply'
    )
];
