jQuery(function($) {
    'use strict';

    $('#wal-redeem-points-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var messageDiv = $('#wal-redeem-message');
        var submitButton = form.find('button');
        var pointsInput = $('#points_to_redeem');
        var points = pointsInput.val();

        // Basic validation
        if (!points || parseInt(points) <= 0) {
            messageDiv.text('Please enter a valid number of points.').css('color', 'red');
            return;
        }

        // Disable button to prevent double submission
        submitButton.prop('disabled', true);
        messageDiv.text('Processing...').css('color', 'inherit');

        $.ajax({
            type: 'POST',
            url: loyaltyRedemption.ajax_url,
            data: {
                action: 'wal_redeem_points',
                points: points,
                nonce: loyaltyRedemption.nonce
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.html(response.data.message).css('color', 'green');
                    // Reload the cart to show the new coupon
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    messageDiv.html(response.data.message).css('color', 'red');
                    submitButton.prop('disabled', false); // Re-enable button on failure
                }
            },
            error: function() {
                messageDiv.text('An unexpected error occurred. Please try again.').css('color', 'red');
                submitButton.prop('disabled', false); // Re-enable button on error
            }
        });
    });
});
