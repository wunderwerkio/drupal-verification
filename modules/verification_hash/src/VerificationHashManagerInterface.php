<?php

declare(strict_types=1);

namespace Drupal\verification_hash;

use Drupal\user\UserInterface;

/**
 * Interface for a verification hash manager.
 */
interface VerificationHashManagerInterface {

  const MODE_LOGIN = 1;
  const MODE_OPERATION = 2;

  /**
   * Create a unique hash for the given verification operation.
   *
   * This is very similar to the user_pass_rehash() function, but
   * is enhanced to enable a single hash to support verification
   * of separate login and operation.
   *
   * This is done by spliting the hashed data into two parts: a
   * core hash that must always match for the verification to be
   * successful, and a secondary hash with just the last login timestamp
   * that becomes invalid after the user logs in.
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
   */
  public function createHash(
    UserInterface $account,
    string $operation,
    int $timestamp,
    string $additionalData = '',
    ?string $email = NULL,
  ): string;

  /**
   * Validates the given hash.
   *
   * There are two modes of hash validation: LOGIN and OPERATION.
   * The verification API supports that the user is logged in before
   * the verified operation is executed.
   *
   * This means, that the hash needs to verify both the login and the
   * operation. For that the hash is split into two parts. The login
   * part is only checked if the user is logging in. The core hash
   * must always match.
   *
   * @param string $hash
   *   The hash to verify.
   * @param int $timestamp
   *   Timestamp for the hash.
   * @param int $timeout
   *   Hash timestamp timeout in seconds.
   * @param int $mode
   *   Either self::MODE_LOGIN or self::MODE_OPERATION.
   * @param \Drupal\user\UserInterface $account
   *   The user to create the hash for.
   * @param string $operation
   *   The operation to create the hash for.
   * @param string $additionalData
   *   (optional) Additional data to include in the core hash.
   * @param string|null $email
   *   (optional) An explicit email address if different than the
   *   user email.
   *
   * @return bool
   *   The validation result.
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
  ): bool;

}
