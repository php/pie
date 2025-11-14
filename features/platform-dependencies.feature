Feature: Platform dependencies are checked when installing

  # pie info <ext>
  Example: Extension platform dependencies are listed as dependencies
    Given I do not have libsodium on my system
    When I display information about the sodium extension with PIE
    Then the information should show that libsodium is a missing dependency

  # pie install <ext>
  Example: Extension platform dependencies will warn the extension is missing a dependency
    Given I do not have libsodium on my system
    When I install the sodium extension with PIE
    Then the extension fails to install due to the missing library
