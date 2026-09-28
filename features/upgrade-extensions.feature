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

  # pie upgrade
  Example: I can upgrade a PIE extension installed from a development branch that has new commits
    Given I have installed a PIE extension from a development branch with configure options that has new commits
    When I run a command to upgrade my extensions
    Then the extension has been upgraded to the latest commit with the previous configure options

  # pie upgrade
  Example: Upgrading a PIE extension installed from a development branch with no new commits does nothing
    Given I have installed a PIE extension from a development branch that has no new commits
    When I run a command to upgrade my extensions
    Then the extension should not have been re-installed
