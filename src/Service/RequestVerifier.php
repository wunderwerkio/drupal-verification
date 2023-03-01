<?php

declare(strict_types=1);

namespace Drupal\verification\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\verification\Plugin\VerificationProviderManagerInterface;
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
   * @return bool
   *   TRUE if the verification was successful, FALSE otherwise.
   *
   * @see Drupal\verification\Plugin\VerificationProviderInterface
   */
  public function verifyLogin(Request $request, string $operation, AccountInterface $account, ?string $email = NULL) {
    $instances = $this->verificationProviderManager->getInstances();

    foreach ($instances as $plugin) {
      $result = $plugin->verifyLogin($request, $operation, $account, $email);

      if ($result === TRUE) {
        return TRUE;
      }
    }

    return FALSE;
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
   * @return bool
   *   TRUE if the verification was successful, FALSE otherwise.
   *
   * @see Drupal\verification\Plugin\VerificationProviderInterface
   */
  public function verifyOperation(Request $request, string $operation, AccountInterface $account, ?string $email = NULL) {
    $instances = $this->verificationProviderManager->getInstances();

    foreach ($instances as $plugin) {
      $result = $plugin->verifyOperation($request, $operation, $account, $email);

      if ($result === TRUE) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
