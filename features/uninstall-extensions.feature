Feature: Extensions can be uninstalled with PIE

  # pie uninstall <ext>
  Example: An extension can be uninstalled
    Given an extension was previously installed and enabled
    When I run a command to uninstall an extension
    Then the extension should not be installed anymore
