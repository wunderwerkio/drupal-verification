<?php

declare(strict_types=1);

namespace Drupal\verification\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Manages the Verification Provider plugins.
 */
interface VerificationProviderManagerInterface extends PluginManagerInterface {

  /**
   * Gets all verification provider plugin instances.
   *
   * @param array|null $ids
   *   (optional) An array of plugin IDs, or NULL to load all plugins.
   *
   * @return VerificationProviderInterface[]
   *   Returns array of all plugin instances.
   */
  public function getInstances(array $ids = NULL): array;

}
