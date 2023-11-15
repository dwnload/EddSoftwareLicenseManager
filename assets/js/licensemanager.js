/* global jQuery, ajaxurl, EddLicenseManager */
(function ($) {
  'use strict'

  $(document).ready(function () {
    $('a[id^="EddSoftwareLicenseManagerButton"]').on('click', function (e) {
      e.preventDefault()
      const $this = $(this)
      const $element = $('input[name$="[' + $this.data('plugin_id') + ']"]')

      if ($element.val().length === 0) {
        return
      }

      $.ajax({
        method: 'POST',
        url: ajaxurl,
        data: {
          action: EddLicenseManager.action,
          license_key: $element.val(),
          nonce: EddLicenseManager.nonce,
          plugin_action: $this.data('action'),
          plugin_id: $this.data('plugin_id')
        },
        beforeSend: function () {
          $this.attr('disabled', true)
          $('<img class="EddLicenseLoader" src="' + EddLicenseManager.loading + '" height="16" width="16">').insertAfter($this)
        },
        success: function (response) {
          if (typeof response.success !== 'undefined' && response.success) {
            $this.closest('form').submit()
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