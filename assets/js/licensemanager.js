/* global jQuery, ajaxurl, EddLicenseManager */
(function ($) {
  'use strict'

  $(document).ready(function () {
    $('input#EddSoftwareLicenseManagerButton').on('click', function (e) {
      e.preventDefault()
      const $this = $(this)

      $.ajax({
        method: 'POST',
        url: ajaxurl,
        data: {
          action: EddLicenseManager.action,
          license_key: $('input[name="' + EddLicenseManager.license_attr + '"]').val(),
          nonce: EddLicenseManager.nonce,
          plugin_action: $this.prop('name')
        },
        beforeSend: function () {
          $this.attr('disabled', true)
          $('<img class="EddLicenseLoader" src="' + EddLicenseManager.loading + '" height="16" width="16">').insertAfter($this)
        },
        success: function (response) {
          if (typeof response.success !== 'undefined' && response.success) {
            location.reload(true)
          }
          $this.attr('disabled', false)
          $('img[class="EddLicenseLoader"]').remove()
        },
        fail: function () {
          window.alert('Unknown Error')
        }
      })
    })
  })
}(jQuery))