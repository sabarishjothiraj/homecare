<script>
document.addEventListener('DOMContentLoaded', function() {
    const method = document.getElementById('payment_method');
    const wrapper = document.getElementById('payment-details-wrapper');
    const input = document.getElementById('payment_details');
    const label = document.getElementById('payment-details-label');
    const requiredMark = document.getElementById('payment-details-required');
    if (!method || !wrapper || !input) return;

    const config = {
        cheque: { label: 'Cheque Details', placeholder: 'Cheque no., bank name, cheque date', required: true },
        bank_transfer: { label: 'Transaction Details', placeholder: 'Transaction / UTR no., bank name', required: true },
        online: { label: 'Transaction ID', placeholder: 'UPI / online transaction reference', required: false },
    };

    function toggle() {
        const current = config[method.value];

        // Disabled fields are skipped by client validation and not submitted
        wrapper.hidden = !current;
        input.disabled = !current;
        if (!current) {
            window.FormValidation?.clearFieldErrors(input);
            return;
        }

        label.textContent = current.label;
        input.placeholder = current.placeholder;
        input.required = current.required;
        requiredMark.hidden = !current.required;
    }

    method.addEventListener('change', toggle);
    toggle();
});
</script>
