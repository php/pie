Feature: Extensions can be uninstalled with PIE

  # pie uninstall <ext>
  Example: An extension can be uninstalled
    Given an extension was previously installed and enabled
    When I run a command to uninstall an extension
    Then the extension should not be installed anymore

  # pie uninstall <ext1> <ext2>
  Example: Multiple extensions can be uninstalled at once
    Given multiple extensions were previously installed and enabled
    When I run a command to uninstall multiple extensions
    Then the extensions should not be installed anymore
