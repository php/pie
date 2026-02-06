Feature: Multiple extensions can be installed with PIE

  # pie install <ext1> <ext2> <ext3>
  Example: Multiple extensions can be installed at once
    When I run a command to install multiple extensions
    Then all extensions should have been installed
    And the output should show the installation summary

  # pie install <ext1> <ext2> --skip-enable-extension
  Example: Multiple extensions can be installed without enabling
    When I run a command to install multiple extensions without enabling them
    Then all extensions should have been installed
    And the extensions should not be enabled

  Example: Installation continues when one extension fails
    When I run a command to install multiple extensions where one fails
    Then the successful extensions should have been installed
    And the output should show which extensions failed
