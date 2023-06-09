<?php

declare(strict_types=1);

namespace Drupal\verification\Result;

use Wunderwerk\JsonApiError\JsonApiErrorResponse;

/**
 * A verification result.
 *
 * Represents the following states:
 *   - ok: Successful verification.
 *   - err: Failed verification.
 *   - unhandled: Provider found no matching verification data.
 *
 * The result should only be considered unhandled if the provider did not
 * find suitable verification data.
 */
class VerificationResult {

  /**
   * Whether the result was ok.
   */
  public readonly bool $ok;

  /**
   * Whether the result was an error.
   */
  public readonly bool $err;

  /**
   * Whether the result was unhandled.
   */
  public readonly bool $unhandled;

  /**
   * The error code.
   */
  public readonly string $code;

  /**
   * Constructs a new VerificationResult object.
   *
   * @param bool $ok
   *   Whether the result was ok.
   * @param bool $err
   *   Whether the result was an error.
   * @param bool $unhandled
   *   Whether the result was unhandled.
   * @param string $code
   *   The error code.
   *
   * @throws \InvalidArgumentException
   *   Thrown if not $unhandled and $ok and $err are both true or both false.
   */
  public function __construct(
    bool $ok = FALSE,
    bool $err = FALSE,
    bool $unhandled = FALSE,
    string $code = '',
  ) {
    if (!$unhandled && $ok === $err) {
      throw new \InvalidArgumentException('Handled verification result must be either $ok or $err.');
    }

    $this->ok = $ok;
    $this->err = $err;
    $this->unhandled = $unhandled;
    $this->code = $code;
  }

  /**
   * Returns the result as a JSON API error response.
   *
   * @return \Wunderwerk\JsonApiError\JsonApiErrorResponse|null
   *   The JSON API error response or null if the result was ok.
   */
  public function toErrorResponse(): ?JsonApiErrorResponse {
    if ($this->ok) {
      return NULL;
    }

    // Verification failed.
    if ($this->err) {
      return JsonApiErrorResponse::fromError(
        status: 403,
        title: 'Verification failed',
        code: 'verification_failed',
        detail: 'The verification was not successful.',
        meta: [
          'verification_error_code' => $this->code,
        ]
      );
    }

    // Unhandled.
    return JsonApiErrorResponse::fromError(
      status: 403,
      title: 'Verification failed',
      code: 'verification_unhandled',
      detail: 'No verification provider was able to handle the request.',
    );
  }

  /**
   * Returns an ok result.
   *
   * @return \Drupal\verification\Result\VerificationResultInterface
   *   The ok result.
   */
  public static function ok() {
    return new self(TRUE);
  }

  /**
   * Returns an error result.
   *
   * @param string $code
   *   The error code.
   *
   * @return \Drupal\verification\Result\VerificationResultInterface
   *   The error result.
   */
  public static function err(string $code) {
    return new self(FALSE, TRUE, FALSE, $code);
  }

  /**
   * Returns an unhandled result.
   *
   * @param string|null $code
   *   The error code.
   *
   * @return \Drupal\verification\Result\VerificationResultInterface
   *   The unhandled result.
   */
  public static function unhandled(?string $code = NULL) {
    return new self(FALSE, FALSE, TRUE, $code ?? "");
  }

}
