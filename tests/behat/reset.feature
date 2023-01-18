@block @block_grade_me @javascript @block_grade_me_quiz
Feature: Reset task works.

    Background:
        Given the grade me block is present on all pages.
        And the following "users" exist:
          | username | firstname | lastname | email |
          | teacher1 | Jane | Doe | teacher1@example.com |
          | student1 | Johnny | Doe | student1@example.com |
          | student2 | Janie | Doe | student2@example.com |
        And the following "courses" exist:
          | fullname | shortname | category |
          | Course 1 | C1 | 0 |
          | Course 2 | C2 | 0 |
        And the following "course enrolments" exist:
          | user | course | role |
          | admin | C1 | editingteacher |
          | teacher1 | C1 | editingteacher |
          | student1 | C1 | student |
          | student2 | C1 | student |
          | admin | C2 | editingteacher |
          | student1 | C2 | student |
          | student2 | C2 | student |
        And the following config values are set as admin:
          | config | value |
          | block_grade_me_enableadminviewall | 1 |
          | block_grade_me_enablequiz | 1 |
          | block_grade_me_enableaassign | 1 |
          | block_grade_me_enableadminviewall | 1 |


    Scenario: A quiz and assignment show up in the block and they are both there after reset
        Given the following "activities" exist:
          | activity | course | idnumber | name |
          | quiz | C1 | testforum | Test Quiz |
        And I log in as "admin"
        And I follow "My courses"
        And I follow "Course 1"
        And I add a "True/False" question to the "Test Quiz" quiz with:
          | Question name | First question |
          | Question text | First question |
          | Default mark  | 2.0 |
        And I add a "Essay" question to the "Test Quiz" quiz with:
          | Question name | Second question |
          | Question text | Second question |
          | Default mark  | 2.0 |
        And I add a "Essay" question to the "Test Quiz" quiz with:
          | Question name | Third question |
          | Question text | Third question |
          | Default mark  | 2.0 |
        And I follow "My courses"
        And I follow "Course 2"
        And I turn editing mode on
        And I add a "Assignment" to section "1" and I fill the form with:
          | Assignment name | Assign |
          | Description | Submit your online text |
          | assignsubmission_onlinetext_enabled | 1 |
          | assignsubmission_onlinetext_wordlimit_enabled | 1 |
          | assignsubmission_onlinetext_wordlimit | 10 |
          | assignsubmission_file_enabled | 0 |
        And I log out
        # Submit the quiz as the first user.
        And I log in as "student1"
        And I follow "My courses"
        And I follow "Course 1"
        And I follow "Test Quiz"
        And I press "Attempt quiz"
        And I click on "True" "radio" in the "First question" "question"
        And I set the field with xpath "//div[@role='textbox']" to "This is my answer to the second question"
        And I press "Finish attempt ..."
        And I press "Submit all and finish"
        And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
        And I log out
        # Submit the assignment as the second user.
        And I log in as "student2"
        And I follow "My courses"
        And I follow "Course 2"
        And I follow "Assign"
        When I press "Add submission"
        And I set the following fields to these values:
          | Online text | 7 8 9 10. |
        And I press "Save changes"
        Then I should see "Submitted for grading"
        And I log out
        #Validate both the quiz and assignment show up
        When I log in as "admin"
        And I run the scheduled task "block_grade_me\task\cache_grade_data"
        And I am on site homepage
        Then I should see "C1" in the "Grade Me" "block"
        And I should see "Test Quiz" in the "Grade Me" "block"
        #Now validate the assignment shows up
        Then I should see "C2" in the "Grade Me" "block"
        And I should see "Assign" in the "Grade Me" "block"
        And I log out
        #Validate both show up after reset block is run
        When I log in as "admin"
        And I run the scheduled task "block_grade_me\task\reset_block"
        And I am on site homepage
        Then I should see "C1" in the "Grade Me" "block"
        And I should see "Test Quiz" in the "Grade Me" "block"
        #validate the assignment shows up
        And I am on site homepage
        Then I should see "C2" in the "Grade Me" "block"
        And I should see "Assign" in the "Grade Me" "block"
