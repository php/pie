Feature: Extensions can be installed with Behat

  Example: The latest version of an extension can be downloaded
    When I run PIE command "download apcu/apcu"
    Then the latest version should have been downloaded

  Scenario Outline: A version matching the requested constraint can be downloaded
    When I run PIE command "<command>"
    Then version "<version>" of the extension should have been downloaded

    Examples:
      | command                                  | version     |
      | download xdebug/xdebug:dev-master        | dev-master  |
      | download xdebug/xdebug:3.4.0alpha1@alpha | 3.4.0alpha1 |
