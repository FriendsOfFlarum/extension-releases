import app from 'flarum/admin/app';

export { default as extend } from './extend';
import addUsernameMappingsSettingComponent from './addUsernameMappingsSettingComponent';

app.initializers.add('fof-extension-releases', () => {
  addUsernameMappingsSettingComponent();
});
