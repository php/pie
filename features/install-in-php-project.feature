Feature: Extensions for a PHP project can be installed with PIE

  # pie install --select <ext>=<package> ...
  Example: PIE running in a PHP project automatically installs missing dependencies
    Given I am in a PHP project that has missing extensions
    When I run a command to install the extensions with package selections
    Then I should see all the extensions are now installed

  # pie install
  Example: PIE running in a PHP project without package selections will fail
    Given I am in a PHP project that has missing extensions
    When I run a command to install the extensions without package selections
    Then I should see information on how to select packages for install
