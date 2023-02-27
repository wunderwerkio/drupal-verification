<?php

declare(strict_types=1);

namespace Drupal\verification\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Verification Provider plugin manager.
 */
class VerificationProviderManager extends DefaultPluginManager implements VerificationProviderManagerInterface {

  /**
   * The plugin instances.
   *
   * @var array
   */
  protected array $instances = [];

  /**
   * Constructor for VerificationProviderManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   *
   * @throws \Exception
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct('Plugin/VerificationProvider', $namespaces, $moduleHandler, 'Drupal\verification\Plugin\VerificationProviderInterface', 'Drupal\verification\Annotation\VerificationProvider');

    $this->alterInfo('verification_verification_provider_info');
    $this->setCacheBackend($cacheBackend, 'verification_verification_provider_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(array $ids = NULL): array {
    $instances = [];

    if (empty($ids)) {
      $ids = array_keys($this->getDefinitions());
    }

    foreach ($ids as $pluginId) {
      if (!isset($this->instances[$pluginId])) {
        $this->instances[$pluginId] = $this->createInstance($pluginId);
      }

      $instances[$pluginId] = $this->instances[$pluginId];
    }

    return $instances;
  }

}
