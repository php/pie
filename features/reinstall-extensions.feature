Feature: Extensions can be re-installed with PIE, but only if needed

  # pie download <ext> && pie install <ext>
  Example: Installing a previously downloaded extension should install the extension
    Given an extension was previously downloaded but not built
    When I run a command to install an extension
    Then the extension should have been installed and enabled

  # pie build <ext> && pie install <ext>
  Example: Installing a previously built extension should install the extension
    Given an extension was previously built but not installed
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
