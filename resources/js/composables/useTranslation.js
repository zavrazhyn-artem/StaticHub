export function useTranslation() {
    const __ = (key, replace = {}) => {
        const translations = window.translations || {};
        let translation = translations[key] || key;

        Object.keys(replace).forEach(r => {
            translation = translation.replace(`{${r}}`, replace[r]);
            translation = translation.replace(`:${r}`, replace[r]);
        });

        return translation;
    };

    return { __ };
}
