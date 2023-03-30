<?php

declare(strict_types=1);

namespace Drupal\Tests\verification\Traits;

use Drupal\verification\Result\VerificationResult;

/**
 * Provides test assertions for verification results.
 */
trait VerificationTestTrait {

  /**
   * Asserts that a verification result is ok.
   *
   * @param \Drupal\verification\VerificationResult $result
   *   The verification result.
   */
  protected function assertVerificationOk(VerificationResult $result) {
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
  protected function assertVerificationErr(VerificationResult $result, ?string $code = NULL) {
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
  protected function assertVerificationUnhandled(VerificationResult $result, ?string $code = NULL) {
    $this->assertTrue($result->unhandled);

    if ($code) {
      $this->assertEquals($code, $result->code);
    }
  }

}
