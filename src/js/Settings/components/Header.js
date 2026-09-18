import { Settings as SettingsIcon } from 'lucide-react';

import { useSettings } from '../context/SettingsContext';

function Header({ showSave }) {
    const { save, saving } = useSettings();

    return (
        <div className="dsw-header">
            <div className="dsw-header__title">
                <div className="dsw-header__icon">
                    <SettingsIcon size={22} />
                </div>
                <div>
                    <h1>{wp.i18n.__('Settings', 'delivery-slots-for-woocommerce')}</h1>
                    <p>{wp.i18n.__('Configure delivery slot behavior and manage your data.', 'delivery-slots-for-woocommerce')}</p>
                </div>
            </div>

            {showSave && (
                <button type="button" className="dsw-btn dsw-btn--primary" disabled={saving} onClick={save}>
                    {saving ? 'Saving…' : 'Save changes'}
                </button>
            )}
        </div>
    );
}

export default Header;
