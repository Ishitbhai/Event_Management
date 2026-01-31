
    $('#changePwForm').on('submit', function(e) {
        let valid = true;
        $('.err').remove();
        let oldpw = $('#old_password').val();
        let newpw = $('#new_password').val();
        let confpw = $('#confirm_password').val();
        
        // Validation
    if (!oldpw) {
        $('#old_password').after('<div class="err">Please enter your current password.</div>');
        valid = false;
    }
    if (!newpw) {
        $('#new_password').after('<div class="err">Please enter a new password.</div>');
        valid = false;
    } else {
        // Enhanced strength requirements (match PHP validation)
        if (newpw.length < 8) {
            $('#new_password').after('<div class="err">Password must be at least 8 characters.</div>');
            valid = false;
        } else if (
            !(/[A-Z]/.test(newpw)) ||
            !(/[a-z]/.test(newpw)) ||
            !(/[0-9]/.test(newpw)) ||
            !(/[^a-zA-Z0-9]/.test(newpw))
        ) {
            $('#new_password').after('<div class="err">Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.</div>');
            valid = false;
        }
    }
    if (!confpw) {
        $('#confirm_password').after('<div class="err">Please confirm your new password.</div>');
        valid = false;
    } else if (newpw && newpw !== confpw) {
        $('#confirm_password').after('<div class="err">Passwords do not match.</div>');
        valid = false;
    }

    if (!valid) e.preventDefault();
});
