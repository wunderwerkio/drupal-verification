<?php

/**
 * @file
 * Hooks specific to the Verification API module.
 */

use Drupal\Core\Session\AccountInterface;

/**
 * Alter the supported operations of the Hash Verification Provider.
 *
 * @param array $operations
 *   The operations to be altered.
 *
 * @see Drupal\verification\Plugin\VerificationProvider\Hash::getSupportedOperations()
 */
function hook_verification_provider_hash_supported_operations_alter(array &$operations) {
  $operations[] = 'new-operation';
}

/**
 * Alter the hash timeout used by the Hash Verification Provider.
 *
 * This value defaults to the timeout defined in
 * user.settings.password_reset_timeout.
 *
 * @param int $timeout
 *   The hash timeout in seconds.
 * @param string $operation
 *   The operation being performed.
 * @param \Drupal\Core\Session\AccountInterface $user
 *   The user performing the operation.
 *
 * @see Drupal\verification\Plugin\VerificationProvider\Hash::getTimeout()
 */
function hook_verification_provider_hash_timeout_alter(&$timeout, $operation, AccountInterface $user) {
  if ($operation === 'some-special-operation') {
    $timeout = 60;
  }
}
