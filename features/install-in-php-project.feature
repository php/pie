Feature: Extensions for a PHP project can be installed with PIE

  # pie install
  Example: PIE running in a PHP project suggests missing dependencies
    Given I am in a PHP project that has missing extensions
    When I run a command to install the extensions
    Then I should see all the extensions are now installed
