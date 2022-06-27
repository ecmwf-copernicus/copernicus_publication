<?php

namespace Drupal\copernicus_publication\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Command to trigger an event when managed file upload is complete.
 */
class ManagedFileUploadCompleteEventCommand implements CommandInterface {

  // Constructs a ReadMessageCommand object.
  public function __construct($prefix = null) {
    $this->prefix = $prefix;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'triggerManagedFileUploadComplete',
      'prefix' => $this->prefix
    ];
  }

}
