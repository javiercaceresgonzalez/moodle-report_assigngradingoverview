@report @report_assigngradingoverview
Feature: View assignment grading status
  In order to prioritise grading work
  As a teacher
  I need a consolidated assignment overview

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course A | CA        | 0        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | CA     | editingteacher |
      | student1 | CA     | student        |
    And the following "activities" exist:
      | activity | name         | course | idnumber |
      | assign   | Assignment 1 | CA     | assign1  |

  Scenario: A teacher opens the global and course reports
    When I log in as "teacher1"
    And I visit "/report/assigngradingoverview/index.php"
    Then I should see "Assignment grading overview"
    And I should see "Assignment 1"
    When I visit "/report/assigngradingoverview/course.php?id=2"
    Then I should see "Assignment 1"

  Scenario: A student cannot see course grading data
    When I log in as "student1"
    And I visit "/report/assigngradingoverview/course.php?id=2"
    Then I should see "You do not have permission"
