Feature: Extensions can be installed with Behat

  Example: The latest version of an extension can be downloaded
    When I run PIE command "download asgrim/example-pie-extension"
    Then the latest version should have been downloaded

  Scenario Outline: A version matching the requested constraint can be downloaded
    When I run PIE command "<command>"
    Then version "<version>" of package "<package>" should have been downloaded

    Examples:
      | command                                    | package                      | version     |
      | download xdebug/xdebug:dev-master          | xdebug/xdebug                | dev-master  |
      | download xdebug/xdebug:3.4.0alpha1@alpha   | xdebug/xdebug                | 3.4.0alpha1 |
      | download asgrim/example-pie-extension:^2.0 | asgrim/example-pie-extension | 2.0.0       |
