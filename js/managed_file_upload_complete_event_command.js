(function (Drupal, $) {

  "use strict";

  /**
   * Add new custom command.
   */
  Drupal.AjaxCommands.prototype.triggerManagedFileUploadComplete = function (ajax, response, status) {
    if (
      response.prefix &&
      $('select[name="doi_prefix"]>option[value="' + response.prefix + '"]').length > 0
    ) {

      $('select[name="doi_prefix"]>option[value="' + response.prefix + '"]').attr('selected', true);
      // use suffix only when prefix exists
      if (response.suffix) {
        $('input[name="doi_suffix"]').val(response.suffix);
      }
    }
  };

}(Drupal, jQuery));
