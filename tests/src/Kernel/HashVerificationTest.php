<?php

declare(strict_types=1);

namespace Drupal\Tests\verification\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\UserInterface;
use Drupal\verification\Result\VerificationResult;
use Drupal\verification\Service\RequestVerifier;
use Drupal\verification_hash\Plugin\VerificationProvider\Hash;
use Drupal\verification_hash\VerificationHashManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the Hash Verification Provider.
 */
class HashVerificationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'verification',
    'verification_hash',
    'verification_test',
  ];

  /**
   * The user.
   */
  protected UserInterface $user;

  /**
   * The request verifier service.
   */
  protected RequestVerifier $verifier;

  /**
   * The verification hash service.
   */
  protected VerificationHashManager $hash;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser();
    $this->verifier = $this->container->get('verification.request_verifier');
    $this->hash = $this->container->get('verification_hash.manager');
  }

  /**
   * Test hash verification provider.
   */
  public function testVerification() {
    $timestamp = \Drupal::time()->getRequestTime();

    // Fail for request without hash data.
    $request = new Request();
    $request->setMethod('POST');
    $this->assertUnhandled($this->verifier->verifyOperation($request, 'register', $this->user));

    // Fail for invalid data.
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', '_invalid-hash_$$' . $timestamp);
    $this->assertErr($this->verifier->verifyOperation($request, 'register', $this->user), Hash::ERR_INVALID_HASH);

    // Fail for invalid operation.
    $hash = $this->hash->createHash($this->user, 'operation', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertUnhandled($this->verifier->verifyOperation($request, 'invalid-operation', $this->user));

    // Fail for GET request.
    $hash = $this->hash->createHash($this->user, 'operation', $timestamp);
    $request = new Request();
    $request->setMethod('GET');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertUnhandled($this->verifier->verifyOperation($request, 'invalid-operation', $this->user));

    // Fail for HEAD request.
    $hash = $this->hash->createHash($this->user, 'operation', $timestamp);
    $request = new Request();
    $request->setMethod('GET');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertUnhandled($this->verifier->verifyOperation($request, 'invalid-operation', $this->user));

    // Fail with non-matching operation.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertErr($this->verifier->verifyOperation($request, 'login', $this->user), Hash::ERR_INVALID_HASH);

    // Success.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertOk($this->verifier->verifyOperation($request, 'register', $this->user));

    // Success for operation added via hook.
    $hash = $this->hash->createHash($this->user, 'test-operation', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertOk($this->verifier->verifyOperation($request, 'test-operation', $this->user));
  }

  /**
   * Test the verification timeout.
   */
  public function testVerificationTimeout() {
    // Default timeout is 86400 seconds.
    // Subtracting one more makes the hash expired.
    $timestamp = \Drupal::time()->getRequestTime() - 86401;

    // Hash is expired.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertErr($this->verifier->verifyOperation($request, 'register', $this->user));

    // Test modified timeout via hooks.
    $timestamp = \Drupal::time()->getRequestTime() - 61;

    // Hash is expired.
    $hash = $this->hash->createHash($this->user, 'op-with-short-timeout', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertErr($this->verifier->verifyOperation($request, 'op-with-short-timeout', $this->user));
  }

  /**
   * Test hash verification provider with explicit email.
   */
  public function testVerificationWithEmail() {
    $timestamp = \Drupal::time()->getRequestTime();
    $email = 'something-other@example.com';

    // Fail with wrong email address.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertErr($this->verifier->verifyOperation($request, 'register', $this->user, $email));

    // Success.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp, '', $email);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertOk($this->verifier->verifyOperation($request, 'register', $this->user, $email));
  }

  /**
   * Test verification with login and following operation.
   */
  public function testVerificationWithPreceedingLogin() {
    $timestamp = \Drupal::time()->getRequestTime();
    $user = $this->drupalCreateUser();

    // Update changed time to be in past to mitigate
    // race conditions.
    $user->setChangedTime(time() - 5)->save();

    $hash = $this->hash->createHash($user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);

    $this->assertOk($this->verifier->verifyLogin($request, 'register', $user));
    user_login_finalize($user);
    $this->assertErr($this->verifier->verifyLogin($request, 'register', $user));

    $this->assertOk($this->verifier->verifyOperation($request, 'register', $user));
    $this->assertErr($this->verifier->verifyOperation($request, 'register', $user));
  }

  /**
   * Asserts that a verification result is ok.
   *
   * @param \Drupal\verification\VerificationResult $result
   *   The verification result.
   */
  protected function assertOk(VerificationResult $result) {
    $this->assertTrue($result->ok);
  }

  /**
   * Asserts that a verification result is an error.
   *
   * @param \Drupal\verification\VerificationResult $result
   *   The verification result.
   * @param string|null $code
   *   (optional) The expected error code.
   */
  protected function assertErr(VerificationResult $result, ?string $code = NULL) {
    $this->assertTrue($result->err);

    if ($code) {
      $this->assertEquals($code, $result->code);
    }
  }

  /**
   * Asserts that a verification result is unhandled.
   *
   * @param \Drupal\verification\VerificationResult $result
   *   The verification result.
   * @param string|null $code
   *   (optional) The expected error code.
   */
  protected function assertUnhandled(VerificationResult $result, ?string $code = NULL) {
    $this->assertTrue($result->unhandled);

    if ($code) {
      $this->assertEquals($code, $result->code);
    }
  }

}
