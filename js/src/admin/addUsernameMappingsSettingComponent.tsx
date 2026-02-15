import { extend } from 'flarum/common/extend';
import FormGroup from 'flarum/common/components/FormGroup';
import type { IFormGroupAttrs } from 'flarum/common/components/FormGroup';
import UsernameMappingsSettingComponent from './components/UsernameMappingsSettingComponent';

export default function () {
  extend(FormGroup.prototype, 'customFieldComponents', function (items) {
    items.add('fof-releases.username-mappings', (attrs: IFormGroupAttrs) => {
      // FormGroup passes stream as bidi for custom components
      return <UsernameMappingsSettingComponent {...attrs} bidi={attrs.stream} />;
    });
  });
}
