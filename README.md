# tide_event_atdw
Import Events from Australian Tourism Data Warehouse.

# CONTENTS OF THIS FILE
* Introduction
* Requirements
* Usage

# INTRODUCTION
The Tide Event ATDW module provides the functionality to import events from 
Australian Tourism Data Warehouse.

# REQUIREMENTS
* [Tide Event](https://github.com/dpc-sdp/tide_event)
* [Migrate Plus](https://drupal.org/project/migrate_plus)
* [Migrate File](https://drupal.org/project/migrate_file)
* [Migrate Tools](https://drupal.org/project/migrate_tools)

# USAGE
* Configure the Migration default settings: `/admin/structure/types/manage/event`
* Run migration via Drush command:

```
drush migrate-import tide_event_atdw --execute-dependencies
```

* Force update all previously imported events:

```
drush migrate-import tide_event_atdw --execute-dependencies --update
```

* Reset the status of all migrations:

```
drush migrate-reset-status tide_event_atdw
drush migrate-reset-status tide_event_atdw_details
drush migrate-reset-status tide_event_atdw_image
drush migrate-reset-status tide_event_atdw_image_file
```

* When running from Migrate Tools UI, the migrations must be executed in the 
following order:

  1. tide_event_atdw_image_file
  2. tide_event_atdw_image
  3. tide_event_atdw_details
  4. tide_event_atdw

* If running via Migrate Cron (not recommended), only `tide_event_atdw` should
be executed with the option `Execute dependencies` enabled.
