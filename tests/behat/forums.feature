@block @block_grade_me @javascript @block_grade_me_forums
Feature: Forum posts are displayed in the block

    Background:
        Given the grade me block is present on all pages.
        And the following "users" exist:
          | username | firstname | lastname | email |
          | teacher1 | John | Doe | teacher1@example.com |
          | student1 | Janie | Doe | student1@example.com |
        And the following "courses" exist:
          | fullname | shortname | category |
          | Course 1 | C1 | 0 |
        And the following "course enrolments" exist:
          | user | course | role |
          | admin | C1 | editingteacher |
          | teacher1 | C1 | editingteacher |
          | student1 | C1 | student |
        And the following config values are set as admin:
          | config | value |
          | block_grade_me_enableadminviewall | 1 |
          | block_grade_me_enableforum | 1 |

    Scenario: Forum posts using "Average of ratings" show up in the block
        Given the following "activities" exist:
          | activity | course | idnumber | name | assessed | scale |
          | forum | C1 | testforum | Test Forum | 1 | 100 |
        # First the teacher creates an initial post.
        When I log in as "admin"
        And I follow "My courses"
        And I follow "Course 1"
        And I add a new discussion to "Test Forum" forum with:
          | Subject | A test discussion topic     |
          | Message | This is a test discussion topic |
        And I log out
        # Now the student submits a reply.
        When I log in as "student1"
        And I follow "My courses"
        And I follow "Course 1"
        And I follow "Test Forum"
        And I follow "A test discussion topic"
        And I follow "Reply"
        And I set the field "post" to "This is a test reply"
        And I press "Post to forum"
        And I log out
        # Now we check the block.
        When I log in as "admin"
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"
        When I run the scheduled task "block_grade_me\task\cache_grade_data"
        And I am on site homepage
        Then I should see "C1" in the "Grade Me" "block"
        And I should see "Test Forum" in the "Grade Me" "block"
        And I should not see "Janie Doe" in the "Grade Me" "block"
        When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
        Then I should see "Janie Doe" in the "Grade Me" "block"
        And "//li[contains(@class, 'gradable')]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        # Now we rate the post and verify it disappears from the block.
        When I click on "//li[contains(@class, 'gradable')]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
        And I set the field with xpath "//div[contains(@class, 'forumpost')]//select[@name='rating']" to "60"
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"

    Scenario: Forum posts using "Count of ratings" show up in the block
        Given the following "activities" exist:
          | activity | course | idnumber | name | assessed | scale |
          | forum | C1 | testforum | Test Forum | 2 | 100 |
        # First the teacher creates an initial post.
        When I log in as "admin"
        And I follow "My courses"
        And I follow "Course 1"
        And I add a new discussion to "Test Forum" forum with:
          | Subject | A test discussion topic     |
          | Message | This is a test discussion topic |
        And I log out
        # Now the student submits a reply.
        When I log in as "student1"
        And I follow "My courses"
        And I follow "Course 1"
        And I follow "Test Forum"
        And I follow "A test discussion topic"
        And I follow "Reply"
        And I set the field "post" to "This is a test reply"
        And I press "Post to forum"
        And I log out
        # Now we check the block.
        When I log in as "admin"
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"
        When I run the scheduled task "block_grade_me\task\cache_grade_data"
        And I am on site homepage
        Then I should see "C1" in the "Grade Me" "block"
        And I should see "Test Forum" in the "Grade Me" "block"
        And I should not see "Janie Doe" in the "Grade Me" "block"
        When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
        Then I should see "Janie Doe" in the "Grade Me" "block"
        And "//li[contains(@class, 'gradable')]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        # Now we rate the post and verify it disappears from the block.
        When I click on "//li[contains(@class, 'gradable')]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
        And I set the field with xpath "//div[contains(@class, 'forumpost')]//select[@name='rating']" to "60"
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"

    Scenario: Forum posts using "Sum of ratings" show up in the block
        Given the following "activities" exist:
          | activity | course | idnumber | name | assessed | scale |
          | forum | C1 | testforum | Test Forum | 5 | 100 |
        # First the teacher creates an initial post.
        When I log in as "admin"
        And I follow "My courses"
        And I follow "Course 1"
        And I add a new discussion to "Test Forum" forum with:
          | Subject | A test discussion topic     |
          | Message | This is a test discussion topic |
        And I log out
        # Now the student submits a reply.
        When I log in as "student1"
        And I follow "My courses"
        And I follow "Course 1"
        And I follow "Test Forum"
        And I follow "A test discussion topic"
        And I follow "Reply"
        And I set the field "post" to "This is a test reply"
        And I press "Post to forum"
        And I log out
        # Now we check the block.
        When I log in as "admin"
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"
        When I run the scheduled task "block_grade_me\task\cache_grade_data"
        And I am on site homepage
        Then I should see "C1" in the "Grade Me" "block"
        And I should see "Test Forum" in the "Grade Me" "block"
        And I should not see "Janie Doe" in the "Grade Me" "block"
        When I click on "dd.module div.toggle" "css_element" in the "Grade Me" "block"
        Then I should see "Janie Doe" in the "Grade Me" "block"
        And "//li[contains(@class, 'gradable')]//a[contains(@title, 'Grade assignment')]" "xpath_element" should exist in the "Grade Me" "block"
        # Now we rate the post and verify it disappears from the block.
        When I click on "//li[contains(@class, 'gradable')]//a[contains(@title, 'Grade assignment')]" "xpath_element" in the "Grade Me" "block"
        And I set the field with xpath "//div[contains(@class, 'forumpost')]//select[@name='rating']" to "60"
        And I am on site homepage
        Then I should see "Nothing to grade!" in the "Grade Me" "block"
