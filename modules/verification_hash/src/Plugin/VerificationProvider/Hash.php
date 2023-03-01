<?php

declare(strict_types=1);

namespace Drupal\verification_hash\Plugin\VerificationProvider;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\verification\Plugin\VerificationProviderBase;
use Drupal\verification_hash\VerificationHashManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hash verification provider plugin.
 *
 * @VerificationProvider(
 *   id = "hash",
 *   label = @Translation("Hash"),
 * )
 */
class Hash extends VerificationProviderBase implements ContainerFactoryPluginInterface {

  const HEADER_HASH = 'X-Verification-Hash';

  /**
   * Array of supported operations for this provider.
   */
  protected array $supportedOperations = [
    'register',
    'login',
    'set-password',
  ];

  /**
   * Construct new Hash provider plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
    protected ModuleHandlerInterface $moduleHandler,
    protected VerificationHashManagerInterface $verificationHashManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('logger.channel.verification'),
      $container->get('module_handler'),
      $container->get('verification_hash.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verifyOperation(Request $request, string $operation, AccountInterface $user, ?string $email = NULL): bool {
    $callback = $this->coreVerify($request, $operation, $user, $email);
    if ($callback === FALSE) {
      return FALSE;
    }

    return $callback(
      function (string $hash, int $timestamp, int $timeout, UserInterface $user) use ($operation, $email) {
        $isSuccess = $this->verificationHashManager->validateHash(
          $hash,
          $timestamp,
          $timeout,
          VerificationHashManagerInterface::MODE_OPERATION,
          $user,
          $operation,
          '',
          $email,
        );

        // Invalidate hash by updating the changed value of the user.
        if ($isSuccess) {
          $user->setChangedTime($this->time->getRequestTime())->save();
        }

        // Hash and timestamp are valid.
        return $isSuccess;
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verifyLogin(Request $request, string $operation, AccountInterface $user, ?string $email = NULL): bool {
    $callback = $this->coreVerify($request, $operation, $user, $email);
    if ($callback === FALSE) {
      return FALSE;
    }

    return $callback(
      function (string $hash, int $timestamp, int $timeout, UserInterface $user) use ($operation, $email) {
        $isSuccess = $this->verificationHashManager->validateHash(
          $hash,
          $timestamp,
          $timeout,
          VerificationHashManagerInterface::MODE_LOGIN,
          $user,
          $operation,
          '',
          $email,
        );

        // The login operation is finished after the login,
        // so we need to invalidate the core hash in the login step.
        if ($operation === 'login') {
          $user->setChangedTime($this->time->getRequestTime())->save();
        }

        return $isSuccess;
      }
    );
  }

  /**
   * Common verification logic.
   *
   * This method implements the common verification logic
   * used by all verification methods.
   *
   * Specific verification is passed as a closure to the
   * return function of this method.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $operation
   *   The operation name.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user object.
   *
   * @return \Closure|false
   *   A closure that executes specific verification logic
   *   or FALSE if preemtive checks have failed.
   */
  protected function coreVerify(Request $request, string $operation, AccountInterface $user) {
    // Only verify methods that are eligible for modifying resources.
    if ($request->isMethodCacheable()) {
      return FALSE;
    }

    // Unsupported operation.
    if (!in_array($operation, $this->getSupportedOperations())) {
      return FALSE;
    }

    $headerData = $this->getHeaderValue($request);

    // Verification data not provided.
    if (is_null($headerData)) {
      return FALSE;
    }

    // Load user.
    if ($user instanceof UserInterface === FALSE) {
      $user = User::load($user->id());
    }
    /** @var \Drupal\user\Entity\UserInterface $user */
    if (!$user) {
      $this->logger->error('Could not verify by hash: User not found!');

      return FALSE;
    }

    [$hash, $timestamp] = $headerData;

    $timeout = $this->getTimeout($operation, $user);

    return function (\Closure $innerVerify) use ($hash, $timestamp, $timeout, $user) {
      return $innerVerify($hash, (int) $timestamp, $timeout, $user);
    };
  }

  /**
   * Get verification data from request header.
   *
   * The header value contains the hash and timestamp separated by
   * two dollar signs:
   * _hash_$$_timestamp_.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array|null
   *   The verification data or NULL if not found.
   */
  protected function getHeaderValue(Request $request): ?array {
    // If required verification data is not found, return FALSE.
    if (!$request->headers->has(self::HEADER_HASH)) {
      return NULL;
    }

    $headerValue = $request->headers->get(self::HEADER_HASH);

    // Header value seems invalid.
    if (strpos($headerValue, '$$') === FALSE) {
      return NULL;
    }

    $parts = explode('$$', $headerValue);

    // Invalid value.
    if (count($parts) !== 2) {
      return NULL;
    }

    return $parts;
  }

  /**
   * Get the configured password reset timeout.
   *
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account.
   *
   * @return int
   *   The configured timeout in seconds.
   */
  protected function getTimeout(string $operation, AccountInterface $user) {
    $config = $this->configFactory->get('user.settings');
    $timeout = (int) ($config->get('password_reset_timeout') ?? 86400);

    $this->moduleHandler->alter('verification_hash_timeout', $timeout, $operation, $user);

    return $timeout;
  }

  /**
   * Get supported operations.
   *
   * Modules can use the hook_verification_provider_supported_operations() hook
   * to alter the list of supported operations.
   *
   * @return array
   *   An array of supported operations.
   */
  protected function getSupportedOperations(): array {
    $operations = $this->supportedOperations;

    $this->moduleHandler->alter('verification_hash_supported_operations', $operations);

    return $operations;
  }

}
