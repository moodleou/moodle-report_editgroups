@report @report_editgroups @ou @ou_vle
Feature: Edit groups report navigation
  In order to navigate through report page
  As an admin
  Go to course administration -> Reports -> Groups

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | admin | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Selector should be available in the Edit groups report
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    When I navigate to "Reports" in current page administration
    And I click on "Groups" "link"
    Then "Report" "field" should exist in the "tertiary-navigation" "region"
    And I should see "Groups" in the "tertiary-navigation" "region"
