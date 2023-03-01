<?php

declare(strict_types=1);

namespace Drupal\verification_hash;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\user\UserInterface;

/**
 * Service to create and verify a verification hash.
 */
class VerificationHashManager implements VerificationHashManagerInterface {

  const SEPARATOR = ':';

  /**
   * {@inheritdoc}
   */
  public function createHash(
    UserInterface $account,
    string $operation,
    int $timestamp,
    string $additionalData = '',
    ?string $email = NULL,
  ): string {
    $coreHash = $this->createCoreHash($account, $operation, $timestamp, $additionalData, $email);
    $lastLoginHash = $this->createLastLoginHash($account);

    return sprintf('%s%s%s', $coreHash, self::SEPARATOR, $lastLoginHash);
  }

  /**
   * {@inheritdoc}
   */
  public function validateHash(
    string $hash,
    int $timestamp,
    int $timeout,
    int $mode,
    UserInterface $account,
    string $operation,
    string $additionalData = '',
    ?string $email = NULL,
  ): bool {
    $currentTime = \Drupal::time()->getRequestTime();

    // Hash is expired.
    if ($currentTime < $timestamp || $timeout < $currentTime - $timestamp) {
      return FALSE;
    }

    // If hash is not valid, return FALSE.
    if (!str_contains($hash, self::SEPARATOR)) {
      return FALSE;
    }

    [$coreHash, $lastLoginHash] = explode(self::SEPARATOR, $hash);

    // Core hash must always match!
    $referenceCoreHash = $this->createCoreHash($account, $operation, $timestamp, $additionalData, $email);
    if (!hash_equals($referenceCoreHash, $coreHash)) {
      return FALSE;
    }

    // Verify last login hash only if mode is set to login.
    if ($mode === self::MODE_LOGIN) {
      $referenceLastLoginHash = $this->createLastLoginHash($account);
      if (!hash_equals($lastLoginHash, $referenceLastLoginHash)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Create the core hash.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user to create the hash for.
   * @param string $operation
   *   The operation to create the hash for.
   * @param int $timestamp
   *   Timestamp for the hash.
   * @param string $additionalData
   *   (optional) Additional data to include in the core hash.
   * @param string|null $email
   *   (optional) An explicit email address if different than the
   *   user email.
   *
   * @return string
   *   The core hash.
   */
  protected function createCoreHash(
    UserInterface $account,
    string $operation,
    int $timestamp,
    string $additionalData = '',
    ?string $email = NULL,
  ): string {
    $key = $this->getHashKey($account);
    $targetEmail = $email ?? $account->getEmail();

    $data = $timestamp;
    $data .= $operation;
    $data .= $account->id();
    $data .= $account->getChangedTime();
    $data .= $targetEmail;
    $data .= $additionalData;

    return Crypt::hmacBase64($data, $key);
  }

  /**
   * Create the last login hash.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user to create the hash for.
   *
   * @return string
   *   The last login hash.
   */
  protected function createLastLoginHash(UserInterface $account): string {
    $key = $this->getHashKey($account);

    return Crypt::hmacBase64($account->getLastLoginTime(), $key);
  }

  /**
   * Get the key for use in hash generation.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user to create the hash for.
   *
   * @return string
   *   The key to use for the hash.
   */
  protected function getHashKey(UserInterface $account): string {
    return Settings::getHashSalt() . $account->getPassword();
  }

}
