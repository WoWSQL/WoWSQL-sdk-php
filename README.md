# 🚀 WOWSQL PHP SDK

Official PHP client for [WOWSQL](https://wowsql.com) - MySQL Backend-as-a-Service with S3 Storage.

## Installation

### Composer

```bash
composer require WOWSQL/WOWSQL-sdk
```

Or add to your `composer.json`:

```json
{
    "require": {
        "WOWSQL/WOWSQL-sdk": "^1.0.0"
    }
}
```

## Quick Start

### Database Operations

```php
<?php
require 'vendor/autoload.php';

use WOWSQL\WOWSQLClient;
use WOWSQL\WOWSQLException;

// Initialize client
$client = new WOWSQLClient(
    'https://your-project.wowsql.com',
    'your-api-key'  // Get from dashboard
);

// Select data
$response = $client->table('users')
    ->select('id', 'name', 'email')
    ->limit(10)
    ->get();

foreach ($response['data'] as $user) {
    echo $user['name'] . ' (' . $user['email'] . ')' . PHP_EOL;
}

// Insert data
$newUser = [
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'age' => 25
];

$client->table('users')->create($newUser);

// Update data
$updates = ['name' => 'Jane Smith'];
$client->table('users')->update(1, $updates);

// Delete data
$client->table('users')->delete(1);
```

### Storage Operations

```php
<?php
use WOWSQL\WOWSQLStorage;
use WOWSQL\StorageException;

// Initialize storage client
$storage = new WOWSQLStorage(
    'your-project-slug',
    'your-api-key'
);

// Upload file
$storage->uploadFromPath('local-file.pdf', 'uploads/document.pdf', 'documents');

// Get presigned URL
$urlData = $storage->getFileUrl('uploads/document.pdf', 3600);
echo $urlData['file_url'] . PHP_EOL;

// List files
$files = $storage->listFiles('uploads/');
foreach ($files as $file) {
    echo $file['key'] . ': ' . ($file['size'] / 1024 / 1024) . ' MB' . PHP_EOL;
}

// Delete file
$storage->deleteFile('uploads/document.pdf');

// Check storage quota
$quota = $storage->getQuota();
echo 'Used: ' . $quota['used_gb'] . ' GB' . PHP_EOL;
echo 'Available: ' . $quota['available_gb'] . ' GB' . PHP_EOL;
```

## Features

### Database Features
- ✅ Full CRUD operations (Create, Read, Update, Delete)
- ✅ Advanced filtering (eq, neq, gt, gte, lt, lte, like, isNull)
- ✅ Pagination (limit, offset)
- ✅ Sorting (orderBy)
- ✅ Table schema introspection
- ✅ Built-in error handling

### Storage Features
- ✅ S3-compatible storage client
- ✅ File upload with automatic quota validation
- ✅ File download (presigned URLs)
- ✅ File listing with metadata
- ✅ File deletion
- ✅ Storage quota management
- ✅ Multi-region support

## Usage Examples

### Select Queries

```php
// Select all columns
$users = $client->table('users')->select('*')->get();

// Select specific columns
$users = $client->table('users')
    ->select('id', 'name', 'email')
    ->get();

// With filters
$activeUsers = $client->table('users')
    ->select('id', 'name', 'email')
    ->eq('status', 'active')
    ->gt('age', 18)
    ->get();

// With ordering
$recentUsers = $client->table('users')
    ->select('*')
    ->orderBy('created_at', true)  // desc = true
    ->limit(10)
    ->get();

// With pagination
$page1 = $client->table('users')
    ->select('*')
    ->limit(20)
    ->offset(0)
    ->get();

$page2 = $client->table('users')
    ->select('*')
    ->limit(20)
    ->offset(20)
    ->get();

// Pattern matching
$gmailUsers = $client->table('users')
    ->select('*')
    ->like('email', '%@gmail.com')
    ->get();
```

### Filter Operators

```php
// Equal
->eq('status', 'active')

// Not equal
->neq('status', 'deleted')

// Greater than
->gt('age', 18)

// Greater than or equal
->gte('age', 18)

// Less than
->lt('age', 65)

// Less than or equal
->lte('age', 65)

// Pattern matching (SQL LIKE)
->like('email', '%@gmail.com')

// Is null
->isNull('deleted_at')
```

### Error Handling

```php
try {
    $users = $client->table('users')->select('*')->get();
} catch (WOWSQLException $e) {
    echo 'Database error: ' . $e->getMessage() . PHP_EOL;
    echo 'Status code: ' . $e->getStatusCode() . PHP_EOL;
}

try {
    $storage->uploadFromPath('file.pdf', 'uploads/file.pdf');
} catch (StorageLimitExceededException $e) {
    echo 'Storage full: ' . $e->getMessage() . PHP_EOL;
    echo 'Please upgrade your plan or delete old files' . PHP_EOL;
} catch (StorageException $e) {
    echo 'Storage error: ' . $e->getMessage() . PHP_EOL;
}
```

## Requirements

- PHP 7.4 or higher
- Composer
- Guzzle HTTP Client 7.0+

## Development

```bash
# Clone repository
git clone https://github.com/wowsql/wowsql-sdk-php.git
cd WOWSQL-sdk-php

# Install dependencies
composer install

# Run tests
composer test
```

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## License

MIT License - see LICENSE file for details.

## Links

- 📚 [Documentation](https://wowsql.com/docs)
- 🌐 [Website](https://wowsql.com)
- 💬 [Discord](https://discord.gg/WOWSQL)
- 🐛 [Issues](https://github.com/wowsql/wowsql/issues)

---

Made with ❤️ by the WOWSQL Team

