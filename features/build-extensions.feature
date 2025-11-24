Feature: Extensions can be built with PIE

  # pie build <ext>
  Example: An extension can be built
    When I run a command to build an extension
    Then the extension should have been built

  # pie build <ext>
  Example: An extension can be built with warnings at PHP startup
    Given I have an invalid extension installed
    When I run a command to build an extension
    Then the extension should have been built

  # pie build <ext> --with-some-options=foo
  Example: An extension can be built with configure options
    When I run a command to build an extension with configure options
    Then the extension should have been built with options
