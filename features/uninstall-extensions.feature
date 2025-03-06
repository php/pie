Feature: Extensions can be uninstalled with PIE

  Example: An extension can be uninstalled
    Given an extension was previously installed
    When I run a command to uninstall an extension
    Then the extension should not be installed anymore
