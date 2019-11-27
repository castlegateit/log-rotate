# Log Rotate

A simple PHP-based monthly log rotation class.

## Basic Usage

The log rotator looks within a specified directory for logs of a matching file format and extension and attempts to delete logs older that your retention rules.

By default logs are required to use the following name format: `log-name-YYYY-MM.log` for example:

```
contact-form-2019-11.csv
import-results-2019-01.txt
access-2019-12.log
```


```php
<?php

use Castlegate\LogRotate;

// Create a log rotator
$rotator = new LogRotate('/path/to/logs');

// Set file extension and retention months
$rotator->setExtensions('log');
$rotator->setRetention(3);

// Rotate logs
$rotator->rotate();
```

## Advanced Usage

### Dry run

When set to run in dry-run mode, no logs will be deleted, but deletions will be recorded in the PHP error logs.

```php
$rotator->dryRun();
```

### Multiple file extensions

Multiple file extensions can be added.

```php
$rotator->setExtensions(['log', 'txt']);
```
