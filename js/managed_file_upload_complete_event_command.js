(function (Drupal, $) {

  "use strict";

  /**
   * Add new custom command.
   */
  Drupal.AjaxCommands.prototype.triggerManagedFileUploadComplete = function (ajax, response, status) {
    // Do stuff here after file upload is complete.
    // alert(Drupal.t("File upload complete!"));
    if (response.prefix) {
      var prefix = response.prefix[0];
      $('select[name=doi_prefix]>option[value=' + prefix + ']').attr('selected', true);
    }
  };

}(Drupal, jQuery));
