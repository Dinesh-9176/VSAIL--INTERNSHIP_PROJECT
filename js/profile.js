// Profile Page JavaScript

let originalProfileData = null;

$(document).ready(function () {
    checkSession();
    initLogout();
    initFormValidation();
    initResetButton();
});

function checkSession() {
    const sessionData = localStorage.getItem('vsail_session');

    if (!sessionData) {
        window.location.href = 'login.html';
        return;
    }

    try {
        const session = JSON.parse(sessionData);

        $.ajax({
            url: 'php/profile.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'validate_session',
                token: session.token
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.valid) {
                    loadProfile(session.token);
                } else {
                    handleInvalidSession();
                }
            },
            error: function () {
                displayBasicInfo(session);
                loadProfile(session.token);
            }
        });

    } catch (e) {
        handleInvalidSession();
    }
}

function handleInvalidSession() {
    localStorage.removeItem('vsail_session');
    window.location.href = 'login.html';
}

function displayBasicInfo(session) {
    const fullName = `${session.firstName || ''} ${session.lastName || ''}`.trim() || session.username;
    const initials = getInitials(fullName);

    $('#navUsername').text(session.username || 'User');
    $('#profileFullName').text(fullName);
    $('#profileEmail').text(session.email || '');
    $('#avatarInitials').text(initials);
    $('#sidebarUsername').text(session.username || '');
    $('#memberSince').text(formatDate(session.loginTime) || 'N/A');
}

function loadProfile(token) {
    $.ajax({
        url: 'php/profile.php',
        type: 'POST',
        data: JSON.stringify({
            action: 'get_profile',
            token: token
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                populateProfile(response.data);
            } else {
                const session = JSON.parse(localStorage.getItem('vsail_session'));
                displayBasicInfo(session);
            }
        },
        error: function () {
            const session = JSON.parse(localStorage.getItem('vsail_session'));
            if (session) {
                displayBasicInfo(session);
            }
        }
    });
}

function populateProfile(data) {
    originalProfileData = { ...data };

    const fullName = `${data.firstName || ''} ${data.lastName || ''}`.trim() || data.username;
    const initials = getInitials(fullName);

    $('#navUsername').text(data.username || 'User');
    $('#profileFullName').text(fullName);
    $('#profileEmail').text(data.email || '');
    $('#avatarInitials').text(initials);
    $('#sidebarUsername').text(data.username || '');
    $('#memberSince').text(formatDate(data.createdAt) || 'N/A');
    $('#lastUpdated').text(formatDate(data.updatedAt) || 'Never');

    $('#firstName').val(data.firstName || '');
    $('#lastName').val(data.lastName || '');
    $('#age').val(data.age || '');
    $('#dob').val(data.dob || '');
    $('#contact').val(data.contact || '');
    $('#address').val(data.address || '');
    $('#city').val(data.city || '');
    $('#country').val(data.country || '');
    $('#bio').val(data.bio || '');
}

function getInitials(name) {
    if (!name) return '?';

    const parts = name.split(' ').filter(p => p.length > 0);

    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    } else if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return '?';
}

function formatDate(dateStr) {
    if (!dateStr) return null;

    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return null;
    }
}

function initLogout() {
    $('#logoutBtn').on('click', function () {
        const session = JSON.parse(localStorage.getItem('vsail_session') || '{}');

        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            data: JSON.stringify({
                action: 'logout',
                token: session.token
            }),
            contentType: 'application/json',
            dataType: 'json',
            complete: function () {
                localStorage.removeItem('vsail_session');
                window.location.href = 'login.html';
            }
        });
    });
}

function initResetButton() {
    $('#resetBtn').on('click', function () {
        if (originalProfileData) {
            populateProfile(originalProfileData);
            showAlert('Changes have been reset', 'success');

            setTimeout(function () {
                $('#profileAlert').addClass('d-none');
            }, 2000);
        }
    });
}

function initFormValidation() {
    $('#profileForm').on('submit', function (e) {
        e.preventDefault();

        resetFormErrors();

        const formData = getFormData();
        const errors = validateForm(formData);

        if (Object.keys(errors).length > 0) {
            displayErrors(errors);
            return;
        }

        updateProfile(formData);
    });
}

function getFormData() {
    return {
        firstName: $('#firstName').val().trim(),
        lastName: $('#lastName').val().trim(),
        age: $('#age').val() ? parseInt($('#age').val()) : null,
        dob: $('#dob').val() || null,
        contact: $('#contact').val().trim(),
        address: $('#address').val().trim(),
        city: $('#city').val().trim(),
        country: $('#country').val().trim(),
        bio: $('#bio').val().trim()
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

    if (data.age !== null && (data.age < 1 || data.age > 150)) {
        errors.age = 'Please enter a valid age';
    }

    if (data.contact) {
        const phoneRegex = /^[\d\s\-\+\(\)]{10,20}$/;
        if (!phoneRegex.test(data.contact)) {
            errors.contact = 'Please enter a valid contact number';
        }
    }

    return errors;
}

function resetFormErrors() {
    $('.form-control').removeClass('is-invalid');
    $('.invalid-feedback').text('');
    $('#profileAlert').addClass('d-none').removeClass('alert-success alert-danger');
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
    const $alert = $('#profileAlert');
    $alert
        .removeClass('d-none alert-success alert-danger')
        .addClass(`alert-${type}`)
        .text(message);

    $('html, body').animate({
        scrollTop: $alert.offset().top - 100
    }, 300);
}

function setLoading(loading) {
    const $btn = $('#updateBtn');
    const $text = $btn.find('.btn-text');
    const $loader = $btn.find('.btn-loader');

    $btn.prop('disabled', loading);
    $text.text(loading ? 'Saving...' : 'Save Changes');
    $loader.toggleClass('d-none', !loading);
}

function updateProfile(formData) {
    const session = JSON.parse(localStorage.getItem('vsail_session') || '{}');

    if (!session.token) {
        showAlert('Session expired. Please login again.', 'danger');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
        return;
    }

    setLoading(true);

    const requestData = {
        action: 'update_profile',
        token: session.token,
        ...formData
    };

    $.ajax({
        url: 'php/profile.php',
        type: 'POST',
        data: JSON.stringify(requestData),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            setLoading(false);

            if (response.success) {
                showAlert('Profile updated successfully!', 'success');

                originalProfileData = { ...originalProfileData, ...formData };

                const updatedSession = {
                    ...session,
                    firstName: formData.firstName,
                    lastName: formData.lastName
                };
                localStorage.setItem('vsail_session', JSON.stringify(updatedSession));

                const fullName = `${formData.firstName} ${formData.lastName}`;
                $('#profileFullName').text(fullName);
                $('#avatarInitials').text(getInitials(fullName));
                $('#lastUpdated').text(formatDate(new Date().toISOString()));

            } else {
                showAlert(response.message || 'Failed to update profile', 'danger');

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
