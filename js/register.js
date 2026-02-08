// Register Page JavaScript

$(document).ready(function () {
    initPasswordToggles();
    initPasswordStrength();
    initFormValidation();
});

function initPasswordToggles() {
    $('#togglePassword').on('click', function () {
        togglePasswordVisibility('#password', $(this));
    });

    $('#toggleConfirmPassword').on('click', function () {
        togglePasswordVisibility('#confirmPassword', $(this));
    });
}

function togglePasswordVisibility(inputSelector, $button) {
    const $input = $(inputSelector);
    const type = $input.attr('type') === 'password' ? 'text' : 'password';
    $input.attr('type', type);

    $button.find('.eye-open').toggle(type === 'password');
    $button.find('.eye-closed').toggle(type === 'text');
}

function initPasswordStrength() {
    $('#password').on('input', function () {
        const password = $(this).val();
        const strength = calculatePasswordStrength(password);
        updateStrengthUI(strength);
    });
}

function calculatePasswordStrength(password) {
    let score = 0;

    if (password.length === 0) {
        return { level: '', label: 'Password Strength' };
    }

    if (password.length >= 8) score++;
    if (password.length >= 12) score++;

    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    if (score <= 2) {
        return { level: 'weak', label: 'Weak' };
    } else if (score <= 3) {
        return { level: 'fair', label: 'Fair' };
    } else if (score <= 5) {
        return { level: 'good', label: 'Good' };
    } else {
        return { level: 'strong', label: 'Strong' };
    }
}

function updateStrengthUI(strength) {
    const $fill = $('#strengthFill');
    const $text = $('#strengthText');

    $fill.removeClass('weak fair good strong');

    if (strength.level) {
        $fill.addClass(strength.level);
    }

    $text.text(strength.label);
}

function initFormValidation() {
    $('#registerForm').on('submit', function (e) {
        e.preventDefault();

        resetFormErrors();

        const formData = getFormData();
        const errors = validateForm(formData);

        if (Object.keys(errors).length > 0) {
            displayErrors(errors);
            return;
        }

        submitRegistration(formData);
    });
}

function getFormData() {
    return {
        firstName: $('#firstName').val().trim(),
        lastName: $('#lastName').val().trim(),
        email: $('#email').val().trim(),
        username: $('#username').val().trim(),
        password: $('#password').val(),
        confirmPassword: $('#confirmPassword').val()
    };
}

function validateForm(data) {
    const errors = {};

    if (!data.firstName) {
        errors.firstName = 'First name is required';
    } else if (data.firstName.length < 2) {
        errors.firstName = 'First name must be at least 2 characters';
    }

    if (!data.lastName) {
        errors.lastName = 'Last name is required';
    } else if (data.lastName.length < 2) {
        errors.lastName = 'Last name must be at least 2 characters';
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!data.email) {
        errors.email = 'Email is required';
    } else if (!emailRegex.test(data.email)) {
        errors.email = 'Please enter a valid email address';
    }

    const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
    if (!data.username) {
        errors.username = 'Username is required';
    } else if (!usernameRegex.test(data.username)) {
        errors.username = 'Username must be 3-20 characters (letters, numbers, underscore only)';
    }

    if (!data.password) {
        errors.password = 'Password is required';
    } else if (data.password.length < 8) {
        errors.password = 'Password must be at least 8 characters';
    }

    if (!data.confirmPassword) {
        errors.confirmPassword = 'Please confirm your password';
    } else if (data.password !== data.confirmPassword) {
        errors.confirmPassword = 'Passwords do not match';
    }

    return errors;
}

function resetFormErrors() {
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback').text('');
    $('#registerAlert').addClass('d-none').removeClass('alert-success alert-danger');
}

function displayErrors(errors) {
    Object.keys(errors).forEach(field => {
        const $input = $(`#${field}`);
        const $error = $(`#${field}Error`);

        $input.addClass('is-invalid');
        $error.text(errors[field]);
    });
}

function showAlert(message, type) {
    const $alert = $('#registerAlert');
    $alert
        .removeClass('d-none alert-success alert-danger')
        .addClass(`alert-${type}`)
        .text(message);
}

function setLoading(loading) {
    const $btn = $('#registerBtn');
    const $text = $btn.find('.btn-text');
    const $loader = $btn.find('.btn-loader');

    $btn.prop('disabled', loading);
    $text.text(loading ? 'Creating Account...' : 'Create Account');
    $loader.toggleClass('d-none', !loading);
}

function submitRegistration(formData) {
    setLoading(true);

    $.ajax({
        url: 'php/register.php',
        type: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            setLoading(false);

            if (response.success) {
                showAlert(response.message, 'success');

                setTimeout(function () {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showAlert(response.message || 'Registration failed', 'danger');

                if (response.errors) {
                    displayErrors(response.errors);
                }
            }
        },
        error: function (xhr, status, error) {
            setLoading(false);

            let message = 'An error occurred. Please try again.';

            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch (e) {
                // Keep default message
            }

            showAlert(message, 'danger');
        }
    });
}
