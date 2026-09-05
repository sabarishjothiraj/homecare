/**
 * Client-Side Form Validation Library
 * Integrates with Bootstrap 5 and existing AJAX form submission
 */

(function() {
    'use strict';

    /**
     * Validation rules configuration
     */
    const validationRules = {
        required: {
            validate: (value) => value !== null && value !== undefined && value.toString().trim() !== '',
            message: 'This field is required.'
        },
        email: {
            validate: (value) => !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            message: 'Please enter a valid email address.'
        },
        url: {
            validate: (value) => !value || /^https?:\/\/.+\..+/.test(value),
            message: 'Please enter a valid URL (e.g., https://example.com).'
        },
        numeric: {
            validate: (value) => !value || /^-?\d*\.?\d+$/.test(value),
            message: 'Please enter a valid number.'
        },
        integer: {
            validate: (value) => !value || /^-?\d+$/.test(value),
            message: 'Please enter a valid integer.'
        },
        min: {
            validate: (value, param) => !value || parseFloat(value) >= parseFloat(param),
            message: (param) => `Value must be at least ${param}.`
        },
        max: {
            validate: (value, param) => !value || parseFloat(value) <= parseFloat(param),
            message: (param) => `Value must not exceed ${param}.`
        },
        minlength: {
            validate: (value, param) => !value || value.length >= parseInt(param),
            message: (param) => `Please enter at least ${param} characters.`
        },
        maxlength: {
            validate: (value, param) => !value || value.length <= parseInt(param),
            message: (param) => `Please enter no more than ${param} characters.`
        },
        date: {
            validate: (value) => !value || !isNaN(Date.parse(value)),
            message: 'Please enter a valid date.'
        },
        after: {
            validate: (value, param, form) => {
                if (!value) return true;
                const compareField = form.querySelector(`[name="${param}"]`);
                if (!compareField || !compareField.value) return true;
                return new Date(value) > new Date(compareField.value);
            },
            message: (param) => `Date must be after ${param.replace('_', ' ')}.`
        },
        image: {
            validate: (file) => {
                if (!file || !file.files || !file.files[0]) return true;
                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
                return allowedTypes.includes(file.files[0].type);
            },
            message: 'Please select a valid image file (JPEG, PNG, GIF, or WebP).'
        },
        filesize: {
            validate: (file, param) => {
                if (!file || !file.files || !file.files[0]) return true;
                const maxSize = parseInt(param) * 1024; // param in KB
                return file.files[0].size <= maxSize;
            },
            message: (param) => `File size must not exceed ${Math.round(param / 1024)}MB.`
        },
        phone: {
            validate: (value) => !value || /^[\d\s\-\+\(\)]+$/.test(value),
            message: 'Please enter a valid phone number.'
        },
        in: {
            validate: (value, param) => {
                if (!value) return true;
                const allowedValues = param.split(',');
                return allowedValues.includes(value);
            },
            message: (param) => `Please select one of: ${param}.`
        }
    };

    /**
     * Parse validation rules from data attributes
     */
    function parseValidationRules(field) {
        const rules = [];

        // Required
        if (field.hasAttribute('required') || field.dataset.required === 'true') {
            rules.push({ rule: 'required' });
        }

        // Type-based validation
        if (field.type === 'email') {
            rules.push({ rule: 'email' });
        }
        if (field.type === 'url') {
            rules.push({ rule: 'url' });
        }
        if (field.type === 'number') {
            rules.push({ rule: 'numeric' });
        }
        if (field.type === 'date' || field.type === 'datetime-local') {
            rules.push({ rule: 'date' });
        }

        // Min/Max for numbers
        if (field.min) {
            rules.push({ rule: 'min', param: field.min });
        }
        if (field.max) {
            rules.push({ rule: 'max', param: field.max });
        }

        // Min/Max length for text
        if (field.minLength && field.minLength > 0) {
            rules.push({ rule: 'minlength', param: field.minLength });
        }
        if (field.maxLength && field.maxLength > 0) {
            rules.push({ rule: 'maxlength', param: field.maxLength });
        }

        // Custom data attributes
        if (field.dataset.validate) {
            const customRules = field.dataset.validate.split('|');
            customRules.forEach(ruleStr => {
                const [ruleName, param] = ruleStr.split(':');
                rules.push({ rule: ruleName, param });
            });
        }

        // File validation
        if (field.type === 'file') {
            if (field.accept && field.accept.includes('image')) {
                rules.push({ rule: 'image' });
            }
            if (field.dataset.maxSize) {
                rules.push({ rule: 'filesize', param: field.dataset.maxSize });
            }
        }

    /**
     * Validate a single field
     */
    function validateField(field, form) {
        const rules = parseValidationRules(field);
        const value = field.type === 'checkbox' ? field.checked : field.value;
        const errors = [];

        rules.forEach(({ rule, param }) => {
            const validator = validationRules[rule];
            if (!validator) return;

            const isValid = validator.validate(
                field.type === 'file' ? field : value,
                param,
                form
            );

            if (!isValid) {
                const message = typeof validator.message === 'function'
                    ? validator.message(param)
                    : validator.message;
                errors.push(message);
            }
        });

        return errors;
    }

    /**
     * Display validation errors for a field
     */
    function showFieldErrors(field, errors) {
        // Remove existing validation states
        field.classList.remove('is-invalid', 'is-valid');

        // Remove existing error messages
        const existingFeedback = field.parentElement.querySelector('.invalid-feedback.client-validation');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        if (errors.length > 0) {
            // Add invalid state
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');

            // Create error message element
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback client-validation';
            feedback.style.display = 'block';
            feedback.textContent = errors[0]; // Show first error

            // Set aria-describedby for accessibility
            const feedbackId = `${field.id || field.name}-error`;
            feedback.id = feedbackId;
            field.setAttribute('aria-describedby', feedbackId);

            // Insert after field
            field.parentElement.appendChild(feedback);
        } else if (field.value || field.type === 'checkbox') {
            // Add valid state only if field has value
            field.classList.add('is-valid');
            field.setAttribute('aria-invalid', 'false');
        }
    }

    /**
     * Clear validation errors for a field
     */
    function clearFieldErrors(field) {
        field.classList.remove('is-invalid', 'is-valid');
        field.removeAttribute('aria-invalid');
        field.removeAttribute('aria-describedby');

        const existingFeedback = field.parentElement.querySelector('.invalid-feedback.client-validation');
        if (existingFeedback) {
            existingFeedback.remove();
        }
    }

    /**
     * Validate entire form
     */
    function validateForm(form) {
        let isValid = true;
        let firstInvalidField = null;

        // Get all form fields
        const fields = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), select, textarea');

        fields.forEach(field => {
            // Skip disabled fields
            if (field.disabled) return;

            const errors = validateField(field, form);
            showFieldErrors(field, errors);

            if (errors.length > 0) {
                isValid = false;
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
            }
        });

        // Scroll to first invalid field
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidField.focus();
        }

        return isValid;
    }

    /**
     * Setup real-time validation for a field
     */
    function setupFieldValidation(field, form) {
        // Validate on blur
        field.addEventListener('blur', function() {
            if (this.value || this.type === 'checkbox' || this.classList.contains('is-invalid')) {
                const errors = validateField(this, form);
                showFieldErrors(this, errors);
            }
        });

        // Validate on input for fields that already have errors
        field.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                const errors = validateField(this, form);
                showFieldErrors(this, errors);
            }
        });

        // Special handling for file inputs
        if (field.type === 'file') {
            field.addEventListener('change', function() {
                const errors = validateField(this, form);
                showFieldErrors(this, errors);
            });
        }

        // Special handling for select elements
        if (field.tagName === 'SELECT') {
            field.addEventListener('change', function() {
                if (this.classList.contains('is-invalid')) {
                    const errors = validateField(this, form);
                    showFieldErrors(this, errors);
                }
            });
        }
    }

    /**
     * Initialize validation for a form
     */
    function initFormValidation(form) {
        // Get all form fields
        const fields = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), select, textarea');

        // Setup real-time validation for each field
        fields.forEach(field => {
            setupFieldValidation(field, form);
        });

        // Intercept form submission
        form.addEventListener('submit', function(e) {
            // Clear all previous client-side errors
            fields.forEach(field => {
                const clientFeedback = field.parentElement.querySelector('.invalid-feedback.client-validation');
                if (clientFeedback) {
                    clientFeedback.remove();
                }
                field.classList.remove('is-invalid', 'is-valid');
            });

            // Validate form
            const isValid = validateForm(form);

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();

                // Show toast notification
                if (window.showToast) {
                    showToast('Please correct the errors in the form before submitting.', 'danger');
                }

                return false;
            }
        }, true); // Use capture phase to run before AJAX handler
    }

    /**
     * Initialize all forms with validation
     */
    function initAllForms() {
        // Find all forms that should have validation
        const forms = document.querySelectorAll('form[data-ajax="true"], form.ajax-form, form.needs-validation');

        forms.forEach(form => {
            initFormValidation(form);
        });
    }

    /**
     * Public API
     */
    window.FormValidation = {
        init: initAllForms,
        initForm: initFormValidation,
        validateForm: validateForm,
        validateField: validateField,
        clearFieldErrors: clearFieldErrors
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllForms);
    } else {
        initAllForms();
    }

})();

