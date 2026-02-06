function openPwModal() {
    document.getElementById("passwordModal").classList.add("active");
    document.body.classList.add("modal-open");
    setTimeout(function() {
        var firstInput = document.querySelector('#passwordModal input[type="password"]');
        if (firstInput) firstInput.focus();
    }, 120);
}
function closePwModal() {
    document.getElementById("passwordModal").classList.remove("active");
    document.body.classList.remove("modal-open");
}

document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById("passwordModal");
    if (modal) {
        modal.addEventListener("mousedown", function(e){
            if(e.target===modal) closePwModal();
        });
        document.addEventListener("keydown", function(ev) {
            if (modal.classList.contains("active") && ev.key === "Escape") {
                closePwModal();
            }
        });
    }

    // Profile Form jQuery Validation
    $("form[method=post]").on("submit", function(e) {
        var $f = $(this);
        // Detect which form is being submitted (profile or password)
        if ($f.find("input[name='save_profile']").length) {
            // Edit Profile validation
            var name = $f.find("[name='user_name']").val().trim();
            var phone = $f.find("[name='user_phone_number']").val().trim();
            var nameRegex = /^[a-zA-Z\s]+$/;
            var phoneRegex = /^\d{10}$/;
            var error = '';
            if (!name.length) {
                error = "Name cannot be empty.";
            } else if (!nameRegex.test(name)) {
                error = "Name must contain only letters and spaces.";
            } else if (!phoneRegex.test(phone)) {
                error = "Phone number must be exactly 10 digits.";
            }
            if (error) {
                showFormError($f, error);
                e.preventDefault();
            }
        } else if ($f.find("input[name='change_password']").length) {
            // Change Password validation
            var curr = $f.find("#current_password").val();
            var pw1 = $f.find("#new_password").val();
            var pw2 = $f.find("#confirm_password").val();
            var pwRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
            var message = "";
            if (!(curr && pw1 && pw2)) {
                message = "All password fields are required.";
            } else if (!pwRegex.test(pw1)) {
                message = "Password must have at least 8 chars, an uppercase, a lowercase, a digit and a special symbol.";
            } else if (pw1 !== pw2) {
                message = "New password and confirmation do not match.";
            }
            if (message) {
                showFormError($f, message);
                e.preventDefault();
            }
        }
    });

    function showFormError($form, msg) {
        var $old = $form.find(".err-msg-js");
        if ($old.length) $old.remove();
        $("<div class='err-msg err-msg-js'>" + $("<div>").text(msg).html() + "</div>").insertBefore($form.find(".profile-btn-row,.profile-btn-row.profile-btn-row-modal").last());
    }
});
