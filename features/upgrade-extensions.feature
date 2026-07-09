Feature: PIE extensions can be upgraded with PIE

  # pie upgrade
  Example: I can upgrade existing PIE extensions
    Given I have installed PIE extensions that have upgrades available
    When I run a command to upgrade my extensions
    Then the extensions should have been upgraded to the latest versions

  # pie upgrade
  Example: I can upgrade existing PIE extensions that had been built with configure options
    Given I have installed PIE extensions with configure options that have upgrades available
    When I run a command to upgrade my extensions
    Then the extension has been upgraded with the previous configure options
