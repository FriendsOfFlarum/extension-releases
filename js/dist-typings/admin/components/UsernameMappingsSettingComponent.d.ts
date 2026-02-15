import Component, { type ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
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
    view(vnode: Mithril.Vnode<UsernameMappingsSettingComponentAttrs, this>): Mithril.Children;
}
