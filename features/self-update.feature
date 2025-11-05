Feature: PIE can update itself and verify it is authentic

  # pie self-update
  Example: PIE can update itself
    Given I have an old version of PIE
    When I update PIE to the latest stable version
    Then I should see I have been updated to the latest version

  # pie self-verify
  Example: PIE can verify its authenticity with gh
    Given I have a pie.phar built on PHP's GitHub
    And I have the gh cli command
    When I verify my PIE installation
    Then I should see it is verified

  # pie self-verify
  Example: PIE can verify its authenticity with openssl
    Given I have a pie.phar built on PHP's GitHub
    And I do not have the gh cli command
    When I verify my PIE installation
    Then I should see it is verified

  # pie self-verify
  Example: PIE will alert when its authenticity is not verified
    Given I have a pie.phar built on a nasty hacker's machine
    When I verify my PIE installation
    Then I should see it has failed verification
