<?php

declare(strict_types=1);

namespace Drupal\verification\Plugin;

use Drupal\Core\Session\AccountInterface;
use Drupal\verification\Result\VerificationResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for the verification provider plugins.
 */
interface VerificationProviderInterface {

  /**
   * Verifies an operation from a request.
   *
   * Verifies that the given request object
   * contains valid verification data for the given
   * operation and for the given user account.
   *
   * Optionally, a different email address can be
   * provided, otherwise the user's email address is used.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $operation
   *   The operation to verify.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account to verify against.
   * @param string|null $email
   *   (optional) Email address to use.
   *
   * @return \Drupal\verification\Result\VerificationResult
   *   The verification result.
   */
  public function verifyOperation(
    Request $request,
    string $operation,
    AccountInterface $user,
    ?string $email = NULL,
  ): VerificationResult;

  /**
   * Verifies an operation from a request.
   *
   * Verifies that the given request object
   * contains valid verification data for the given
   * operation and for the given user account.
   *
   * Optionally, a different email address can be
   * provided, otherwise the user's email address is used.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $operation
   *   The operation to verify.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account to verify against.
   * @param string|null $email
   *   (optional) Email address to use.
   *
   * @return \Drupal\verification\Result\VerificationResult
   *   The verification result.
   */
  public function verifyLogin(
    Request $request,
    string $operation,
    AccountInterface $user,
    ?string $email = NULL,
  ): VerificationResult;

}
