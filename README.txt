Edit groups report

This 'report' is actually a tool that lets you edit all the groups settings for
all the activities in your course on a single page.

You can install it from the Moodle plugins database
http://moodle.org/plugins/view.php?plugin=report_editgroups

Alternatively, you can install it using git. In the top-level folder of your
Moodle install, type the command:
    git clone git://github.com/moodleou/moodle-report_editgroups.git report/editgroups
    echo '/report/editgroups/' >> .git/info/exclude

Then visit the admin screen to allow the install to complete.

Once the plugin is installed, you can access the functionality by going to
Reports -> Groups in the Course adminstration block.
