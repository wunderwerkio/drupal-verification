<?php

declare(strict_types=1);

namespace Drupal\Tests\verification\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\verification\Traits\VerificationTestTrait;
use Drupal\user\UserInterface;
use Drupal\verification\Service\RequestVerifier;
use Drupal\verification_hash\Plugin\VerificationProvider\Hash;
use Drupal\verification_hash\VerificationHashManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the Hash Verification Provider.
 */
class HashVerificationTest extends EntityKernelTestBase {

  use VerificationTestTrait;

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
    $this->assertVerificationUnhandled($this->verifier->verifyOperation($request, 'register', $this->user));

    // Fail for invalid data.
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', '_invalid-hash_$$' . $timestamp);
    $this->assertVerificationErr($this->verifier->verifyOperation($request, 'register', $this->user), Hash::ERR_INVALID_HASH);

    // Fail for invalid operation.
    $hash = $this->hash->createHash($this->user, 'operation', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationUnhandled($this->verifier->verifyOperation($request, 'invalid-operation', $this->user));

    // Fail for GET request.
    $hash = $this->hash->createHash($this->user, 'operation', $timestamp);
    $request = new Request();
    $request->setMethod('GET');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationUnhandled($this->verifier->verifyOperation($request, 'invalid-operation', $this->user));

    // Fail for HEAD request.
    $hash = $this->hash->createHash($this->user, 'operation', $timestamp);
    $request = new Request();
    $request->setMethod('GET');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationUnhandled($this->verifier->verifyOperation($request, 'invalid-operation', $this->user));

    // Fail with non-matching operation.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationErr($this->verifier->verifyOperation($request, 'login', $this->user), Hash::ERR_INVALID_HASH);

    // Success.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationOk($this->verifier->verifyOperation($request, 'register', $this->user));

    // Success for operation added via hook.
    $hash = $this->hash->createHash($this->user, 'test-operation', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationOk($this->verifier->verifyOperation($request, 'test-operation', $this->user));
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
    $this->assertVerificationErr($this->verifier->verifyOperation($request, 'register', $this->user));

    // Test modified timeout via hooks.
    $timestamp = \Drupal::time()->getRequestTime() - 61;

    // Hash is expired.
    $hash = $this->hash->createHash($this->user, 'op-with-short-timeout', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationErr($this->verifier->verifyOperation($request, 'op-with-short-timeout', $this->user));
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
    $this->assertVerificationErr($this->verifier->verifyOperation($request, 'register', $this->user, $email));

    // Success.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp, '', $email);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertVerificationOk($this->verifier->verifyOperation($request, 'register', $this->user, $email));
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

    $this->assertVerificationOk($this->verifier->verifyLogin($request, 'register', $user));
    user_login_finalize($user);
    $this->assertVerificationErr($this->verifier->verifyLogin($request, 'register', $user));

    $this->assertVerificationOk($this->verifier->verifyOperation($request, 'register', $user));
    $this->assertVerificationErr($this->verifier->verifyOperation($request, 'register', $user));
  }

  /**
   * Test verification error response.
   */
  public function testVerificationErrorResponse() {
    $timestamp = \Drupal::time()->getRequestTime();

    // Fail for request without hash data.
    $request = new Request();
    $request->setMethod('POST');
    $result = $this->verifier->verifyOperation($request, 'register', $this->user);

    $response = $result->toErrorResponse();
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertStringContainsString('"code":"verification_unhandled"', $response->getContent());

    // Fail for invalid data.
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', '_invalid-hash_$$' . $timestamp);

    $result = $this->verifier->verifyOperation($request, 'register', $this->user);

    $response = $result->toErrorResponse();
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertStringContainsString('"code":"verification_failed"', $response->getContent());
    $this->assertStringContainsString('"verification_error_code":"hash_invalid"', $response->getContent());

    // Success.
    $hash = $this->hash->createHash($this->user, 'register', $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);

    $result = $this->verifier->verifyOperation($request, 'register', $this->user);

    $response = $result->toErrorResponse();
    $this->assertNull($response);
  }

}
