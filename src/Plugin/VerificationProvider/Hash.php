<?php

declare(strict_types=1);

namespace Drupal\verification\Plugin\VerificationProvider;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\verification\Plugin\VerificationProviderBase;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function verifyRequest(Request $request, string $operation, AccountInterface $user, ?string $email = NULL): bool {
    // Only verify methods that are eligible for modifying resources.
    if ($request->isMethodCacheable()) {
      return FALSE;
    }

    // Unsupported operation.
    if (!in_array($operation, $this->supportedOperations)) {
      return FALSE;
    }

    $headerData = $this->getHeaderValue($request);

    // Verification data not provided.
    if (is_null($headerData)) {
      return FALSE;
    }

    [$hash, $timestamp] = $headerData;

    $timeout = $this->getTimeout();
    $currentTime = $this->time->getRequestTime();

    // Hash is expired.
    if ($currentTime < $timestamp || $timeout < $currentTime - $timestamp) {
      return FALSE;
    }

    $referenceHash = $this->createHash($user, (int) $timestamp, $email);
    if (!$referenceHash) {
      $this->logger->critical('Hash verification failed: User for account with id %id could not be loaded!', [
        '%id' => $user->id(),
      ]);

      return FALSE;
    }

    // Hash is not valid.
    if (!hash_equals($hash, $referenceHash)) {
      return FALSE;
    }

    // Hash and timestamp are valid.
    return TRUE;
  }

  /**
   * Create hash value.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to generate the hash for.
   * @param int $timestamp
   *   The timestamp of the hash.
   * @param string|null $email
   *   (optional) E-Mail address.
   *
   * @return string|null
   *   The generated hash or NULL if the user could not be loaded.
   */
  protected function createHash(AccountInterface $user, int $timestamp, ?string $email = NULL) {
    // If explicit email address is set, create a
    // cloned user object with the explicit email addre to
    // create the correct hash value.
    if ($email) {
      // Load user if needed.
      if ($user instanceof UserInterface === FALSE) {
        $user = User::load($user->id());
      }

      /** @var \Drupal\user\Entity\UserInterface $user */

      // Failsafe. Should normally never happen.
      if (!$user) {
        return NULL;
      }

      $clonedUser = clone $user;
      $clonedUser->setEmail($email);

      return user_pass_rehash($clonedUser, $timestamp);
    }

    return user_pass_rehash($user, $timestamp);
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
   * @return int
   *   The configured timeout in seconds.
   */
  protected function getTimeout() {
    $config = $this->configFactory->get('user.settings');

    return (int) $config->get('password_reset_timeout') ?? 86400;
  }

}
