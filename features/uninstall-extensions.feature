Feature: Extensions can be uninstalled with PIE

  Example: The latest version of an extension can be downloaded
    Given an extension was previously installed
    When I run a command to uninstall an extension
    Then the extension should not be installed anymore
