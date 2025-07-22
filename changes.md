# Change log for the Edit groups report


## Changes in 2.8

* This version is compatible with Moodle 5.0.
* Updated automated tests to work with newer Moodle versions.
* Fixed coding style issues.
* Log events: removed legacy logging methods for compatibility with Moodle 4.2.


## Changes in 2.7

* This version works with Moodle 4.0.
* Updated to use Moodle 3.11 navigation
* The method report_helper::save_selected_report() has been been deprecated because it is no longer used in 4.0.
  The report_helper::print_report_selector() is used to to show the dropdown, on the report page.
* Switch from Travis to Github actions


## Changes in 2.6

* Fix Behat for Moodle 3.6.


## Changes in 2.5

* Privacy API implementation.
* Setup Travis-CI automated testing integration.
* Fix some automated tests to pass with newer versions of Moodle.
* Fix some coding style.
* Due to privacy API support, this version now only works in Moodle 3.4+
  For older Moodles, you will need to use a previous version of this plugin.


## 2.4 and before

Changes were not documented here.
