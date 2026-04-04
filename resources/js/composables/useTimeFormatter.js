export function useTimeFormatter() {
    const formatDate = (iso) => {
        if (!iso) return '';
        try {
            return new Intl.DateTimeFormat(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }).format(new Date(iso));
        } catch (e) { return ''; }
    };

    const formatTime = (iso) => {
        if (!iso) return '??:??';
        try {
            return new Intl.DateTimeFormat(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(new Date(iso));
        } catch (e) { return '??:??'; }
    };

    const formatToLocal = (iso) => {
        if (!iso) return '';
        try {
            return new Intl.DateTimeFormat(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).format(new Date(iso));
        } catch (e) { return ''; }
    };

    const formatRange = (startIso, endIso) => {
        let text = formatToLocal(startIso);
        if (endIso) {
            const endText = formatToLocal(endIso);
            if (endText) text += ' - ' + endText;
        }
        return text;
    };

    return { formatDate, formatTime, formatToLocal, formatRange };
}
