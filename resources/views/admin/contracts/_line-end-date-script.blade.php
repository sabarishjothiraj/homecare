<script>
document.addEventListener('DOMContentLoaded', function() {
    const contractStart = document.getElementById('start_date');
    const durationType = document.getElementById('duration_type');
    const durationValue = document.getElementById('duration_value');
    const customEnd = document.getElementById('custom_end_date');
    const lineStart = document.getElementById('assignment_start_date');
    const lineEnd = document.getElementById('assignment_end_date');
    if (!durationType || !durationValue || !lineEnd) return;

    // Last value we filled in; a different value means the user typed their own date.
    let autofilled = null;

    function parseDate(value) {
        const [y, m, d] = (value || '').split('-').map(Number);
        return y && m && d ? { y, m: m - 1, d } : null;
    }

    function formatDate(date) {
        const pad = n => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    // Clamp to the target month's last day, like Carbon's addMonthsNoOverflow
    // (Jan 31 + 1 month is Feb 28, not Mar 3).
    function addMonthsNoOverflow(s, months) {
        const lastDay = new Date(s.y, s.m + months + 1, 0).getDate();
        return new Date(s.y, s.m + months, Math.min(s.d, lastDay));
    }

    // Mirrors ContractService::calculateEndDate (Carbon addDays/addWeeks/addMonthsNoOverflow/addYearsNoOverflow)
    function calculateEndDate(startValue, type, amount) {
        const s = parseDate(startValue);
        if (!s || !(amount >= 1)) return null;

        switch (type) {
            case 'daily': return formatDate(new Date(s.y, s.m, s.d + amount));
            case 'weekly': return formatDate(new Date(s.y, s.m, s.d + amount * 7));
            case 'monthly': return formatDate(addMonthsNoOverflow(s, amount));
            case 'yearly': return formatDate(addMonthsNoOverflow(s, amount * 12));
            default: return null;
        }
    }

    function update() {
        const userEdited = lineEnd.value && lineEnd.value !== autofilled;
        if (userEdited) return;

        const start = (lineStart && lineStart.value) || (contractStart && contractStart.value);
        const endDate = durationType.value === 'custom'
            ? (customEnd && customEnd.value) || null
            : calculateEndDate(start, durationType.value, parseInt(durationValue.value, 10));

        lineEnd.value = endDate || '';
        autofilled = lineEnd.value || null;
    }

    [contractStart, durationType, lineStart, customEnd].forEach(el => el && el.addEventListener('change', update));
    durationValue.addEventListener('input', update);
    update();
});
</script>
