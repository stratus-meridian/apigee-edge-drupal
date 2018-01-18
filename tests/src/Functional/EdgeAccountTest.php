<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;

/**
 * Edge account related tests.
 *
 * @group ApigeeEdge
 */
class EdgeAccountTest extends BrowserTestBase {

  /**
   * Credential storage.
   *
   * @var array
   */
  protected $credentials = [];

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * Initializes the credentials property.
   *
   * @return bool
   *   True if the credentials are successfully initialized.
   */
  protected function initCredentials() : bool {
    if (($username = getenv('APIGEE_EDGE_USERNAME'))) {
      $this->credentials['username'] = $username;
    }
    if (($password = getenv('APIGEE_EDGE_PASSWORD'))) {
      $this->credentials['password'] = $password;
    }
    if (($organization = getenv('APIGEE_EDGE_ORGANIZATION'))) {
      $this->credentials['organization'] = $organization;
    }
    if (($endpoint = getenv('APIGEE_EDGE_ENDPOINT'))) {
      $this->credentials['endpoint'] = $endpoint;
    }

    return (bool) $this->credentials;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    if (!$this->initCredentials()) {
      $this->markTestSkipped('credentials not found');
    }
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  protected function resetCache() {
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache();
  }

  /**
   * Tests environment credentials storage.
   */
  public function testCredentialsStorages() {
    // Test private file storage.
    $this->drupalGet('/admin/config/apigee-edge');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_private_file',
      'credentials_api_organization' => $this->credentials['organization'],
      'credentials_api_endpoint' => $this->credentials['endpoint'],
      'credentials_api_username' => $this->credentials['username'],
      'credentials_api_password' => $this->credentials['password'],
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));

    $developer_data = [
      'userName' => 'UserByAdmin',
      'email' => 'edge.functional.test@pronovix.com',
      'firstName' => 'Functional',
      'lastName' => "Test",
    ];

    $developer = Developer::create($developer_data);
    $developer->save();

    $this->resetCache();

    /** @var Developer $developer */
    $developer = Developer::load($developer_data['email']);
    $this->assertEquals($developer->getEmail(), $developer_data['email']);

    // Test env storage.
    $this->drupalGet('/admin/config/apigee-edge');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_env',
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));

    $this->resetCache();

    $developer = Developer::load($developer_data['email']);
    $this->assertEquals($developer->getEmail(), $developer_data['email']);

    $developer->delete();
  }

}
