Feature: A PIE extension can be installed with PIE

  # pie install
  Example: Running PIE in a PIE project will install that PIE extension
    Given I am in a PIE project
    When I run a command to install the extension
    Then the extension should have been installed and enabled
