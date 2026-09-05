/**
 * Admin Panel AJAX Form Handler
 * Handles all form submissions via AJAX for better UX
 */

(function() {
    'use strict';

    // Get CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        // Remove existing toasts
        const existingToast = document.querySelector('.ajax-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `ajax-toast alert alert-${type} alert-dismissible fade show`;
        toast.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 5000);
    }

    /**
     * Display validation errors on form
     */
    function displayErrors(form, errors) {
        // Clear previous errors
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

        // Display new errors
        Object.keys(errors).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.classList.add('is-invalid');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = errors[fieldName][0];
                field.parentNode.appendChild(errorDiv);
            }
        });

        // Scroll to first error
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }

    /**
     * Set button loading state
     */
    function setButtonLoading(button, loading) {
        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    }

    /**
     * Handle AJAX form submission
     */
    function handleFormSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        const method = form.querySelector('input[name="_method"]')?.value || form.method.toUpperCase();
        
        // Set loading state
        setButtonLoading(submitButton, true);

        // Clear previous errors
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

        // Make AJAX request
        fetch(form.action, {
            method: method === 'GET' ? 'GET' : 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: method === 'GET' ? null : formData
        })
        .then(response => {
            return response.json().then(data => ({
                status: response.status,
                ok: response.ok,
                data: data
            }));
        })
        .then(({ status, ok, data }) => {
            setButtonLoading(submitButton, false);

            if (ok) {
                // Success
                showToast(data.message || 'Operation completed successfully!', 'success');

                if (data.open_in_new_tab) {
                    window.open(data.open_in_new_tab, '_blank');
                }
                
                // Handle redirect
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 500);
                } else if (data.reload) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            } else if (status === 422) {
                // Validation errors
                displayErrors(form, data.errors || {});
                showToast('Please fix the validation errors.', 'danger');
            } else {
                // Other errors
                showToast(data.message || 'An error occurred. Please try again.', 'danger');
            }
        })
        .catch(error => {
            setButtonLoading(submitButton, false);
            console.error('Form submission error:', error);
            showToast('An unexpected error occurred. Please try again.', 'danger');
        });
    }

    /**
     * Initialize AJAX forms
     */
    function initAjaxForms() {
        // Add ajax-form class to all forms that should use AJAX
        document.querySelectorAll('form[data-ajax="true"], form.ajax-form').forEach(form => {
            form.addEventListener('submit', handleFormSubmit);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAjaxForms);
    } else {
        initAjaxForms();
    }

    // Expose global function for manual initialization
    window.initAjaxForms = initAjaxForms;
})();

