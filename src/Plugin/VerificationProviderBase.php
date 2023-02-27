<?php

declare(strict_types=1);

namespace Drupal\verification\Plugin;

use Drupal\Component\Plugin\PluginBase;

/**
 * The base class for all verification plugins.
 */
abstract class VerificationProviderBase extends PluginBase implements VerificationProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->pluginDefinition['label'];
  }

}
