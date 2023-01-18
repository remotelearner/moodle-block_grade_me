@block @block_grade_me @javascript @block_grade_me_quiz
Feature: Quizzes are supported by the block.

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
        And the following "course enrolments" exist:
          | user | course | role |
          | admin | C1 | editingteacher |
          | teacher1 | C1 | editingteacher |
          | student1 | C1 | student |
          | student2 | C1 | student |
        And the following config values are set as admin:
          | config | value |
          | block_grade_me_enableadminviewall | 1 |
          | block_grade_me_enablequiz | 1 |

    Scenario: A completed quiz attempt shows up in the block
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
        And I log out
        # Submit the quiz as the first user.
        And I log in as "student1"
        And I follow "My courses"
        And I follow "Course 1"
        And I follow "Test Quiz"
        And I press "Attempt quiz"
        And I click on "True" "radio" in the "First question" "question"
        And I set the hidden field with xpath "//textarea[contains(@id, '2_answer_id')]" to "This is my answer to the second question"
        And I set the hidden field with xpath "//textarea[contains(@id, '3_answer_id')]" to "This is my answer to the third question"
        And I press "Finish attempt ..."
        And I press "Submit all and finish"
        And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
        And I log out
        # Submit the quiz as the second user.
        And I log in as "student2"
        And I follow "My courses"
        And I follow "Course 1"
        And I follow "Test Quiz"
        And I press "Attempt quiz"
        And I click on "True" "radio" in the "First question" "question"
        And I set the hidden field with xpath "//textarea[contains(@id, '2_answer_id')]" to "This is my answer to the second question"
        And I set the hidden field with xpath "//textarea[contains(@id, '3_answer_id')]" to "This is my answer to the third question"
        And I press "Finish attempt ..."
        And I press "Submit all and finish"
        And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
        And I log out
        # Log in as admin and verify block contents.
        When I log in as "admin"
        And I run the scheduled task "block_grade_me\task\cache_grade_data"
        And I am on site homepage
        Then I should see "C1" in the "Grade Me" "block"
        And I should see "Test Quiz" in the "Grade Me" "block"
        And I should not see "Johnny Doe" in the "Grade Me" "block"
        And I should not see "Janie Doe" in the "Grade Me" "block"
        When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
        Then I should see "Johnny Doe" in the "Grade Me" "block"
        And I should see "Janie Doe" in the "Grade Me" "block"
        And "//dd[@class='module']" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[2]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[2]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        # Grade the first student's submission.
        When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
        And I click on "//div[starts-with(@id, 'question-') and contains(@id, '-2')]//div[@class='commentlink']//a[contains(@href, 'slot=2')]" "xpath_element"
        And I switch to "commentquestion" window
        And I set the field "Mark" to "2"
        And I press "Save"
        And I switch to the main window
        # After grading one of two questions, we verify both users still appear in the block.
        Then "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[2]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[2]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        And I click on "//div[starts-with(@id, 'question-') and contains(@id, '-3')]//div[@class='commentlink']//a[contains(@href, 'slot=3')]" "xpath_element"
        And I switch to "commentquestion" window
        And I set the field "Mark" to "2"
        And I press "Save"
        And I switch to the main window
        And I am on site homepage
        # After grading both questions for the first user we verify only one user remains in the block.
        Then "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[2]" "xpath_element" should not exist in the "Grade Me" "block"
        When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
        Then I should not see "Johnny Doe" in the "Grade Me" "block"
        And I should see "Janie Doe" in the "Grade Me" "block"
        # Grade the second student's submission.
        When I click on "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
        And I click on "//div[starts-with(@id, 'question-') and contains(@id, '-2')]//div[@class='commentlink']//a[contains(@href, 'slot=2')]" "xpath_element"
        And I switch to "commentquestion" window
        And I set the field "Mark" to "2"
        And I press "Save" and switch to main window
        # After grading one of two questions, we verify user still appears in the block.
        Then "//dd[@class='module']//ul//li[1]" "xpath_element" should exist in the "Grade Me" "block"
        And "//dd[@class='module']//ul//li[1]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        # Grade the last question, after which no more items should appear in the block.
        And I click on "//div[starts-with(@id, 'question-') and contains(@id, '-3')]//div[@class='commentlink']//a[contains(@href, 'slot=3')]" "xpath_element"
        And I switch to "commentquestion" window
        And I set the field "Mark" to "2"
        And I press "Save" and switch to main window
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"
