Feature: Bundled PHP extensions can be installed

  # pie install php/sodium
  Example: An extension normally bundled with PHP can be installed
    Given I have libsodium on my system
    When I install the sodium extension with PIE
    Then the extension should have been installed and enabled

  Example: A bundled extension installed with PIE can be uninstalled
    Given I have the sodium extension installed with PIE
    When I run a command to uninstall an extension
    Then the extension should not be installed anymore
