<?php

declare(strict_types=1);

namespace Drupal\verification\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\verification\Plugin\VerificationProviderManagerInterface;
use Drupal\verification\Result\VerificationResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies requests.
 */
class RequestVerifier {

  /**
   * Constructs a RequestVerifier.
   *
   * @param \Drupal\verification\Plugin\VerificationProviderManagerInterface $verificationProviderManager
   *   The verification provider manager.
   */
  public function __construct(
    protected VerificationProviderManagerInterface $verificationProviderManager,
  ) {}

  /**
   * Checks if request is verified for given operation and account.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $operation
   *   The operation to verify.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to verify against.
   * @param string|null $email
   *   (optional) Email address to use.
   *
   * @return \Drupal\verification\Result\VerificationResult
   *   The verification result.
   *
   * @see Drupal\verification\Plugin\VerificationProviderInterface
   */
  public function verifyLogin(Request $request, string $operation, AccountInterface $account, ?string $email = NULL): VerificationResult {
    $instances = $this->verificationProviderManager->getInstances();
    $finalResult = VerificationResult::unhandled();

    foreach ($instances as $plugin) {
      $result = $plugin->verifyLogin($request, $operation, $account, $email);

      if ($result->ok) {
        return $result;
      }

      if ($result->err) {
        $finalResult = $result;
      }
    }

    return $finalResult;
  }

  /**
   * Checks if request is verified for given operation and account.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $operation
   *   The operation to verify.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to verify against.
   * @param string|null $email
   *   (optional) Email address to use.
   *
   * @return \Drupal\verification\Result\VerificationResult
   *   The verification result.
   *
   * @see Drupal\verification\Plugin\VerificationProviderInterface
   */
  public function verifyOperation(Request $request, string $operation, AccountInterface $account, ?string $email = NULL): VerificationResult {
    $instances = $this->verificationProviderManager->getInstances();
    $finalResult = VerificationResult::unhandled();

    foreach ($instances as $plugin) {
      $result = $plugin->verifyOperation($request, $operation, $account, $email);

      if ($result->ok) {
        return $result;
      }

      if ($result->err) {
        $finalResult = $result;
      }
    }

    return $finalResult;
  }

}
