@block @block_grade_me @javascript @block_grade_me_assign
Feature: Assignments are displayed in the block

    Background:
        Given the grade me block is present on all pages.
        And the following "users" exist:
          | username | firstname | lastname | email |
          | teacher1 | John | Doe | teacher1@example.com |
          | student1 | Janie | Doe | student1@example.com |
          | student2 | Kevin | Smith | student2@example.com |
          | student3 | Jill  | Green | student3@example.com |
        And the following "courses" exist:
          | fullname | shortname | category |
          | Course 1 | C1 | 0 |
        And the following "course enrolments" exist:
          | user | course | role |
          | admin | C1 | editingteacher |
          | teacher1 | C1 | editingteacher |
          | student1 | C1 | student |
          | student2 | C1 | student |
          | student3 | C1 | student |
        And the following config values are set as admin:
          | config | value |
          | block_grade_me_enableaassign | 1 |
          | block_grade_me_enableadminviewall | 1 |

    Scenario: Assignments show up in the block
      Given I log in as "teacher1"
      And I follow "My courses"
      And I follow "Course 1"
      And I turn editing mode on
      And I add a "Assignment" to section "1" and I fill the form with:
        | Assignment name | Assign |
        | Description | Submit your online text |
        | assignsubmission_onlinetext_enabled | 1 |
        | assignsubmission_onlinetext_wordlimit_enabled | 1 |
        | assignsubmission_onlinetext_wordlimit | 10 |
        | assignsubmission_file_enabled | 0 |
      And I log out
      # Now the students submit assignments.
      And I log in as "student1"
      And I follow "My courses"
      And I follow "Course 1"
      And I follow "Assign"
      When I press "Add submission"
      And I set the following fields to these values:
        | Online text | 7 8 9 10. |
      And I press "Save changes"
      Then I should see "Submitted for grading"
      And I log out
      # Student2  submit assigment.
      And I log in as "student2"
      And I follow "My courses"
      And I follow "Course 1"
      And I follow "Assign"
      When I press "Add submission"
      And I set the following fields to these values:
        | Online text | A dog barked. |
      And I press "Save changes"
      Then I should see "Submitted for grading"
      And I log out
      # Student3 submits assignment.
      And I log in as "student3"
      And I follow "My courses"
      And I follow "Course 1"
      And I follow "Assign"
      When I press "Add submission"
      And I set the following fields to these values:
        | Online text | A pig made a noise. |
      And I press "Save changes"
      Then I should see "Submitted for grading"
      And I log out
      # Now we check the block.
      When I log in as "admin"
      And I run the scheduled task "block_grade_me\task\cache_grade_data"
      And I am on site homepage
      Then I should see "C1" in the "Grade Me" "block"
      And I should see "Assign" in the "Grade Me" "block"
      When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
      Then I should see "Janie Doe" in the "Grade Me" "block"
      Then I should see "Kevin Smith" in the "Grade Me" "block"
      Then I should see "Jill Green" in the "Grade Me" "block"
      And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
      # Grade the first student's submission.
      When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
      And I set the field "grade" to "2"
      And I press "Save changes"
      # After grading we verify two users still appear in the block.
      And I am on site homepage
      Then I should see "C1" in the "Grade Me" "block"
      And I should see "Assign" in the "Grade Me" "block"
      When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
      Then I should see "Kevin Smith" in the "Grade Me" "block"
      Then I should see "Jill Green" in the "Grade Me" "block"
      And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
      # Grade the seconds student's submission.
      When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
      And I set the field "grade" to "90"
      And I press "Save changes"
      Then I should see "C1" in the "Grade Me" "block"
      And I should see "Assign" in the "Grade Me" "block"
      When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
      Then I should see "Jill Green" in the "Grade Me" "block"
      And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
      # Grade the last student's submission.
      When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
      And I set the field "grade" to "90"
      And I press "Save changes"
      And I am on site homepage
      Then I should see "Nothing to grade!" in the "Grade Me" "block"

    Scenario: Assignments with scaler grades show up in the block
      Given I log in as "teacher1"
      And I follow "My courses"
      And I follow "Course 1"
      And I turn editing mode on
      And I add a "Assignment" to section "1" and I fill the form with:
        | Assignment name | Assign |
        | Description | Submit your online text |
        | assignsubmission_onlinetext_enabled | 1 |
        | assignsubmission_onlinetext_wordlimit_enabled | 1 |
        | assignsubmission_onlinetext_wordlimit | 10 |
        | assignsubmission_file_enabled | 0 |
        | grade[modgrade_type] | Scale |
      And I log out
      # Now the students submit assignments.
      And I log in as "student1"
      And I follow "My courses"
      And I follow "Course 1"
      And I follow "Assign"
      When I press "Add submission"
      And I set the following fields to these values:
        | Online text | 7 8 9 10. |
      And I press "Save changes"
      Then I should see "Submitted for grading"
      And I log out
      # Student2  submit assigment.
      And I log in as "student2"
      And I follow "My courses"
      And I follow "Course 1"
      And I follow "Assign"
      When I press "Add submission"
      And I set the following fields to these values:
        | Online text | A dog barked. |
      And I press "Save changes"
      Then I should see "Submitted for grading"
      And I log out
      # Student3 submits assignment.
      And I log in as "student3"
      And I follow "My courses"
      And I follow "Course 1"
      And I follow "Assign"
      When I press "Add submission"
      And I set the following fields to these values:
        | Online text | A pig made a noise. |
      And I press "Save changes"
      Then I should see "Submitted for grading"
      And I log out
      # Now we check the block.
      When I log in as "admin"
      And I run the scheduled task "block_grade_me\task\cache_grade_data"
      And I am on site homepage
      Then I should see "C1" in the "Grade Me" "block"
      And I should see "Assign" in the "Grade Me" "block"
      When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
      Then I should see "Janie Doe" in the "Grade Me" "block"
      Then I should see "Kevin Smith" in the "Grade Me" "block"
      Then I should see "Jill Green" in the "Grade Me" "block"
      And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
      # Grade the first student's submission.
      When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
      And I set the field "grade" to "2"
      And I press "Save changes"
      # After grading we verify two users still appear in the block.
      And I am on site homepage
      Then I should see "C1" in the "Grade Me" "block"
      And I should see "Assign" in the "Grade Me" "block"
      When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
      Then I should see "Kevin Smith" in the "Grade Me" "block"
      Then I should see "Jill Green" in the "Grade Me" "block"
      And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
      # Grade the seconds student's submission.
      When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
      And I set the field "Feedback comments" to "feed back comments"
      And I press "Save changes"
      Then I should see "C1" in the "Grade Me" "block"
      And I should see "Assign" in the "Grade Me" "block"
      When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
      Then I should see "Jill Green" in the "Grade Me" "block"
      And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
      And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
      # Grade the last student's submission.
      When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
      And I set the field "grade" to "1"
      And I press "Save changes"
      And I am on site homepage
      Then I should see "Nothing to grade!" in the "Grade Me" "block"
