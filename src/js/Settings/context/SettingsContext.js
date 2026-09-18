import { createContext, useContext, useEffect, useRef, useState } from 'react';

import { saveSettings } from '../api';
import { notifySuccess, notifyError } from '../../Dashboard/swal';

const AUTOSAVE_DELAY = 800;

const getInitialSettings = () => (typeof window !== 'undefined' && window.dsw && window.dsw.settings) || {};

const SettingsContext = createContext(null);

export function SettingsProvider({ children }) {
    const [settings, setSettings] = useState(getInitialSettings);
    const [saving, setSaving] = useState(false);
    const autoSaveTimer = useRef(null);

    useEffect(() => () => clearTimeout(autoSaveTimer.current), []);

    const save = async (payload = settings) => {
        setSaving(true);

        try {
            const saved = await saveSettings(payload);
            setSettings(saved);
            notifySuccess('Settings saved.');
        } catch (error) {
            notifyError(error.message);
        } finally {
            setSaving(false);
        }
    };

    const updateField = (key, value) => {
        setSettings((current) => {
            const next = { ...current, [key]: value };

            clearTimeout(autoSaveTimer.current);

            if (key === 'auto_save') {
                // Persist the toggle itself immediately so the new behavior
                // takes effect right away, not on the next manual save.
                save(next);
            } else if (next.auto_save) {
                autoSaveTimer.current = setTimeout(() => save(next), AUTOSAVE_DELAY);
            }

            return next;
        });
    };

    return (
        <SettingsContext.Provider value={{ settings, updateField, save, saving }}>
            {children}
        </SettingsContext.Provider>
    );
}

export function useSettings() {
    const context = useContext(SettingsContext);

    if (!context) {
        throw new Error('useSettings must be used within a SettingsProvider.');
    }

    return context;
}
