Feature: Extensions for a PHP project can be installed with PIE

  # pie install php/sodium
  Example: An extension normally bundled with PHP can be installed
    Given I have libsodium on my system
    When I install the sodium extension with PIE
    Then the extension should have been installed and enabled
