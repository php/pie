Feature: Extensions can be installed with PIE

  # pie install <ext> --skip-enable-extension
  Example: An extension can be installed without enabling
    When I run a command to install an extension without enabling it
    Then the extension should have been installed

  # pie install <ext>
  Example: An extension can be installed and enabled
    When I run a command to install an extension
    Then the extension should have been installed and enabled

  # pie install <ext> && pie install <ext>
  Example: Re-installing an existing extension is a no-op
    Given an extension was previously installed and enabled
    When I run a command to install an extension
    Then the extension should not have been re-installed

  # pie install <ext> && pie install --force <ext>
  Example: Forcefully re-installing an existing extension should re-install the extension
    Given an extension was previously installed and enabled
    When I run a command to forcefully install an extension
    Then the extension should have been installed and enabled
