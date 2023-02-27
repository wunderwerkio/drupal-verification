<?php

/**
 * @file
 * Hooks specific to the Verification API module.
 */

/**
 * Alter the supported operations of the Hash Verification Provider.
 *
 * @param array $operations
 *   The operations to be altered.
 *
 * @see Drupal\verification\Plugin\VerificationProvider\Hash::getSupportedOperations().
 */
function hook_verification_provider_hash_supported_operations_alter(&$operations) {
  $operations[] = 'new-operation';
}
