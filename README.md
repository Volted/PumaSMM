# PumaSMM - Storage Manager Module

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**Storage Manager Module for Puma - Apache based micro services.**

A lightweight, manifest-driven PHP database abstraction layer that simplifies MySQL database operations through an intuitive API. PumaSMM provides CRUD operations, flexible query building, and automatic table management based on data manifests.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
    - [Data Manifest](#data-manifest)
    - [Data Types](#data-types)
- [Configuration](#configuration)
- [API Reference](#api-reference)
    - [CRUD Operations](#crud-operations)
    - [Conditions](#conditions)
    - [Sorting & Pagination](#sorting--pagination)
- [Error Handling](#error-handling)
- [Advanced Usage](#advanced-usage)
    - [Multi-Table Operations](#multi-table-operations)
    - [Chaining Methods](#chaining-methods)
- [License](#license)

---

## Requirements

- **PHP**: 8.2 or higher
- **Extensions**:
    - `ext-mysqli`
    - `ext-json`
- **Database**: MySQL / MariaDB

---

## Installation

Install via Composer:

```bash
composer require pumasoft/puma-smm
```

---

## Quick Start

```php
$manifest = [
    'users' => [
        'id' => Storage::UNIQUE_INTEGER_MAIN_KEY,
        'username' => Storage::STRING,
        'email' => Storage::STRING,
        'created_at' => Storage::DATE_TIME,
        'is_active' => Storage::BOOLEAN,
    ],
];

try {
    // Initialize the database connection
    $db = new DB($manifest);
    $db->setConfig('config.ini');
    $db->connect();

    // Create a new record
    $result = $db->create([
        'username' => 'john_doe',
        'email' => 'john@example.com',
        'is_active' => 1,
    ]);

    // Read records with conditions
    $users = $db->read(
        ['id', 'username', 'email'],
        $db->matching(Storage::AND, ['is_active' => 1])
    );

    // Update records
    $db->update(
        ['email' => 'newemail@example.com'],
        $db->matching(Storage::AND, ['id' => 1])
    );

    // Delete records
    $db->delete($db->matching(Storage::AND, ['id' => 1]));

    $db->closeConnection();
} catch (DataRawr $e) {
    $e->handleException();
}
```

---

## Core Concepts

### Data Manifest

The **Data Manifest** is the heart of PumaSMM. It defines your database schema as a PHP array, mapping tables to their columns and data types.

```php
$manifest = [
    'users' => [
        'id' => Storage::UNIQUE_INTEGER_MAIN_KEY,
        'name' => Storage::STRING,
        // Add more columns as needed
    ],
];
```

#### Multi-Table Manifest Example

```php
$manifest = [
    'orders' => [
        'id' => Storage::UNIQUE_INTEGER_MAIN_KEY,
        'user_id' => Storage::INTEGER,
        'total' => Storage::FLOAT,
    ],
    'order_details' => [
        'id' => Storage::UNIQUE_INTEGER,
        'order_id' => Storage::INTEGER,
        'product' => Storage::STRING,
    ],
];
```

> **Important**: One table must contain a column with `UNIQUE_INTEGER_MAIN_KEY` - this defines the primary table and main key for the entire manifest.

### Data Types

| Constant                           | MySQL Type                                    | Description                       |
|------------------------------------|-----------------------------------------------|-----------------------------------|
| `Storage::STRING`                  | `VARCHAR(255) NOT NULL DEFAULT ''`            | Text strings up to 255 characters |
| `Storage::INTEGER`                 | `INT NOT NULL DEFAULT '0'`                    | Standard integer values           |
| `Storage::UNIQUE_INTEGER`          | `INT NOT NULL AUTO_INCREMENT`                 | Auto-incrementing integer         |
| `Storage::UNIQUE_INTEGER_MAIN_KEY` | `INT NOT NULL AUTO_INCREMENT`                 | Primary key for the main table    |
| `Storage::BOOLEAN`                 | `TINYINT NOT NULL DEFAULT '0'`                | Boolean (0 or 1)                  |
| `Storage::DATE`                    | `DATE NOT NULL DEFAULT '1979-04-20'`          | Date only                         |
| `Storage::DATE_TIME`               | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | Date and time                     |
| `Storage::FLOAT`                   | `DECIMAL(19,2) NOT NULL DEFAULT '0.00'`       | Decimal numbers                   |
| `Storage::BLOB`                    | `BLOB NOT NULL DEFAULT ''`                    | Binary large objects              |

### Date Formats

When working with dates, use these format constants:

```php
Storage::FORMAT_DATETIME // 'Y-m-d H:i:s'
Storage::FORMAT_TIME // 'H:i:s'
Storage::FORMAT_DATE // 'Y-m-d'
```

---

## Configuration

PumaSMM supports both **INI** and **JSON** configuration files.

### INI Configuration

```ini
[database]
charset = utf8mb4
host = localhost
db = my_database
username = db_user
password = your_password
```

### JSON Configuration

```json
{
    "database": {
        "charset": "utf8mb4",
        "host": "localhost",
        "db": "my_database",
        "username": "db_user",
        "password": "your_password"
    }
}
```

### Loading Configuration

```php
$db = new DB($manifest);
$db->setConfig('path/to/config.ini'); // or config.json
$db->connect();
```

---

## API Reference

### CRUD Operations

#### Create

Insert new records into the database.

```php
$result = $db->create([
    'column1' => 'value1',
    'column2' => 'value2',
]);
// Returns: ['table_name' => inserted_id, ...]
```

#### Read

Retrieve records from the database.

```php
$records = $db->read(
    ['column1', 'column2'],
    $condition
);
// Returns: array of associative arrays
```

#### Update

Modify existing records.

```php
$result = $db->update(
    ['column' => 'new_value'],
    $condition
);
// Returns: ['table_name' => affected_rows, ...]
```

#### Delete

Remove records from the database.

```php
$result = $db->delete($condition);
// Returns: ['table_name' => affected_rows, ...]
```

### Conditions

Conditions are used to filter records in read, update, and delete operations.

#### Condition Operators

- `Storage::AND` - Combine conditions with AND logic
- `Storage::OR` - Combine conditions with OR logic

#### matching()

Exact match condition (uses `=` operator).

```php
$condition = $db->matching(Storage::AND, [
    'status' => 'active',
    'role' => 'admin',
]); // SQL: WHERE status = 'active' AND role = 'admin'
```

#### featuring()

Partial match condition (uses `LIKE %value%`).

```php
$condition = $db->featuring(Storage::OR, [
    'username' => 'john',
    'email' => 'john',
]); // SQL: WHERE username LIKE '%john%' OR email LIKE '%john%'
```

#### startsWith()

Match values that start with the given string (uses `LIKE value%`).

```php
$condition = $db->startsWith(Storage::AND, [
    'email' => 'admin@',
]); // SQL: WHERE email LIKE 'admin@%'
```

#### endsWith()

Match values that end with the given string (uses `LIKE %value`).

```php
$condition = $db->endsWith(Storage::AND, [
    'email' => '@example.com',
]); // SQL: WHERE email LIKE '%@example.com'
```

### Sorting & Pagination

#### sort()

Order results by a specific column.

```php
$db->sort('column_name', Storage::SMALL_TO_LARGE); // ASC
$db->sort('column_name', Storage::LARGE_TO_SMALL); // DESC
```

#### limit()

Paginate results.

```php
$db->limit(1, 10); // Page 1, 10 records per page
$db->limit(2, 10); // Page 2, 10 records per page
```

> **Note**: Page numbers start at 1, not 0.

---

## Error Handling

PumaSMM uses the `DataRawr` exception class for error handling.

### Exception Codes

| Constant                       | HTTP Code | Description        |
|--------------------------------|-----------|--------------------|
| `DataRawr::INTERNAL_ERROR`     | 500       | Server error       |
| `DataRawr::METHOD_NOT_ALLOWED` | 405       | Method not allowed |
| `DataRawr::NOT_FOUND`          | 404       | Not found          |
| `DataRawr::FORBIDDEN`          | 403       | Access denied      |
| `DataRawr::UNAUTHORIZED`       | 401       | Unauthorized       |
| `DataRawr::BAD_REQUEST`        | 400       | Bad request        |

### Handling Exceptions

```php
try {
    $db->connect();
    // ... database operations
} catch (DataRawr $e) {
    // Option 1: Let DataRawr handle the response
    $e->handleException(); // Logs error and sends JSON response

    // Option 2: Handle manually
    error_log($e->getMessage());
    // Custom error handling...
}
```

The `handleException()` method:
1. Logs the error with full stack trace
2. Sets the appropriate HTTP response code
3. Returns a JSON error response
4. Terminates script execution

---

## Advanced Usage

### Multi-Table Operations

When your manifest includes multiple tables, PumaSMM automatically handles JOINs for related data.

```php
$manifest = [
    'orders' => [
        'id' => Storage::UNIQUE_INTEGER_MAIN_KEY,
        'user_id' => Storage::INTEGER,
        'total' => Storage::FLOAT,
    ],
    'order_details' => [
        'id' => Storage::UNIQUE_INTEGER,
        'order_id' => Storage::INTEGER,
        'product' => Storage::STRING,
    ],
];

$db = new DB($manifest);
$db->setConfig('config.ini');
$db->connect();

// Reading from multiple tables automatically JOINs on the primary key
$orderDetails = $db->read(
    ['orders.id', 'order_details.product']
);
```

### Chaining Methods

Methods like `sort()` and `limit()` return the DB instance for fluent chaining:

```php
$results = $db
    ->sort('created_at', Storage::LARGE_TO_SMALL)
    ->limit(1, 25)
    ->read(
        ['id', 'username']
    );
```

### Creating Tables from Manifest

Automatically generate database tables based on your manifest:

```php
$db = new DB($manifest);
$db->setConfig('config.ini');
$db->connect();

// Creates all tables defined in the manifest
$db->createTablesFromManifest();
```

### Debug Mode

Enable query logging for debugging:

```php
$db->enableLogs();

// All subsequent queries will be logged to PHP error log
$db->read(['id', 'username'], $db->matching(Storage::AND, ['id' => 1]));
```

---

## Namespace

All classes are under the `PumaSMM` namespace:

```php
use PumaSMM\DB;
use PumaSMM\Storage;
use PumaSMM\StorageInterface;
use PumaSMM\QueryBuilder;
use PumaSMM\DataRawr;
```

---

## Class Overview

| Class              | Description                                              |
|--------------------|----------------------------------------------------------|
| `Storage`          | Abstract base class with constants and configuration     |
| `StorageInterface` | Interface defining CRUD and condition methods            |
| `DB`               | MySQL implementation of the storage interface            |
| `QueryBuilder`     | Internal query construction engine                       |
| `DataRawr`         | Exception class with HTTP response handling              |

---

## License

This project is licensed under the **MIT License**. See the [LICENSE](LICENSE) file for details.

---

## Author

**George Dryser - Pumasoft** 

---

## Contributing

Contributions are welcome! Please feel free to submit issues and pull requests.

## Important
This is a personal side project developed independently in my own time, using my own equipment and resources. It has no connection to any employer, client, or commercial work.