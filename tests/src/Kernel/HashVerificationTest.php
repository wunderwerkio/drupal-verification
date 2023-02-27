<?php

declare(strict_types=1);

namespace Drupal\Tests\verification\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\UserInterface;
use Drupal\verification\Service\RequestVerifier;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser();
    $this->verifier = $this->container->get('verification.request_verifier');
  }

  /**
   * Test hash verification provider.
   */
  public function testVerification() {
    $timestamp = \Drupal::time()->getRequestTime();

    // Fail for request without hash data.
    $request = new Request();
    $request->setMethod('POST');
    $this->assertFalse($this->verifier->verify($request, 'register', $this->user));

    // Fail for invalid data.
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', '_invalid-hash_$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'register', $this->user));

    // Fail for invalid operation.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'invalid-operation', $this->user));

    // Fail for GET request.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('GET');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'invalid-operation', $this->user));

    // Fail for HEAD request.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('GET');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'invalid-operation', $this->user));

    // Success.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertTrue($this->verifier->verify($request, 'register', $this->user));

    // Success for operation added via hook.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertTrue($this->verifier->verify($request, 'test-operation', $this->user));
  }

  /**
   * Test the verification timeout.
   */
  public function testVerificationTimeout() {
    // Default timeout is 86400 seconds.
    // Subtracting one more makes the hash expired.
    $timestamp = \Drupal::time()->getRequestTime() - 86401;

    // Hash is expired.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'register', $this->user));

    // Test modified timeout via hooks.
    $timestamp = \Drupal::time()->getRequestTime() - 61;

    // Hash is expired.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'op-with-short-timeout', $this->user));
  }

  /**
   * Test hash verification provider with explicit email.
   */
  public function testVerificationWithEmail() {
    $timestamp = \Drupal::time()->getRequestTime();
    $email = 'something-other@example.com';

    // Fail with wrong email address.
    $hash = user_pass_rehash($this->user, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertFalse($this->verifier->verify($request, 'register', $this->user, $email));

    // Success.
    $clone = clone $this->user;
    $clone->setEmail($email);
    $hash = user_pass_rehash($clone, $timestamp);
    $request = new Request();
    $request->setMethod('POST');
    $request->headers->set('X-Verification-Hash', $hash . '$$' . $timestamp);
    $this->assertTrue($this->verifier->verify($request, 'register', $this->user, $email));
  }

}
