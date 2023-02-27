<?php

declare(strict_types=1);

namespace Drupal\verification\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a Verification Provider annotation object.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class VerificationProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

}
