import Component, { type ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import classList from 'flarum/common/utils/classList';

export interface UsernameMapping {
  platform: string;
  forum: string;
}

export interface UsernameMappingsSettingComponentAttrs extends ComponentAttrs {
  bidi?: (value?: UsernameMapping[]) => UsernameMapping[];
  stream?: (value?: UsernameMapping[]) => UsernameMapping[];
  label?: Mithril.Children;
  help?: Mithril.Children;
  className?: string;
}

export default class UsernameMappingsSettingComponent extends Component<UsernameMappingsSettingComponentAttrs> {
  view(vnode: Mithril.Vnode<UsernameMappingsSettingComponentAttrs, this>): Mithril.Children {
    const { label, help, className } = this.attrs;
    const bidi = this.attrs.bidi ?? this.attrs.stream;
    if (typeof bidi !== 'function') {
      return null;
    }
    const raw = bidi();
    // Support legacy format: {"github_user": "flarum_user"} -> [{platform, forum}]
    const mappings: UsernameMapping[] = Array.isArray(raw)
      ? raw
      : raw && typeof raw === 'object' && !Array.isArray(raw)
      ? Object.entries(raw).map(([platform, forum]) => ({ platform: String(platform), forum: String(forum) }))
      : [];

    return (
      <div className={classList('Form-group', 'UsernameMappingsSetting', className)}>
        {label && <label className="Form-group-label">{label}</label>}
        {help && <div className="helpText">{help}</div>}
        <div className="UsernameMappingsSetting-rows">
          {mappings.map((mapping, index) => (
            <div className="UsernameMappingsSetting-row" key={index}>
              <input
                type="text"
                className="FormControl UsernameMappingsSetting-input"
                placeholder={app.translator.trans('fof-releases.admin.settings.platform_username_placeholder')}
                value={mapping.platform}
                oninput={(e: InputEvent) => {
                  const target = e.target as HTMLInputElement;
                  const newMappings = [...mappings];
                  newMappings[index] = { ...mapping, platform: target.value };
                  bidi(newMappings);
                }}
              />
              <span className="UsernameMappingsSetting-arrow">→</span>
              <input
                type="text"
                className="FormControl UsernameMappingsSetting-input"
                placeholder={app.translator.trans('fof-releases.admin.settings.forum_username_placeholder')}
                value={mapping.forum}
                oninput={(e: InputEvent) => {
                  const target = e.target as HTMLInputElement;
                  const newMappings = [...mappings];
                  newMappings[index] = { ...mapping, forum: target.value };
                  bidi(newMappings);
                }}
              />
              <Button
                icon="fas fa-times"
                className="Button Button--icon Button--danger UsernameMappingsSetting-remove"
                onclick={() => {
                  const newMappings = mappings.filter((_, i) => i !== index);
                  bidi(newMappings);
                }}
                aria-label={app.translator.trans('core.admin.settings.remove_button')}
              />
            </div>
          ))}
        </div>
        <Button
          icon="fas fa-plus"
          className="Button Button--block UsernameMappingsSetting-add"
          onclick={() => {
            bidi([...mappings, { platform: '', forum: '' }]);
          }}
        >
          {app.translator.trans('fof-releases.admin.settings.add_mapping_button')}
        </Button>
      </div>
    );
  }
}
