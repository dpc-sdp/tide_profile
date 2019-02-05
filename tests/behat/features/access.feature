# @TODO remove @skipped once the module is extracted to its own repo.
Feature: Access to Profile content type

  Ensure that Profile content access permissions are set correctly
  for designated roles.

  @api
  Scenario Outline: Users have access to create Profile content
    Given I am logged in as a user with the "<role>" role
    When I go to "node/add/profile"
    Then I should get a "<response>" HTTP response
    And save screenshot
    Examples:
      | role               | response |
      | authenticated user | 404      |
      | administrator      | 200      |
      | editor             | 200      |
      | approver           | 200      |
      | previewer          | 404      |
