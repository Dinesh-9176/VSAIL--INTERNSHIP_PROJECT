// Login Page JavaScript

$(document).ready(function () {
    checkExistingSession();
    initPasswordToggle();
    initFormValidation();
    loadRememberedCredentials();
});

function checkExistingSession() {
    const sessionData = localStorage.getItem('vsail_session');

    if (sessionData) {
        try {
            const session = JSON.parse(sessionData);

            $.ajax({
                url: 'php/login.php',
                type: 'POST',
                data: JSON.stringify({
                    action: 'validate_session',
                    token: session.token
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.valid) {
                        window.location.href = 'profile.html';
                    }
                },
                error: function () {
                    localStorage.removeItem('vsail_session');
                }
            });
        } catch (e) {
            localStorage.removeItem('vsail_session');
        }
    }
}

function initPasswordToggle() {
    $('#togglePassword').on('click', function () {
        const $input = $('#password');
        const type = $input.attr('type') === 'password' ? 'text' : 'password';
        $input.attr('type', type);

        $(this).find('.eye-open').toggle(type === 'password');
        $(this).find('.eye-closed').toggle(type === 'text');
    });
}

function initFormValidation() {
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        resetFormErrors();

        const formData = {
            username: $('#username').val().trim(),
            password: $('#password').val(),
            rememberMe: $('#rememberMe').is(':checked'),
            action: 'login'
        };

        const errors = validateForm(formData);

        if (Object.keys(errors).length > 0) {
            displayErrors(errors);
            return;
        }

        submitLogin(formData);
    });
}

function validateForm(data) {
    const errors = {};

    if (!data.username) {
        errors.username = 'Username or email is required';
    }

    if (!data.password) {
        errors.password = 'Password is required';
    }

    return errors;
}

function resetFormErrors() {
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback').text('');
    $('#loginAlert').addClass('d-none').removeClass('alert-success alert-danger');
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
    const $alert = $('#loginAlert');
    $alert
        .removeClass('d-none alert-success alert-danger')
        .addClass(`alert-${type}`)
        .text(message);
}

function setLoading(loading) {
    const $btn = $('#loginBtn');
    const $text = $btn.find('.btn-text');
    const $loader = $btn.find('.btn-loader');

    $btn.prop('disabled', loading);
    $text.text(loading ? 'Signing In...' : 'Sign In');
    $loader.toggleClass('d-none', !loading);
}

function loadRememberedCredentials() {
    const remembered = localStorage.getItem('vsail_remember');

    if (remembered) {
        try {
            const data = JSON.parse(remembered);
            $('#username').val(data.username);
            $('#rememberMe').prop('checked', true);
        } catch (e) {
            localStorage.removeItem('vsail_remember');
        }
    }
}

function handleRememberMe(username, rememberMe) {
    if (rememberMe) {
        localStorage.setItem('vsail_remember', JSON.stringify({ username }));
    } else {
        localStorage.removeItem('vsail_remember');
    }
}

function submitLogin(formData) {
    setLoading(true);

    $.ajax({
        url: 'php/login.php',
        type: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            setLoading(false);

            if (response.success) {
                showAlert('Login successful! Redirecting...', 'success');

                handleRememberMe(formData.username, formData.rememberMe);

                const sessionData = {
                    token: response.token,
                    userId: response.userId,
                    username: response.username,
                    email: response.email,
                    firstName: response.firstName,
                    lastName: response.lastName,
                    loginTime: new Date().toISOString()
                };

                localStorage.setItem('vsail_session', JSON.stringify(sessionData));

                setTimeout(function () {
                    window.location.href = 'profile.html';
                }, 1500);
            } else {
                showAlert(response.message || 'Login failed', 'danger');

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
