import { usePage } from '@inertiajs/vue3';

export function useTranslations() {
    const page = usePage();
    
    const __ = (key, fallback = key) => {
        const translations = page.props.translations;
        
        return translations[key] || fallback;
    };
    
    return { __ };
}