@repository @repository_free_images @javascript
Feature: Free images repository
  In order to update my profile picture
  As an admin
  I need to choose a picture from Free images

  Scenario: Users can add profile picture using free_images
    Given I log in as "admin"
    And I open my profile in edit mode
    And I click on "Add..." "button" in the "New picture" "form_row"
    # Upload a new user picture using Free images repository.
    And I follow "Free images"
    And I set the field "Search for:" to "cat"
    And I click on "Submit" "button"
    # Click on the link of the first search result.
    And I click on "a.fp-file" "css_element"
    And I click on "Select this file" "button"
    When I click on "Update profile" "button"
    # New profile picture.
    Then "//img[contains(@class, 'userpicture')]" "xpath_element" should exist
    # Default profile picture should not exist any more.
    And "//img[contains(@class, 'defaultuserpic')]" "xpath_element" should not exist
