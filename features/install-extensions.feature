Feature: Extensions can be installed with PIE

  # pie download <ext>
  Example: The latest version of an extension can be downloaded
    When I run a command to download the latest version of an extension
    Then the latest version should have been downloaded

  # pie download <ext>:<version>
  Scenario Outline: A version matching the requested constraint can be downloaded
    When I run a command to download version "<constraint>" of an extension
    Then version "<version>" should have been downloaded

    Examples:
      | constraint | version  |
      | 2.0.5      | 2.0.5    |
      | ^2.0       | 2.0.5    |

  # pie download <ext>:dev-main
  @non-windows
  Example: An in-development version can be downloaded on non-Windows systems
    When I run a command to download version "dev-main" of an extension
    Then version "dev-main" should have been downloaded

  # pie build <ext>
  Example: An extension can be built
    When I run a command to build an extension
    Then the extension should have been built

  Example: An extension can be built with warnings at PHP startup
    Given I have an invalid extension installed
    When I run a command to build an extension
    Then the extension should have been built

  # pie build <ext> --with-some-options=foo
  Example: An extension can be built with configure options
    When I run a command to build an extension with configure options
    Then the extension should have been built with options

  # pie install <ext> --skip-enable-extension
  Example: An extension can be installed without enabling
    When I run a command to install an extension without enabling it
    Then the extension should have been installed

  # pie install <ext>
  Example: An extension can be installed and enabled
    When I run a command to install an extension
    Then the extension should have been installed and enabled
