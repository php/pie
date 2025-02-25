Feature: Extensions can be uninstalled with PIE

  # See https://github.com/php/pie/issues/190 for why this is non-Windows
  @non-windows
  Example: An extension can be uninstalled
    Given an extension was previously installed
    When I run a command to uninstall an extension
    Then the extension should not be installed anymore
