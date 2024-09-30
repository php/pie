Feature: Extensions can be installed with Behat

  Example: The latest version of an extension can be downloaded
    When I run PIE command "download asgrim/example-pie-extension"
    Then the latest version should have been downloaded

  Scenario Outline: A version matching the requested constraint can be downloaded
    When I run PIE command "<command>"
    Then version "<version>" of the extension should have been downloaded

    Examples:
      | command                                                   | version       |
      | download asgrim/example-pie-extension:^1.0                | 1.0.1         |
      | download asgrim/example-pie-extension:1.0.1-alpha.3@alpha | 1.0.1-alpha.3 |
