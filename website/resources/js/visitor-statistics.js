export function initVisitorStatistics() {
    const card = document.querySelector('[data-statistics-url]');
    if (!card) return;
    // Same output as the server-rendered numbers: thousands separators + Khmer digits ("១,២៣៤").
    const numbers = new Intl.NumberFormat('en-US');
    const khmerDigits = '០១២៣៤៥៦៧៨៩';
    const format = (value) => numbers.format(value).replace(/\d/g, (digit) => khmerDigits[Number(digit)]);
    let pending = false;
    const refresh = async () => {
        if (document.hidden || pending) return;
        pending = true;
        try {
            const response = await fetch(card.dataset.statisticsUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: AbortSignal.timeout(10000),
            });
            if (!response.ok) return;
            const statistics = await response.json();
            card.querySelectorAll('[data-stat]').forEach((element) => {
                const value = statistics[element.dataset.stat];
                if (Number.isSafeInteger(value) && value >= 0) element.textContent = format(value);
            });
        } catch {
            // Retain the server-rendered counts when offline or temporarily unavailable.
        } finally {
            pending = false;
        }
    };
    refresh();
    window.setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', refresh);
}
