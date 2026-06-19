Feature: Extensions can be installed with PIE

  # pie install <ext> --skip-enable-extension
  Example: An extension can be installed without enabling
    When I run a command to install an extension without enabling it
    Then the extension should have been installed

  # pie install <ext>
  Example: An extension can be installed and enabled
    When I run a command to install an extension
    Then the extension should have been installed and enabled

  # pie install <ext1> <ext2>
  Example: Multiple extensions can be installed at once
    When I run a command to install multiple extensions
    Then the extensions should have been installed and enabled
