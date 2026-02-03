// ... (rest of JS unchanged)
function updateMaxSeats() {
    var sel = document.getElementById('category_id');
    var i = sel.selectedIndex;
    var option = sel.options[i];
    var maxSeats = option.getAttribute('data-max-seats');
    var maxSpan = document.getElementById('max-seats-caption');
    var eventSeatsInput = document.getElementById('event_seats_input');
    var personsInput = document.getElementById('persons-input');
    if (maxSeats) {
        maxSpan.textContent = maxSeats;
        eventSeatsInput.setAttribute('max', maxSeats);
        personsInput.setAttribute('max', eventSeatsInput.value ? eventSeatsInput.value : maxSeats);
    } else {
        maxSpan.textContent = 'Choose category';
        eventSeatsInput.setAttribute('max', 9999);
        personsInput.removeAttribute('max');
    }
    if (!eventSeatsInput.value) {
        personsInput.value = '';
        document.getElementById('available-seats-span').textContent = '-';
    } else {
        // recalculate available seats
        var available = parseInt(eventSeatsInput.value) - (parseInt(personsInput.value) || 0);
        document.getElementById('available-seats-span').textContent = (available >= 0 ? available : 0);
        personsInput.setAttribute('max', eventSeatsInput.value);
    }
}

document.getElementById('category_id').addEventListener('change', function(){
    updateMaxSeats();
});

document.getElementById('event_seats_input').addEventListener('input', function(){
    var max = this.getAttribute('max');
    if (parseInt(this.value) > parseInt(max)) {
        this.value = max;
    }
    var personsInput = document.getElementById('persons-input');
    personsInput.setAttribute('max', this.value);
    // Clear persons if more than event_seats
    if (parseInt(personsInput.value) > parseInt(this.value)) {
        personsInput.value = this.value;
    }
    var available = parseInt(this.value || 0) - (parseInt(personsInput.value) || 0);
    document.getElementById('available-seats-span').textContent = (!isNaN(available) && available >= 0 ? available : 0);
});

document.getElementById('persons-input').addEventListener('input', function(){
    var eventSeats = parseInt(document.getElementById('event_seats_input').value) || 0;
    var val = parseInt(this.value) || 0;
    if (val > eventSeats) {
        this.value = eventSeats;
        val = eventSeats;
    }
    var avail = eventSeats - val;
    document.getElementById('available-seats-span').textContent = (avail >= 0 ? avail : 0);
});

$(document).ready(function(){
    function isValidImg(filename) {
        return (/\.(jpe?g|png|gif|webp)$/i).test(filename);
    }

    $('#title').on('input blur', function() {
        var val = $(this).val().trim();
        if (val.length < 3) {
            $('#title-error').text("Event title must be at least 3 characters.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#title-error').hide();
            $(this).removeClass("input-invalid");
        }
    });
    $('#description').on('input blur', function() {
        var val = $(this).val().trim();
        if (val.length < 8) {
            $('#description-error').text("Description is required (min 8 chars).").show();
            $(this).addClass("input-invalid");
        } else {
            $('#description-error').hide();
            $(this).removeClass("input-invalid");
        }
    });
    $('#category_id').on('change blur', function() {
        var val = $(this).val();
        if (!val) {
            $('#category_id-error').text("Please select a category.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#category_id-error').hide();
            $(this).removeClass("input-invalid");
        }
    });
    $('#event_date').on('change blur', function() {
        var val = $(this).val();
        var valid = false;
        if (val) {
            var entered = new Date(val);
            var today = new Date();
            today.setHours(0,0,0,0);
            var minDate = new Date(today);
            minDate.setDate(minDate.getDate() + 7);
            entered.setHours(0,0,0,0);
            valid = entered >= minDate;
        }
        if (!val) {
            $('#event_date-error').text("Event date is required.").show();
            $(this).addClass("input-invalid");
        } else if (!valid) {
            $('#event_date-error').text("Event date must be at least one week from today.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#event_date-error').hide();
            $(this).removeClass("input-invalid");
        }
    });
    $('#start_time').on('change blur', function() {
        if (!$(this).val()) {
            $('#start_time-error').text("Start time is required.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#start_time-error').hide();
            $(this).removeClass("input-invalid");
        }
        $('#end_time').trigger('blur');
    });
    $('#end_time').on('change blur', function() {
        var s = $('#start_time').val();
        var e = $(this).val();
        if (!e) {
            $('#end_time-error').text("End time is required.").show();
            $(this).addClass("input-invalid");
        } else if (s && e && s >= e) {
            $('#end_time-error').text("End time must be after start time.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#end_time-error').hide();
            $(this).removeClass("input-invalid");
        }
    });

    $('#event_seats_input').on('input blur', function() {
        var cat_max = parseInt($('#category_id option:selected').attr('data-max-seats') || '9999', 10);
        var val = parseInt($(this).val(),10);
        if (!val || val < 1) {
            $('#event_seats-error').text("Event seats must be at least 1.").show();
            $(this).addClass("input-invalid");
        } else if (val > cat_max) {
            $('#event_seats-error').text("Cannot exceed max seats for this category.").show();
            $(this).addClass("input-invalid");
            $(this).val(cat_max);
        } else {
            $('#event_seats-error').hide();
            $(this).removeClass("input-invalid");
        }
        $('#persons-input').attr('max', val || cat_max);
        var personsVal = parseInt($('#persons-input').val(), 10) || 0;
        if (personsVal > val) {
            $('#persons-input').val(val);
            personsVal = val;
        }
        var available = val - personsVal;
        $('#available-seats-span').text((available >= 0 ? available : 0));
    });

    $('#persons-input').on('input blur', function() {
        var eventSeats = parseInt($('#event_seats_input').val(), 10) || 0;
        var val = parseInt($(this).val(), 10) || 0;
        if (!val || val < 1) {
            $('#persons-error').text("You must book at least 1 seat.").show();
            $(this).addClass("input-invalid");
        } else if (val > eventSeats) {
            $('#persons-error').text("Cannot exceed the event seats you set.").show();
            $(this).addClass("input-invalid");
            $(this).val(eventSeats);
            val = eventSeats;
        } else {
            $('#persons-error').hide();
            $(this).removeClass("input-invalid");
        }
        var available = eventSeats - val;
        $('#available-seats-span').text((available >= 0 ? available : 0));
    });

    $('#banner_image').on('change blur', function() {
        var files = this.files;
        if (!files || files.length === 0) {
            $('#banner_image-error').text("Please select a banner image.").show();
            $(this).addClass("input-invalid");
        }
        else if (!isValidImg(files[0].name)) {
            $('#banner_image-error').text("Invalid image format. Allowed: jpg, jpeg, png, gif, webp.").show();
            $(this).addClass("input-invalid");
        }
        else {
            $('#banner_image-error').hide();
            $(this).removeClass("input-invalid");
        }
    });
    $('#gallery_images').on('change blur', function() {
        var files = this.files;
        var valid = true;
        for (var i = 0; i < files.length; i++) {
            if (!isValidImg(files[i].name)) {
                valid = false;
                break;
            }
        }
        if (!valid) {
            $('#gallery_images-error').text("Invalid image format in gallery. Allowed: jpg, jpeg, png, gif, webp.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#gallery_images-error').hide();
            $(this).removeClass("input-invalid");
        }
    });
    $('#reg_deadline').on('change blur', function() {
        var val = $(this).val();
        var eventDateVal = $('#event_date').val();
        var valid = false;
        if (val && eventDateVal) {
            var deadline = new Date(val);
            var eventDate = new Date(eventDateVal);
            var today = new Date();
            today.setHours(0,0,0,0);
            valid = (deadline <= eventDate && deadline >= today);
        }
        if (!val) {
            $('#reg_deadline-error').text("Registration deadline required.").show();
            $(this).addClass("input-invalid");
        } else if (!valid) {
            $('#reg_deadline-error').text("Deadline must be before event date and not in the past.").show();
            $(this).addClass("input-invalid");
        } else {
            $('#reg_deadline-error').hide();
            $(this).removeClass("input-invalid");
        }
    });

    $('#create-event-form').on('submit', function(e){
        $('#title').trigger('blur');
        $('#description').trigger('blur');
        $('#category_id').trigger('blur');
        $('#event_date').trigger('blur');
        $('#start_time').trigger('blur');
        $('#end_time').trigger('blur');
        $('#event_seats_input').trigger('blur');
        $('#persons-input').trigger('blur');
        $('#banner_image').trigger('blur');
        $('#gallery_images').trigger('blur');
        $('#reg_deadline').trigger('blur');
        if ($('.input-invalid').length > 0) {
            e.preventDefault();
        }
    });
});
