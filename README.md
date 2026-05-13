# WowSQL PHP SDK

The official PHP SDK for [WowSQL](https://wowsqlconnect.com). Provides a clean, chainable interface for all PostgREST database operations, authentication, file storage, and schema management.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Client Configuration](#client-configuration)
- [Database Operations](#database-operations)
  - [get — Query Records](#get--query-records)
  - [getById — Fetch by Primary Key](#getbyid--fetch-by-primary-key)
  - [create / insert — Insert a Record](#create--insert--insert-a-record)
  - [bulkInsert — Insert Multiple Records](#bulkinsert--insert-multiple-records)
  - [upsert — Insert or Update](#upsert--insert-or-update)
  - [update — Update by ID](#update--update-by-id)
  - [delete — Delete by ID](#delete--delete-by-id)
- [Query Builder](#query-builder)
  - [select — Choose Columns](#select--choose-columns)
  - [Filtering](#filtering)
  - [orderBy — Sort Results](#orderby--sort-results)
  - [groupBy — Aggregate Groups](#groupby--aggregate-groups)
  - [limit / offset — Pagination](#limit--offset--pagination)
  - [paginate — Page-Based Pagination](#paginate--page-based-pagination)
  - [first — Single Record](#first--single-record)
  - [single — Exactly One Record](#single--exactly-one-record)
  - [count — Total Count](#count--total-count)
  - [sum / avg — Aggregates](#sum--avg--aggregates)
- [Authentication](#authentication)
  - [signUp](#signup)
  - [signIn](#signin)
  - [getUser](#getuser)
  - [OAuth — Google, GitHub, etc.](#oauth--google-github-etc)
  - [forgotPassword / resetPassword](#forgotpassword--resetpassword)
  - [sendOtp / verifyOtp](#sendotp--verifyotp)
  - [sendMagicLink](#sendmagiclink)
  - [verifyEmail / resendVerification](#verifyemail--resendverification)
  - [refreshToken](#refreshtoken)
  - [changePassword / updateUser](#changepassword--updateuser)
  - [logout](#logout)
- [File Storage](#file-storage)
  - [createBucket](#createbucket)
  - [upload / uploadFromPath](#upload--uploadfrompath)
  - [listFiles / download / deleteFile](#listfiles--download--deletefile)
  - [getPublicUrl](#getpublicurl)
- [Schema Management](#schema-management)
  - [createTable](#createtable)
  - [addColumn / dropColumn / renameColumn](#addcolumn--dropcolumn--renamecolumn)
  - [dropTable / executeSql](#droptable--executesql)
- [Error Handling](#error-handling)
- [Response Format](#response-format)

---

## Requirements

- PHP 7.4 or higher
- `ext-curl` enabled
- Composer

---

## Installation

```bash
composer require wowsql/wowsql-sdk
```

---

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use WOWSQL\WOWSQLClient;

// Initialize client
$client = new WOWSQLClient('myproject', 'wowsql_anon_...');

// Insert a record
$user = $client->table('users')->create([
    'email' => 'alice@example.com',
    'name'  => 'Alice',
]);

// Query with filters
$result = $client->table('users')
    ->select('id', 'email', 'name')
    ->eq('is_active', true)
    ->orderBy('created_at', true)   // true = descending
    ->limit(10)
    ->get();

foreach ($result['data'] as $u) {
    echo $u['email'] . "\n";
}

$client->close();
```

---

## Client Configuration

```php
$client = new WOWSQLClient(
    'myproject',              // Project slug, hostname, or full URL
    'wowsql_anon_...',        // Anonymous key (or service role key)
    'wowsqlconnect.com',      // base_domain — default, override for self-hosting
    true,                     // $secure — use HTTPS (default: true)
    30,                       // $timeout — seconds (default: 30)
    true                      // $verifySsl (default: true)
);
```

**project_url formats accepted:**

| Format | Description |
|--------|-------------|
| `"myproject"` | Appends `.wowsqlconnect.com` |
| `"myproject.wowsqlconnect.com"` | Full hostname |
| `"https://myproject.wowsqlconnect.com"` | Full URL |
| `"https://your-self-hosted-domain.com"` | Self-hosted instance |

**API Key types:**

| Key Prefix | Purpose |
|------------|---------|
| `wowsql_anon_...` | Public / client-side operations |
| `wowsql_service_...` | Privileged server-side operations (schema management, admin) |

---

## Database Operations

### get — Query Records

```php
// All records
$result = $client->table('products')->get();

// With chained filters
$result = $client->table('products')
    ->select('id', 'name', 'price')
    ->eq('category', 'electronics')
    ->orderBy('price')            // ascending
    ->limit(20)
    ->offset(0)
    ->get();

$result['data'];    // Array of records
$result['count'];   // Records returned this page
$result['total'];   // Total matching records
$result['limit'];   // Applied limit
$result['offset'];  // Applied offset
```

### getById — Fetch by Primary Key

```php
$user = $client->table('users')->getById('550e8400-e29b-41d4-a716-446655440000');
echo $user['email'];
```

### create / insert — Insert a Record

```php
$product = $client->table('products')->create([
    'name'     => 'Widget Pro',
    'price'    => 29.99,
    'category' => 'tools',
    'in_stock' => true,
]);
echo $product['id'];
```

`insert()` is an alias for `create()`.

### bulkInsert — Insert Multiple Records

```php
$records = [
    ['name' => 'Item A', 'price' => 10.00],
    ['name' => 'Item B', 'price' => 20.00],
    ['name' => 'Item C', 'price' => 30.00],
];
$results = $client->table('products')->bulkInsert($records);
echo "Inserted " . count($results) . " records";
```

### upsert — Insert or Update

```php
$record = $client->table('settings')->upsert(
    ['id' => 'user-uuid', 'theme' => 'dark', 'language' => 'en'],
    'id'   // on_conflict column (default: "id")
);
```

### update — Update by ID

```php
$updated = $client->table('users')->update(
    '550e8400-e29b-41d4-a716-446655440000',
    ['name' => 'Alice Smith', 'updated_at' => date('c')]
);
echo $updated['name'];
```

### delete — Delete by ID

```php
$deleted = $client->table('users')->delete('550e8400-e29b-41d4-a716-446655440000');
```

---

## Query Builder

All query builder methods return `$this` and are fully chainable. Call `get()` at the end to execute.

### select — Choose Columns

```php
$client->table('users')->select('id', 'email', 'name')->get();
```

### Filtering

#### Available operators

| Method | PostgREST operator | Description |
|--------|--------------------|-------------|
| `eq($col, $val)` | `eq` | Equals |
| `neq($col, $val)` | `neq` | Not equals |
| `gt($col, $val)` | `gt` | Greater than |
| `gte($col, $val)` | `gte` | Greater than or equal |
| `lt($col, $val)` | `lt` | Less than |
| `lte($col, $val)` | `lte` | Less than or equal |
| `like($col, $pat)` | `like` | SQL LIKE pattern |
| `ilike($col, $pat)` | `ilike` | Case-insensitive LIKE |
| `isNull($col)` | `is.null` | Column is NULL |
| `isNotNull($col)` | `not.is.null` | Column is not NULL |
| `in($col, $arr)` | `in.(...)` | Column in list |
| `notIn($col, $arr)` | `not.in.(...)` | Column not in list |
| `between($col, $min, $max)` | `gte+lte` | Inclusive range |
| `notBetween($col, $min, $max)` | `lt+gt` | Outside range |
| `filter($col, $op, $val)` | any above | Generic filter |
| `orWhere($col, $op, $val)` | OR | OR condition |

```php
// Chained filters (all AND by default)
$result = $client->table('orders')
    ->gte('total', 100)
    ->lte('total', 500)
    ->eq('status', 'shipped')
    ->get();

// LIKE / ILIKE
$result = $client->table('products')
    ->ilike('name', '%widget%')
    ->get();

// IN list
$result = $client->table('users')
    ->in('role', ['admin', 'manager'])
    ->get();

// NULL check
$result = $client->table('users')->isNull('deleted_at')->get();

// Date range
$result = $client->table('orders')
    ->between('created_at', '2025-01-01', '2025-12-31')
    ->get();
```

### orderBy — Sort Results

```php
// Ascending
$client->table('products')->orderBy('price')->get();

// Descending (pass true)
$client->table('products')->orderBy('created_at', true)->get();

// Multiple columns
$client->table('products')
    ->order('category', 'asc')
    ->order('price', 'desc')
    ->get();
```

### groupBy — Aggregate Groups

```php
$result = $client->table('orders')
    ->select('status', 'sum(total)', 'count(*)')
    ->groupBy('status')
    ->get();
```

### limit / offset — Pagination

```php
$result = $client->table('products')->limit(20)->offset(40)->get();
```

### paginate — Page-Based Pagination

```php
$result = $client->table('products')->paginate(3, 20);

$result['data'];        // Array of records
$result['page'];        // 3
$result['per_page'];    // 20
$result['total'];       // Total matching records
$result['total_pages']; // Total pages
```

### first — Single Record

```php
$user = $client->table('users')->eq('email', 'alice@example.com')->first();
// Returns null if not found
```

### single — Exactly One Record

```php
try {
    $user = $client->table('users')->eq('email', 'alice@example.com')->single();
} catch (\WOWSQL\WOWSQLException $e) {
    echo "Not found or multiple records: " . $e->getMessage();
}
```

### count — Total Count

```php
$total = $client->table('users')->eq('is_active', true)->count();
echo "Active users: $total";
```

### sum / avg — Aggregates

```php
$totalRevenue = $client->table('orders')->eq('status', 'completed')->sum('total');
$avgPrice     = $client->table('products')->eq('category', 'electronics')->avg('price');
```

---

## Authentication

```php
use WOWSQL\ProjectAuthClient;

$auth = new ProjectAuthClient(
    'myproject',
    'wowsql_anon_...',
    'wowsqlconnect.com',  // base_domain
    true,                  // secure
    30,                    // timeout
    true                   // verify_ssl
);
```

### signUp

```php
$response = $auth->signUp(
    'alice@example.com',
    'SecurePass123!',
    'Alice Smith',          // full_name (optional)
    ['plan' => 'pro']       // user_metadata (optional)
);

echo $response['access_token'];
echo $response['user']['id'];
echo $response['user']['email'];
```

### signIn

```php
$response = $auth->signIn('alice@example.com', 'SecurePass123!');

echo $response['access_token'];
echo $response['refresh_token'];
```

### getUser

```php
$user = $auth->getUser($accessToken);

echo $user['id'];
echo $user['email'];
echo $user['full_name'];
echo $user['email_verified'] ? 'verified' : 'unverified';
```

### OAuth — Google, GitHub, etc.

**Step 1: Get the authorization URL**

```php
$oauth = $auth->getOAuthAuthorizationUrl(
    'google',
    'https://myapp.com/auth/callback'   // frontend_redirect_uri
);

// Redirect browser to:
header('Location: ' . $oauth['authorization_url']);
```

**Step 2: Exchange the callback code**

```php
$result = $auth->exchangeOAuthCallback(
    'google',
    $_GET['code'],
    'https://myapp.com/auth/callback'
);

echo $result['access_token'];
echo $result['user']['email'];
```

### forgotPassword / resetPassword

```php
// Send reset email
$auth->forgotPassword('alice@example.com');

// Reset with token from email link
$auth->resetPassword(
    'reset_token_from_email',
    'NewSecurePass456!'
);
```

### sendOtp / verifyOtp

```php
// Send OTP
$auth->sendOtp('alice@example.com', 'login');

// Verify OTP
$response = $auth->verifyOtp(
    'alice@example.com',
    '123456',
    'login'
);
echo $response['access_token'];
```

Purposes: `"login"`, `"signup"`, `"password_reset"`.

### sendMagicLink

```php
$auth->sendMagicLink('alice@example.com', 'login');
```

Purposes: `"login"`, `"signup"`, `"email_verification"`.

### verifyEmail / resendVerification

```php
// Verify email from link
$result = $auth->verifyEmail('verification_token_from_email');
echo $result['success'] ? 'Verified' : 'Failed';

// Resend
$auth->resendVerification('alice@example.com');
```

### refreshToken

```php
$response = $auth->refreshToken($refreshToken);
echo $response['access_token'];
```

### changePassword / updateUser

```php
// Change password
$auth->changePassword($accessToken, 'OldPass123!', 'NewPass456!');

// Update profile
$user = $auth->updateUser($accessToken, [
    'full_name'     => 'Alice Smith',
    'avatar_url'    => 'https://cdn.example.com/avatar.jpg',
    'user_metadata' => ['bio' => 'Developer'],
]);
```

### logout

```php
$auth->logout($accessToken);
```

---

## File Storage

```php
use WOWSQL\WOWSQLStorage;

$storage = new WOWSQLStorage(
    'myproject',
    'wowsql_anon_...',
    'wowsqlconnect.com',  // base_domain
    true,                  // secure
    60,                    // timeout
    true                   // verify_ssl
);
```

### createBucket

```php
$bucket = $storage->createBucket('avatars', [
    'public'             => true,
    'file_size_limit'    => 5 * 1024 * 1024,  // 5 MB
    'allowed_mime_types' => ['image/jpeg', 'image/png'],
]);
echo $bucket['name'];
echo $bucket['public'] ? 'public' : 'private';
```

### upload / uploadFromPath

```php
// Upload from file handle
$fp   = fopen('/local/photo.jpg', 'rb');
$file = $storage->upload('avatars', $fp, 'users/alice.jpg');
fclose($fp);
echo $file['path'];

// Upload from path
$file = $storage->uploadFromPath('/local/photo.jpg', 'avatars', 'users/alice.jpg');
```

### listFiles / download / deleteFile

```php
// List files
$files = $storage->listFiles('avatars', 'users/', 50);
foreach ($files as $f) {
    echo $f['path'] . " (" . round($f['size'] / 1048576, 2) . " MB)\n";
}

// Download to variable
$content = $storage->download('avatars', 'users/alice.jpg');

// Download to disk
$storage->downloadToFile('avatars', 'users/alice.jpg', '/local/alice.jpg');

// Delete
$storage->deleteFile('avatars', 'users/alice.jpg');
```

### getPublicUrl

```php
$url = $storage->getPublicUrl('avatars', 'users/alice.jpg');
echo $url;   // https://myproject.wowsqlconnect.com/api/v1/storage/...
```

---

## Schema Management

Schema operations require a **service role key** (`wowsql_service_...`).

```php
use WOWSQL\WOWSQLSchema;

$schema = new WOWSQLSchema(
    'myproject',
    'wowsql_service_...',
    'wowsqlconnect.com',  // base_domain
    true                   // secure
);
```

### createTable

```php
$schema->createTable('products', [
    ['name' => 'id',         'type' => 'UUID',         'auto_increment' => true],
    ['name' => 'name',       'type' => 'VARCHAR(255)',  'nullable' => false],
    ['name' => 'price',      'type' => 'DECIMAL(10,2)', 'nullable' => false],
    ['name' => 'category',   'type' => 'VARCHAR(100)'],
    ['name' => 'metadata',   'type' => 'JSONB',         'default' => "'{}'"],
    ['name' => 'created_at', 'type' => 'TIMESTAMPTZ',   'default' => 'CURRENT_TIMESTAMP'],
], 'id', ['category', 'name']);   // primary_key, indexes
```

### addColumn / dropColumn / renameColumn

```php
// Add a column
$schema->addColumn('products', 'sku', 'VARCHAR(50)', true);   // nullable=true

// Drop a column
$schema->dropColumn('products', 'old_field');

// Rename a column
$schema->renameColumn('products', 'sku', 'product_sku');

// Modify column type
$schema->modifyColumn('products', 'price', 'NUMERIC(12,2)', false);
```

### dropTable / executeSql

```php
// Drop table (irreversible)
$schema->dropTable('products', false);   // cascade=false

// Execute raw DDL SQL
$schema->executeSql("CREATE INDEX idx_products_category ON products (category)");
$schema->executeSql("ALTER TABLE users ADD COLUMN last_login TIMESTAMPTZ");
```

---

## Error Handling

All SDK errors are instances of `WOWSQL\WOWSQLException`.

```php
try {
    $result = $client->table('orders')->getById('some-id');
} catch (\WOWSQL\WOWSQLException $e) {
    echo $e->getMessage();    // Human-readable message
    echo $e->getStatusCode(); // HTTP status code (400, 401, 403, 404, 500, etc.)
    print_r($e->getResponse()); // Raw response body array
}
```

**Storage-specific errors:**

```php
try {
    $storage->upload('avatars', $largeFile, 'big.mov');
} catch (\WOWSQL\StorageLimitExceededException $e) {
    echo "File too large: " . $e->getMessage();
} catch (\WOWSQL\StorageException $e) {
    echo "Storage error: " . $e->getMessage();
}
```

**Schema-specific errors:**

```php
try {
    $schema->dropTable('important_table');
} catch (\WOWSQL\SchemaPermissionException $e) {
    echo "Permission denied — use a service role key";
} catch (\WOWSQL\WOWSQLException $e) {
    echo "Schema error: " . $e->getMessage();
}
```

---

## Response Format

All `get()` and query builder calls return a consistent array:

```php
[
    'data'   => [...],   // Array of records
    'count'  => 10,      // Records in this response
    'total'  => 120,     // Total matching records (from Content-Range)
    'limit'  => 20,      // Applied limit
    'offset' => 0,       // Applied offset
]
```

Single-record operations (`create`, `update`, `delete`, `getById`, `upsert`) return a plain associative array of the record.

Pagination (`paginate`) returns:

```php
[
    'data'        => [...],
    'page'        => 2,
    'per_page'    => 20,
    'total'       => 120,
    'total_pages' => 6,
]
```

---

## License

MIT License — see [LICENSE](LICENSE) for details.
