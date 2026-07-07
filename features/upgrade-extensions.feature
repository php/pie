Feature: PIE extensions can be upgraded with PIE

  # pie upgrade
  Example: I can upgrade existing PIE extensions
    Given I have installed PIE extensions that have upgrades available
    When I run a command to upgrade my extensions
    Then the extensions should have been upgraded to the latest versions
