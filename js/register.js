$(document).ready(function(){
    $.validator.addMethod("strongPassword", function(value, element) {
        return this.optional(element)
            || /[a-z]/.test(value)    
            && /[A-Z]/.test(value)    
            && /[0-9]/.test(value) 
            && /[^A-Za-z0-9]/.test(value); 
    }, "Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character.");

    $('#registerForm').validate({
        rules: {
            fullname: {
                required: true,
                minlength: 3
            },
            username: {
                required: true,
                minlength: 3,
                maxlength: 20
            },
            email: {
                required: true,
                email: true
            },
            phone: {
                required: true,
                digits: true,
                minlength: 10,
                maxlength: 15
            },
            address: {
                required: true,
                minlength: 5
            },
            dob: {
                required: true,
                date: true
            },
            password: {
                required: true,
                minlength: 6,
                strongPassword: true
            },
            confirm_password: {
                required: true,
                equalTo: "#password"
            }
        },
        messages: {
            fullname: {
                required: "Please enter your full name",
                minlength: "Full name must be at least 3 characters"
            },
            username: {
                required: "Please enter a username",
                minlength: "Username must be at least 3 characters",
                maxlength: "Username must be less than 20 characters"
            },
            email: {
                required: "Please enter your email address",
                email: "Please enter a valid email address"
            },
            phone: {
                required: "Please provide your phone number",
                digits: "Phone number must be digits only",
                minlength: "Phone must be at least 10 digits",
                maxlength: "Phone must be no more than 15 digits"
            },
            address: {
                required: "Please provide your address",
                minlength: "Address must be at least 5 characters"
            },
            dob: {
                required: "Please provide your date of birth",
                date: "Please enter a valid date"
            },
            password: {
                required: "Please provide a password",
                minlength: "Password must be at least 6 characters",
                strongPassword: "Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character."
            },
            confirm_password: {
                required: "Please confirm your password",
                equalTo: "Passwords do not match"
            }
        },
        errorElement: 'span',
        errorClass: 'error',
        highlight: function(element) {
            $(element).addClass('error');
        },
        unhighlight: function(element) {
            $(element).removeClass('error');
        }
    });
});
