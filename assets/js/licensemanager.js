/* global jQuery, ajaxurl, EddLicenseManager */
(function ($) {
    "use strict";

    $(document).ready(function () {
        $('input#EddSoftwareLicenseManagerButton').on('submit', function (e) {
            e.preventDefault();
            var $this = $(this);

            $.ajax({
                method: "POST",
                url: ajaxurl,
                data: {
                    action: EddLicenseManager.action,
                    nonce: EddLicenseManager.nonce
                },
                beforeSend: function () {
                    $this.attr('disabled', true);
                },
                success: function (response) {
                    if (typeof response.success !== 'undefined' && response.success) {
                        $this.slideUp();
                        document.location = response.url;
                    }
                    $this.attr('disabled', false);
                },
                fail: function () {
                    alert('Unknown Error');
                }
            });
        });
    });
}(jQuery));