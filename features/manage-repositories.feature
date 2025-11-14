Feature: Package repositories can be managed with PIE

  # pie repository:add ...
  Example: A package repository can be added
    Given no repositories have previously been added
    When I add a package repository
    Then I should see the package repository can be used by PIE

  # pie repository:remove ...
  Example: A package repository can be removed
    Given I have previously added a package repository
    When I remove the package repository
    Then I should see the package repository is not used by PIE
