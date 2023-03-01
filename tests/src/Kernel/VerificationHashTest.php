<?php

declare(strict_types=1);

namespace Drupal\Tests\verification\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\UserInterface;
use Drupal\verification_hash\VerificationHashManagerInterface;

/**
 * Test the Verification Hash Manager.
 */
class VerificationHashTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'verification_hash',
  ];

  /**
   * The verification hash manager.
   */
  protected VerificationHashManagerInterface $manager;

  /**
   * The user.
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser();
    $this->manager = $this->container->get('verification_hash.manager');
  }

  /**
   * Test hash creation.
   */
  public function testCreateHash() {
    $timestamp = time();

    $hash = $this->manager->createHash($this->user, 'some-operation', $timestamp);

    $this->assertStringContainsString(':', $hash);
  }

}
