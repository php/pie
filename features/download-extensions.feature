Feature: Extensions can be downloaded with PIE

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
