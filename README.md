# WowSQL PHP SDK

Official PHP client for [WowSQL](https://wowsql.com) - MySQL Backend-as-a-Service with built-in Authentication and Storage.

[![Packagist Version](https://img.shields.io/packagist/v/wowsql/wowsql-php.svg)](https://packagist.org/packages/wowsql/wowsql-sdk)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Installation

```bash
composer require wowsql/wowsql-php
```

Or add to your `composer.json`:

```json
{
    "require": {
        "wowsql/wowsql-php": "^1.0"
    }
}
```

Then run:

```bash
composer install
```

## Quick Start

### Database Operations

```php
<?php
require 'vendor/autoload.php';

use WOWSQL\WOWSQLClient;

$client = new WOWSQLClient(
    'https://your-project.wowsql.com',
    'your-api-key'
);

// Select data
$response = $client->table('users')
    ->select('id', 'name', 'email')
    ->eq('status', 'active')
    ->limit(10)
    ->get();

foreach ($response['data'] as $user) {
    echo $user['name'] . ' (' . $user['email'] . ')' . PHP_EOL;
}

// Insert data
$result = $client->table('users')->create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'age' => 25
]);
echo "Created user with ID: " . $result['id'] . PHP_EOL;

// Update data
$client->table('users')->update(1, ['name' => 'Jane Smith']);

// Delete data
$client->table('users')->delete(1);
```

### Authentication

```php
<?php
use WOWSQL\ProjectAuthClient;

$auth = new ProjectAuthClient(
    'https://your-project.wowsql.com',
    'your-anon-key'
);

// Sign up
$response = $auth->signUp('user@example.com', 'SuperSecret123', 'Demo User');
echo "User ID: " . $response->user->id . PHP_EOL;
echo "Access token: " . $response->session->accessToken . PHP_EOL;

// Sign in
$result = $auth->signIn('user@example.com', 'SuperSecret123');
echo "Logged in as: " . $result->user->email . PHP_EOL;
```

### Storage Operations

```php
<?php
use WOWSQL\WOWSQLStorage;

$storage = new WOWSQLStorage(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-api-key'
);

// Create a bucket
$storage->createBucket('avatars', ['public' => true]);

// Upload a file
$storage->uploadFromPath('/path/to/avatar.jpg', 'avatars', 'users/avatar.jpg');

// Get public URL
$url = $storage->getPublicUrl('avatars', 'users/avatar.jpg');

// Check quota
$quota = $storage->getQuota();
echo "Used: {$quota->usedGb} GB / {$quota->totalGb} GB" . PHP_EOL;
```

### Schema Management

```php
<?php
use WOWSQL\WOWSQLSchema;

$schema = new WOWSQLSchema(
    'https://your-project.wowsql.com',
    'wowsql_service_your-service-key'
);

$schema->createTable('products', [
    ['name' => 'id', 'type' => 'INT', 'auto_increment' => true],
    ['name' => 'name', 'type' => 'VARCHAR(255)', 'not_null' => true],
    ['name' => 'price', 'type' => 'DECIMAL(10,2)', 'not_null' => true],
], 'id');
```

## Features

### Database Features
- Full CRUD operations (Create, Read, Update, Delete)
- Advanced filtering (eq, neq, gt, gte, lt, lte, like, isNull, isNotNull, in, notIn, between, notBetween)
- Logical operators (AND, OR)
- GROUP BY and aggregate functions (COUNT, SUM, AVG, MAX, MIN)
- HAVING clause for filtering aggregated results
- Multiple ORDER BY columns
- Date/time functions in SELECT and filters
- Expressions in SELECT (e.g., `COUNT(*)`, `DATE(created_at) as date`)
- Pagination (limit, offset, paginate)
- Sorting (orderBy)
- Get record by ID
- Bulk insert
- Upsert (insert or update on conflict)
- Table schema introspection
- Fluent query builder API

### Authentication Features
- Email/password sign up and sign in
- OAuth provider support (GitHub, Google, etc.)
- Password reset flow (forgot + reset)
- OTP (one-time password) via email
- Magic link authentication
- Email verification and resend
- Session management (get, set, clear, refresh)
- Change password
- Update user profile
- Custom token storage interface

### Storage Features
- Bucket management (create, list, get, update, delete)
- File upload from data or local path
- File download to memory or local path
- File listing with prefix and pagination
- File deletion
- Public URL generation
- Storage statistics and quota management

### Schema Management Features
- Create, alter, and drop tables
- Add, drop, rename, and modify columns
- Create indexes
- Execute raw SQL
- Table listing and schema introspection

## Database Operations

### Select Queries

```php
<?php
use WOWSQL\WOWSQLClient;

$client = new WOWSQLClient(
    'https://your-project.wowsql.com',
    'your-api-key'
);

// Select all columns
$users = $client->table('users')->select('*')->get();

// Select specific columns
$users = $client->table('users')
    ->select('id', 'name', 'email')
    ->get();

// With filters
$activeUsers = $client->table('users')
    ->select('*')
    ->eq('status', 'active')
    ->gt('age', 18)
    ->get();

// With ordering
$recentUsers = $client->table('users')
    ->select('*')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// With pagination (limit/offset)
$page1 = $client->table('users')->select('*')->limit(20)->offset(0)->get();
$page2 = $client->table('users')->select('*')->limit(20)->offset(20)->get();

// Using paginate helper
$paginated = $client->table('users')->paginate(1, 20);
echo "Page: " . $paginated['page'] . PHP_EOL;
echo "Per page: " . $paginated['perPage'] . PHP_EOL;
echo "Total: " . $paginated['total'] . PHP_EOL;
print_r($paginated['data']);

// Pattern matching
$gmailUsers = $client->table('users')
    ->select('*')
    ->like('email', '%@gmail.com')
    ->get();

// IN operator
$categories = $client->table('products')
    ->select('*')
    ->in('category', ['electronics', 'books', 'clothing'])
    ->get();

// BETWEEN operator
$priceRange = $client->table('products')
    ->select('*')
    ->between('price', [10, 100])
    ->get();

// NOT IN operator
$active = $client->table('products')
    ->select('*')
    ->notIn('status', ['deleted', 'archived'])
    ->get();

// NOT BETWEEN operator
$outliers = $client->table('products')
    ->select('*')
    ->notBetween('price', [10, 100])
    ->get();

// IS NULL / IS NOT NULL
$noEmail = $client->table('users')->select('*')->isNull('email')->get();
$hasEmail = $client->table('users')->select('*')->isNotNull('email')->get();

// OR conditions
$results = $client->table('products')
    ->select('*')
    ->filter('category', 'eq', 'electronics', 'AND')
    ->orWhere('price', 'gt', 1000)
    ->get();

// Get first record only
$firstUser = $client->table('users')
    ->select('*')
    ->eq('email', 'john@example.com')
    ->first();

// Get single record (throws if not exactly one)
$singleUser = $client->table('users')
    ->select('*')
    ->eq('id', 1)
    ->single();

// Count records
$totalUsers = $client->table('users')->count();
echo "Total users: {$totalUsers}" . PHP_EOL;

// Get record by ID
$user = $client->table('users')->getById(123);
echo $user['name'] . PHP_EOL;
```

### Insert Data

```php
// Insert a single record
$result = $client->table('users')->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);
echo "New user ID: " . $result['id'] . PHP_EOL;

// Using the insert alias
$result = $client->table('users')->insert([
    'name' => 'Alice',
    'email' => 'alice@example.com'
]);

// Bulk insert multiple records
$results = $client->table('users')->bulkInsert([
    ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 28],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 32],
    ['name' => 'Carol', 'email' => 'carol@example.com', 'age' => 24],
]);
echo "Inserted " . count($results) . " records" . PHP_EOL;
```

### Upsert Data

```php
// Insert or update on conflict (default conflict column: id)
$result = $client->table('users')->upsert([
    'id' => 1,
    'name' => 'John Updated',
    'email' => 'john@example.com'
]);

// Specify conflict column
$result = $client->table('users')->upsert(
    ['email' => 'john@example.com', 'name' => 'John Updated'],
    'email'
);
```

### Update Data

```php
// Update by ID
$result = $client->table('users')->update(1, [
    'name' => 'Jane Smith',
    'age' => 26
]);
echo "Updated " . $result['affected_rows'] . " row(s)" . PHP_EOL;
```

### Delete Data

```php
// Delete by ID
$result = $client->table('users')->delete(1);
echo "Deleted " . $result['affected_rows'] . " row(s)" . PHP_EOL;
```

## Advanced Query Features

### GROUP BY and Aggregates

GROUP BY supports both simple column names and SQL expressions with functions. All expressions are validated for security.

#### Basic GROUP BY

```php
// Group by single column
$result = $client->table('products')
    ->select('category', 'COUNT(*) as count', 'AVG(price) as avg_price')
    ->groupBy('category')
    ->get();

// Group by multiple columns
$result = $client->table('sales')
    ->select('region', 'category', 'SUM(amount) as total')
    ->groupBy('region', 'category')
    ->get();
```

#### GROUP BY with Date/Time Functions

```php
// Group by date
$result = $client->table('orders')
    ->select('DATE(created_at) as date', 'COUNT(*) as orders', 'SUM(total) as revenue')
    ->groupBy('DATE(created_at)')
    ->orderBy('date', 'desc')
    ->get();

// Group by year
$result = $client->table('orders')
    ->select('YEAR(created_at) as year', 'COUNT(*) as orders')
    ->groupBy('YEAR(created_at)')
    ->get();

// Group by year and month
$result = $client->table('orders')
    ->select(
        'YEAR(created_at) as year',
        'MONTH(created_at) as month',
        'SUM(total) as revenue'
    )
    ->groupBy('YEAR(created_at)', 'MONTH(created_at)')
    ->orderBy('year', 'desc')
    ->orderBy('month', 'desc')
    ->get();

// Group by week / quarter
$weekly = $client->table('orders')
    ->select('WEEK(created_at) as week', 'COUNT(*) as orders')
    ->groupBy('WEEK(created_at)')
    ->get();

$quarterly = $client->table('orders')
    ->select('QUARTER(created_at) as quarter', 'SUM(total) as revenue')
    ->groupBy('QUARTER(created_at)')
    ->get();
```

#### GROUP BY with String Functions

```php
// Group by first letter of name
$result = $client->table('users')
    ->select('LEFT(name, 1) as first_letter', 'COUNT(*) as count')
    ->groupBy('LEFT(name, 1)')
    ->get();

// Group by uppercase category
$result = $client->table('products')
    ->select('UPPER(category) as category_upper', 'COUNT(*) as count')
    ->groupBy('UPPER(category)')
    ->get();
```

#### GROUP BY with Mathematical Functions

```php
// Group by rounded price ranges
$result = $client->table('products')
    ->select('ROUND(price, -1) as price_range', 'COUNT(*) as count')
    ->groupBy('ROUND(price, -1)')
    ->get();

// Group by price tier
$result = $client->table('products')
    ->select('FLOOR(price / 10) * 10 as price_tier', 'COUNT(*) as count')
    ->groupBy('FLOOR(price / 10) * 10')
    ->get();
```

#### Supported Functions in GROUP BY

**Date/Time Functions:**
- `DATE()`, `YEAR()`, `MONTH()`, `DAY()`, `DAYOFMONTH()`, `DAYOFWEEK()`, `DAYOFYEAR()`
- `WEEK()`, `QUARTER()`, `HOUR()`, `MINUTE()`, `SECOND()`
- `DATE_FORMAT()`, `TIME()`, `DATE_ADD()`, `DATE_SUB()`
- `DATEDIFF()`, `TIMEDIFF()`, `TIMESTAMPDIFF()`
- `NOW()`, `CURRENT_TIMESTAMP()`, `CURDATE()`, `CURRENT_DATE()`
- `CURTIME()`, `CURRENT_TIME()`, `UNIX_TIMESTAMP()`

**String Functions:**
- `CONCAT()`, `CONCAT_WS()`, `SUBSTRING()`, `SUBSTR()`, `LEFT()`, `RIGHT()`
- `LENGTH()`, `CHAR_LENGTH()`, `UPPER()`, `LOWER()`, `TRIM()`, `LTRIM()`, `RTRIM()`
- `REPLACE()`, `LOCATE()`, `POSITION()`

**Mathematical Functions:**
- `ABS()`, `ROUND()`, `CEIL()`, `CEILING()`, `FLOOR()`, `POW()`, `POWER()`, `SQRT()`, `MOD()`, `RAND()`

> All expressions are validated for security. Only whitelisted functions are allowed.

### HAVING Clause

HAVING filters aggregated results after GROUP BY. It supports aggregate functions and comparison operators.

#### Basic HAVING

```php
// Filter aggregated results
$result = $client->table('products')
    ->select('category', 'COUNT(*) as count')
    ->groupBy('category')
    ->having('COUNT(*)', 'gt', 10)
    ->get();

// Multiple HAVING conditions (AND logic)
$result = $client->table('orders')
    ->select('DATE(created_at) as date', 'SUM(total) as revenue')
    ->groupBy('DATE(created_at)')
    ->having('SUM(total)', 'gt', 1000)
    ->having('COUNT(*)', 'gte', 5)
    ->get();
```

#### HAVING with Aggregate Functions

```php
// Filter by average
$result = $client->table('products')
    ->select('category', 'AVG(price) as avg_price', 'COUNT(*) as count')
    ->groupBy('category')
    ->having('AVG(price)', 'gt', 100)
    ->having('COUNT(*)', 'gte', 5)
    ->get();

// Filter by sum
$result = $client->table('orders')
    ->select('customer_id', 'SUM(total) as total_spent')
    ->groupBy('customer_id')
    ->having('SUM(total)', 'gt', 1000)
    ->get();

// Filter by max/min
$result = $client->table('products')
    ->select('category', 'MAX(price) as max_price', 'MIN(price) as min_price')
    ->groupBy('category')
    ->having('MAX(price)', 'gt', 500)
    ->get();
```

#### Supported HAVING Operators

- `eq` - Equal to
- `neq` - Not equal to
- `gt` - Greater than
- `gte` - Greater than or equal to
- `lt` - Less than
- `lte` - Less than or equal to

#### Supported Aggregate Functions in HAVING

- `COUNT(*)` or `COUNT(column)` - Count of rows
- `SUM(column)` - Sum of values
- `AVG(column)` - Average of values
- `MAX(column)` - Maximum value
- `MIN(column)` - Minimum value
- `GROUP_CONCAT(column)` - Concatenated values
- `STDDEV(column)`, `STDDEV_POP(column)`, `STDDEV_SAMP(column)` - Standard deviation
- `VARIANCE(column)`, `VAR_POP(column)`, `VAR_SAMP(column)` - Variance

### Multiple ORDER BY

```php
// Chain multiple orderBy calls
$result = $client->table('products')
    ->select('*')
    ->orderBy('category', 'asc')
    ->orderBy('price', 'desc')
    ->orderBy('created_at', 'desc')
    ->get();

// Using the order alias
$result = $client->table('products')
    ->select('*')
    ->order('category', 'asc')
    ->order('price', 'desc')
    ->get();
```

### Utility Methods

```php
// List all tables
$tables = $client->listTables();
print_r($tables); // ['users', 'posts', 'comments']

// Get table schema
$schema = $client->getTableSchema('users');
echo "Columns: " . count($schema['columns']) . PHP_EOL;
foreach ($schema['columns'] as $column) {
    echo "  - " . $column['name'] . " (" . $column['type'] . ")" . PHP_EOL;
}

// Close the client
$client->close();
```

## Filter Operators

Complete list of all available filter operators with examples:

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
->lt('price', 100)

// Less than or equal
->lte('price', 100)

// Pattern matching (SQL LIKE)
->like('email', '%@gmail.com')

// IS NULL
->isNull('deleted_at')

// IS NOT NULL
->isNotNull('email')

// IN operator (value must be an array)
->in('category', ['electronics', 'books', 'clothing'])

// NOT IN operator
->notIn('status', ['deleted', 'archived'])

// BETWEEN operator (value must be an array of 2 values)
->between('price', [10, 100])

// NOT BETWEEN operator
->notBetween('age', [18, 65])

// OR logical operator
->filter('category', 'eq', 'electronics', 'AND')
->orWhere('price', 'gt', 1000)

// Using the generic filter method
->filter('column', 'operator', $value)
->filter('column', 'operator', $value, 'OR')
```

## Authentication

The `ProjectAuthClient` provides a complete authentication system for your application.

### Initialization

```php
<?php
use WOWSQL\ProjectAuthClient;
use WOWSQL\MemoryTokenStorage;

$auth = new ProjectAuthClient(
    'https://your-project.wowsql.com',
    'your-anon-key'
);

// With custom token storage
$auth = new ProjectAuthClient(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-anon-key',
    tokenStorage: new MemoryTokenStorage()
);
```

#### Custom Token Storage

Implement the `TokenStorage` interface to persist tokens however you like (e.g., database, Redis, file):

```php
<?php
use WOWSQL\TokenStorage;

class RedisTokenStorage implements TokenStorage
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function getItem(string $key): ?string
    {
        $value = $this->redis->get($key);
        return $value === false ? null : $value;
    }

    public function setItem(string $key, string $value): void
    {
        $this->redis->set($key, $value);
    }

    public function removeItem(string $key): void
    {
        $this->redis->del($key);
    }
}

$auth = new ProjectAuthClient(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-anon-key',
    tokenStorage: new RedisTokenStorage($redis)
);
```

### Sign Up

```php
$response = $auth->signUp(
    'user@example.com',
    'SuperSecret123',
    'Demo User',
    ['referrer' => 'landing-page', 'plan' => 'free']
);

echo "User ID: " . $response->user->id . PHP_EOL;
echo "Email: " . $response->user->email . PHP_EOL;
echo "Access token: " . $response->session->accessToken . PHP_EOL;
```

### Sign In

```php
$result = $auth->signIn('user@example.com', 'SuperSecret123');

$auth->setSession(
    $result->session->accessToken,
    $result->session->refreshToken
);

echo "Logged in as: " . $result->user->email . PHP_EOL;
```

### Get Current User

```php
// Uses the stored session token automatically
$currentUser = $auth->getUser();
echo $currentUser->id . PHP_EOL;
echo $currentUser->email . PHP_EOL;
echo $currentUser->emailVerified . PHP_EOL;
echo $currentUser->fullName . PHP_EOL;

// Or pass a specific access token
$user = $auth->getUser('specific-access-token');
```

### OAuth Authentication

Complete OAuth flow with provider callback handling:

```php
// Step 1: Get the authorization URL
$oauthData = $auth->getOAuthAuthorizationUrl(
    'github',
    'https://app.your-domain.com/auth/callback'
);

// Redirect user to the authorization URL
header('Location: ' . $oauthData['authorization_url']);
exit;

// Step 2: Handle the callback (in your callback route)
$code = $_GET['code'];

$result = $auth->exchangeOAuthCallback(
    'github',
    $code,
    'https://app.your-domain.com/auth/callback'
);

echo "Logged in via GitHub: " . $result->user->email . PHP_EOL;
echo "Access token: " . $result->session->accessToken . PHP_EOL;

// Save the session
$auth->setSession(
    $result->session->accessToken,
    $result->session->refreshToken
);
```

### Password Reset

```php
// Step 1: Request a password reset email
$auth->forgotPassword('user@example.com');
// "If that email exists, a password reset link has been sent"

// Step 2: Reset the password (user clicks link, you extract the token)
$auth->resetPassword('reset-token-from-email', 'NewSecurePassword123');
// "Password reset successfully!"
```

### OTP (One-Time Password)

```php
// Send OTP to user's email
$auth->sendOtp('user@example.com', 'login');

// Verify OTP entered by user
$result = $auth->verifyOtp(
    'user@example.com',
    '123456',
    'login'
);
echo "Verified: " . ($result ? 'true' : 'false') . PHP_EOL;

// OTP for password reset
$auth->sendOtp('user@example.com', 'password_reset');
$auth->verifyOtp(
    'user@example.com',
    '654321',
    'password_reset',
    'NewPassword123'  // new password
);
```

### Magic Link

```php
// Send a magic link to user's email
$auth->sendMagicLink('user@example.com', 'login');

// The user clicks the link in their email, which redirects to your app
// with a token. No additional verification step needed on client side.
```

### Email Verification

```php
// Verify email with token (from verification email link)
$auth->verifyEmail('verification-token-from-email');

// Resend verification email
$auth->resendVerification('user@example.com');
```

### Change Password

```php
// Change password for authenticated user
$auth->changePassword('currentPassword123', 'newSecurePassword456');

// With explicit access token
$auth->changePassword(
    'currentPassword123',
    'newSecurePassword456',
    'specific-access-token'
);
```

### Update User Profile

```php
// Update user information
$updatedUser = $auth->updateUser(
    fullName: 'New Display Name',
    userMetadata: ['theme' => 'dark', 'language' => 'en']
);
echo "Updated name: " . $updatedUser->fullName . PHP_EOL;

// Update avatar and username
$updatedUser = $auth->updateUser(
    avatarUrl: 'https://example.com/avatar.jpg',
    username: 'newusername'
);

// With explicit access token
$user = $auth->updateUser(
    fullName: 'Admin User',
    accessToken: 'specific-access-token'
);
```

### Session Management

```php
// Get current session
$session = $auth->getSession();
if ($session) {
    echo "Access token: " . $session['access_token'] . PHP_EOL;
    echo "Refresh token: " . $session['refresh_token'] . PHP_EOL;
}

// Set session (e.g., restore from storage on page load)
$auth->setSession(
    'stored-access-token',
    'stored-refresh-token'
);

// Refresh an expired session
$newSession = $auth->refreshToken();
// Or with explicit refresh token
$newSession = $auth->refreshToken('stored-refresh-token');

// Logout
$auth->logout();
// Or with explicit access token
$auth->logout('specific-access-token');

// Clear local session data
$auth->clearSession();
```

### Auth Models

```php
// AuthResponse - returned by signUp, signIn, exchangeOAuthCallback
$response->user;            // AuthUser instance
$response->session;         // AuthSession instance

// AuthUser
$user->id;
$user->email;
$user->fullName;
$user->emailVerified;
$user->avatarUrl;
$user->username;
$user->userMetadata;        // array
$user->createdAt;

// AuthSession
$session->accessToken;
$session->refreshToken;
$session->expiresIn;
$session->tokenType;
```

## Storage Operations

The `WOWSQLStorage` client provides file storage capabilities backed by S3-compatible storage.

### Initialization

```php
<?php
use WOWSQL\WOWSQLStorage;

$storage = new WOWSQLStorage(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-api-key'
);

// With project slug
$storage = new WOWSQLStorage(
    projectSlug: 'my-project',
    apiKey: 'your-api-key'
);

// With custom timeout for large uploads
$storage = new WOWSQLStorage(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-api-key',
    timeout: 120
);
```

### Bucket Operations

```php
// Create a new bucket
$storage->createBucket('avatars', [
    'public' => true,
    'allowed_mime_types' => ['image/png', 'image/jpeg', 'image/webp'],
    'file_size_limit' => 5 * 1024 * 1024  // 5 MB
]);

// List all buckets
$buckets = $storage->listBuckets();
foreach ($buckets as $bucket) {
    echo $bucket->name . ': ' . ($bucket->public ? 'public' : 'private') . PHP_EOL;
}

// Get bucket details
$bucket = $storage->getBucket('avatars');
echo $bucket->name . ' created at ' . $bucket->createdAt . PHP_EOL;

// Update bucket settings
$storage->updateBucket('avatars', [
    'public' => false,
    'file_size_limit' => 10 * 1024 * 1024  // increase to 10 MB
]);

// Delete a bucket
$storage->deleteBucket('old-bucket');
```

### File Upload

```php
// Upload raw data
$fileData = file_get_contents('/path/to/report.pdf');
$result = $storage->upload('documents', $fileData, 'reports/q1.pdf');
echo "Uploaded: " . $result['path'] . PHP_EOL;

// Upload from a local file path
$storage->uploadFromPath('/home/user/report.pdf', 'documents', 'reports/q1.pdf');
```

### File Download

```php
// Download file to memory
$data = $storage->download('documents', 'reports/q1.pdf');
echo "Downloaded " . strlen($data) . " bytes" . PHP_EOL;

// Download to a local file path
$storage->downloadToFile('documents', 'reports/q1.pdf', '/home/user/downloads/q1.pdf');
```

### File Listing

```php
// List all files in a bucket
$files = $storage->listFiles('documents');
foreach ($files as $file) {
    echo $file->name . ': ' . $file->sizeMb . ' MB' . PHP_EOL;
}

// List with options (prefix, limit, offset)
$files = $storage->listFiles('documents', [
    'prefix' => 'reports/',
    'limit' => 50,
    'offset' => 0
]);
```

### File Deletion and URLs

```php
// Delete a file
$storage->deleteFile('documents', 'reports/old-report.pdf');

// Get a public URL for a file
$url = $storage->getPublicUrl('avatars', 'users/123/avatar.png');
echo $url . PHP_EOL;
```

### Storage Stats and Quota

```php
// Get storage statistics
$stats = $storage->getStats();
echo "Total files: " . $stats['total_files'] . PHP_EOL;
echo "Total size: " . $stats['total_size'] . PHP_EOL;

// Check storage quota
$quota = $storage->getQuota();
echo "Used: {$quota->usedGb} GB" . PHP_EOL;
echo "Total: {$quota->totalGb} GB" . PHP_EOL;
echo "Available: {$quota->availableGb} GB" . PHP_EOL;

// Force refresh quota (bypass cache)
$quota = $storage->getQuota(true);

// Check quota before uploading
$fileSize = filesize('/path/to/large-file.zip');
$fileSizeGb = $fileSize / (1024 * 1024 * 1024);

if ($quota->availableGb > $fileSizeGb) {
    $storage->uploadFromPath('/path/to/large-file.zip', 'uploads', 'large-file.zip');
} else {
    echo "Storage limit reached. Upgrade your plan." . PHP_EOL;
}
```

### Storage Models

```php
// StorageBucket
$bucket->id;
$bucket->name;
$bucket->public;
$bucket->createdAt;

// StorageFile
$file->name;
$file->size;            // bytes
$file->sizeMb;          // megabytes
$file->sizeGb;          // gigabytes
$file->mimeType;
$file->createdAt;

// StorageQuota
$quota->usedBytes;
$quota->totalBytes;
$quota->availableBytes;
$quota->usedGb;
$quota->totalGb;
$quota->availableGb;
$quota->usagePercentage;
```

### Storage Error Handling

```php
<?php
use WOWSQL\WOWSQLStorage;
use WOWSQL\StorageException;
use WOWSQL\StorageLimitExceededException;

try {
    $storage->uploadFromPath('/path/to/huge-file.zip', 'uploads', 'huge-file.zip');
} catch (StorageLimitExceededException $e) {
    echo "Storage limit exceeded: " . $e->getMessage() . PHP_EOL;
    echo "Please upgrade your plan or delete old files" . PHP_EOL;
} catch (StorageException $e) {
    echo "Storage error: " . $e->getMessage() . PHP_EOL;
}
```

## Schema Management

Programmatically manage your database schema with the `WOWSQLSchema` client.

> **IMPORTANT**: Schema operations require a **Service Role Key** (`wowsql_service_...`). Anonymous keys will return a 403 Forbidden error.

### Initialization

```php
<?php
use WOWSQL\WOWSQLSchema;

$schema = new WOWSQLSchema(
    'https://your-project.wowsql.com',
    'wowsql_service_your-service-key'
);
```

### Create Table

```php
$schema->createTable('products', [
    ['name' => 'id', 'type' => 'INT', 'auto_increment' => true],
    ['name' => 'name', 'type' => 'VARCHAR(255)', 'not_null' => true],
    ['name' => 'price', 'type' => 'DECIMAL(10,2)', 'not_null' => true],
    ['name' => 'category', 'type' => 'VARCHAR(100)'],
    ['name' => 'stock', 'type' => 'INT', 'default' => '0'],
    ['name' => 'created_at', 'type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
], 'id', [
    ['name' => 'idx_category', 'columns' => ['category']],
    ['name' => 'idx_price', 'columns' => ['price']],
]);
```

### Alter Table

```php
// Add columns
$schema->alterTable('products', [
    'addColumns' => [
        ['name' => 'description', 'type' => 'TEXT'],
        ['name' => 'stock_quantity', 'type' => 'INT', 'default' => '0'],
    ]
]);

// Modify columns
$schema->alterTable('products', [
    'modifyColumns' => [
        ['name' => 'price', 'type' => 'DECIMAL(12,2)'],
    ]
]);

// Drop columns
$schema->alterTable('products', [
    'dropColumns' => ['old_field']
]);

// Rename columns
$schema->alterTable('products', [
    'renameColumns' => [
        ['oldName' => 'name', 'newName' => 'product_name'],
    ]
]);
```

### Column Operations

```php
// Add a column
$schema->addColumn('products', 'description', 'TEXT', [
    'not_null' => false,
    'default' => "''"
]);

// Drop a column
$schema->dropColumn('products', 'old_field');

// Rename a column
$schema->renameColumn('products', 'name', 'product_name');

// Modify a column type or constraints
$schema->modifyColumn('products', 'price', 'DECIMAL(14,2)', [
    'not_null' => true
]);
```

### Index Operations

```php
// Create an index
$schema->createIndex('products', ['category', 'price'], [
    'name' => 'idx_category_price',
    'unique' => false
]);
```

### Drop Table

```php
// Drop a table
$schema->dropTable('old_table');

// Drop with CASCADE
$schema->dropTable('products', true);
```

### Execute Raw SQL

```php
$schema->executeSql("
    CREATE INDEX idx_product_name
    ON products(product_name);
");

$schema->executeSql("
    ALTER TABLE orders
    ADD CONSTRAINT fk_product
    FOREIGN KEY (product_id)
    REFERENCES products(id);
");
```

### Schema Introspection

```php
// List all tables
$tables = $schema->listTables();
print_r($tables);

// Get detailed table schema
$tableSchema = $schema->getTableSchema('users');
print_r($tableSchema['columns']);
echo "Primary key: " . $tableSchema['primary_key'] . PHP_EOL;
```

### Schema Error Handling

```php
<?php
use WOWSQL\WOWSQLSchema;
use WOWSQL\SchemaPermissionException;

try {
    $schema->createTable('test', [
        ['name' => 'id', 'type' => 'INT', 'auto_increment' => true],
    ], 'id');
} catch (SchemaPermissionException $e) {
    echo "Permission denied: " . $e->getMessage() . PHP_EOL;
    echo "Make sure you are using a SERVICE ROLE KEY!" . PHP_EOL;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
```

### Schema Security Best Practices

**DO:**
- Use service role keys **only in backend/server code** (Laravel, Symfony, CLI scripts)
- Store service keys in environment variables
- Use anonymous keys for client-facing data operations
- Test schema changes in development first

**DON'T:**
- Never expose service role keys in frontend/browser code
- Never commit service keys to version control
- Don't use anonymous keys for schema operations (will fail with 403)

## API Keys

WowSQL uses **unified authentication** - the same API keys work for database operations, authentication, storage, and schema management.

### Key Types

| Operation Type | Recommended Key | Alternative Key | Used By |
|---|---|---|---|
| **Database Operations** (CRUD) | Service Role Key (`wowsql_service_...`) | Anonymous Key (`wowsql_anon_...`) | `WOWSQLClient` |
| **Authentication** (sign-up, login, OAuth) | Anonymous Key (`wowsql_anon_...`) | Service Role Key (`wowsql_service_...`) | `ProjectAuthClient` |
| **Storage** (upload, download) | Service Role Key (`wowsql_service_...`) | Anonymous Key (`wowsql_anon_...`) | `WOWSQLStorage` |
| **Schema Management** (DDL) | Service Role Key (`wowsql_service_...`) | N/A | `WOWSQLSchema` |

### Where to Find Your Keys

All keys are in: **WowSQL Dashboard > Settings > API Keys** or **Authentication > PROJECT KEYS**

1. **Anonymous Key** (`wowsql_anon_...`)
   - Client-side auth operations (signup, login, OAuth)
   - Public/client-side database operations with limited permissions
   - **Safe to expose** in frontend code (browser, mobile apps)

2. **Service Role Key** (`wowsql_service_...`)
   - Server-side operations with full access (bypass RLS)
   - Schema management operations
   - **NEVER expose** in frontend code - server-side only!

### Environment Variables

```bash
# .env
WOWSQL_PROJECT_URL=https://your-project.wowsql.com
WOWSQL_ANON_KEY=wowsql_anon_your-anon-key
WOWSQL_SERVICE_ROLE_KEY=wowsql_service_your-service-key
```

```php
<?php
$dbClient = new WOWSQLClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);

$authClient = new ProjectAuthClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_ANON_KEY')
);

$storage = new WOWSQLStorage(
    projectUrl: getenv('WOWSQL_PROJECT_URL'),
    apiKey: getenv('WOWSQL_SERVICE_ROLE_KEY')
);

$schema = new WOWSQLSchema(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);
```

### Security Best Practices

1. **Never expose Service Role Key** in client-side code or public repositories
2. **Use Anonymous Key** for client-side authentication and limited database access
3. **Store keys in environment variables**, never hardcode them
4. **Rotate keys regularly** if compromised

### Troubleshooting

**Error: "Invalid API key for project"**
- Ensure you're using the correct key type for the operation
- Verify the key is copied correctly (no extra spaces)
- Check that the project URL matches your dashboard

**Error: "Authentication failed"**
- Check that you're using the correct key: Anonymous Key for client-side, Service Role Key for server-side
- Ensure the key hasn't been revoked or expired

## Error Handling

```php
<?php
use WOWSQL\WOWSQLClient;
use WOWSQL\WOWSQLStorage;
use WOWSQL\WOWSQLException;
use WOWSQL\StorageException;
use WOWSQL\StorageLimitExceededException;
use WOWSQL\PermissionException;
use WOWSQL\SchemaPermissionException;

// Database errors
try {
    $users = $client->table('users')->select('*')->get();
} catch (WOWSQLException $e) {
    echo "Database error [{$e->getStatusCode()}]: {$e->getMessage()}" . PHP_EOL;
}

// Storage errors
try {
    $storage->upload('bucket', $fileData, 'path/to/file');
} catch (StorageLimitExceededException $e) {
    echo "Storage full: " . $e->getMessage() . PHP_EOL;
} catch (StorageException $e) {
    echo "Storage error: " . $e->getMessage() . PHP_EOL;
}

// Schema errors
try {
    $schema->createTable('test', []);
} catch (SchemaPermissionException $e) {
    echo "Use a Service Role Key for schema operations" . PHP_EOL;
}

// Permission errors
try {
    $client->table('restricted_table')->get();
} catch (PermissionException $e) {
    echo "Permission denied: " . $e->getMessage() . PHP_EOL;
}

// Generic catch-all with status code handling
try {
    $client->table('users')->getById(999);
} catch (WOWSQLException $e) {
    switch ($e->getStatusCode()) {
        case 401:
            echo "Unauthorized - check your API key" . PHP_EOL;
            break;
        case 404:
            echo "Record not found" . PHP_EOL;
            break;
        case 429:
            echo "Rate limit exceeded - slow down" . PHP_EOL;
            break;
        default:
            echo "Error {$e->getStatusCode()}: {$e->getMessage()}" . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . PHP_EOL;
}
```

### Exception Hierarchy

```
Exception
└── WOWSQLException              # Base exception for all SDK errors
    ├── PermissionException      # 403 Forbidden / access denied
    ├── StorageException         # Storage operation failures
    │   └── StorageLimitExceededException  # Storage quota exceeded
    └── SchemaPermissionException  # Schema operations require service key
```

## Configuration

### Basic Configuration

```php
$client = new WOWSQLClient(
    'https://your-project.wowsql.com',
    'your-api-key'
);
```

### Advanced Configuration

```php
$client = new WOWSQLClient(
    projectUrl: 'your-project',      // subdomain or full URL
    apiKey: 'your-api-key',
    baseDomain: 'wowsql.com',        // custom domain (default: wowsql.com)
    secure: true,                      // use HTTPS (default: true)
    timeout: 30,                       // request timeout in seconds (default: 30)
    verifySsl: true                    // verify SSL certificates (default: true)
);
```

### Storage Timeout

```php
$storage = new WOWSQLStorage(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-api-key',
    timeout: 120  // 2 minutes for large file uploads
);
```

### Auth with Custom Storage

```php
use WOWSQL\TokenStorage;

class SessionTokenStorage implements TokenStorage
{
    public function getItem(string $key): ?string
    {
        return $_SESSION[$key] ?? null;
    }

    public function setItem(string $key, string $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function removeItem(string $key): void
    {
        unset($_SESSION[$key]);
    }
}

$auth = new ProjectAuthClient(
    projectUrl: 'https://your-project.wowsql.com',
    apiKey: 'your-anon-key',
    tokenStorage: new SessionTokenStorage()
);
```

### Disabling SSL Verification (development only)

```php
$client = new WOWSQLClient(
    projectUrl: 'https://localhost:8443',
    apiKey: 'dev-key',
    verifySsl: false
);
```

## Laravel Integration

### Configuration

```php
// config/services.php
return [
    'wowsql' => [
        'project_url' => env('WOWSQL_PROJECT_URL'),
        'api_key' => env('WOWSQL_API_KEY'),
        'anon_key' => env('WOWSQL_ANON_KEY'),
        'service_key' => env('WOWSQL_SERVICE_ROLE_KEY'),
    ],
];
```

### Service Provider

```php
// app/Providers/WowSQLServiceProvider.php
<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use WOWSQL\WOWSQLClient;
use WOWSQL\ProjectAuthClient;
use WOWSQL\WOWSQLStorage;
use WOWSQL\WOWSQLSchema;

class WowSQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WOWSQLClient::class, function ($app) {
            return new WOWSQLClient(
                config('services.wowsql.project_url'),
                config('services.wowsql.service_key')
            );
        });

        $this->app->singleton(ProjectAuthClient::class, function ($app) {
            return new ProjectAuthClient(
                config('services.wowsql.project_url'),
                config('services.wowsql.anon_key')
            );
        });

        $this->app->singleton(WOWSQLStorage::class, function ($app) {
            return new WOWSQLStorage(
                projectUrl: config('services.wowsql.project_url'),
                apiKey: config('services.wowsql.service_key')
            );
        });

        $this->app->singleton(WOWSQLSchema::class, function ($app) {
            return new WOWSQLSchema(
                config('services.wowsql.project_url'),
                config('services.wowsql.service_key')
            );
        });
    }
}
```

### Controller Usage

```php
// app/Http/Controllers/UserController.php
<?php
namespace App\Http\Controllers;

use WOWSQL\WOWSQLClient;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private WOWSQLClient $client
    ) {}

    public function index(Request $request)
    {
        $page = $request->query('page', 1);

        $result = $this->client->table('users')
            ->select('id', 'name', 'email', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($page, 20);

        return view('users.index', [
            'users' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        $result = $this->client->table('users')->create($validated);

        return redirect()->route('users.index')
            ->with('success', "User created with ID: {$result['id']}");
    }

    public function show(int $id)
    {
        $user = $this->client->table('users')->getById($id);
        return view('users.show', compact('user'));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email',
        ]);

        $this->client->table('users')->update($id, $validated);
        return redirect()->route('users.show', $id);
    }

    public function destroy(int $id)
    {
        $this->client->table('users')->delete($id);
        return redirect()->route('users.index');
    }
}
```

### Auth Controller (Laravel)

```php
// app/Http/Controllers/AuthController.php
<?php
namespace App\Http\Controllers;

use WOWSQL\ProjectAuthClient;
use WOWSQL\WOWSQLException;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private ProjectAuthClient $auth
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $result = $this->auth->signIn(
                $request->email,
                $request->password
            );

            session([
                'access_token' => $result->session->accessToken,
                'refresh_token' => $result->session->refreshToken,
                'user' => $result->user,
            ]);

            return redirect()->intended('/dashboard');
        } catch (WOWSQLException $e) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $result = $this->auth->signUp(
            $request->email,
            $request->password,
            $request->name
        );

        session([
            'access_token' => $result->session->accessToken,
            'user' => $result->user,
        ]);

        return redirect('/dashboard');
    }

    public function logout()
    {
        $this->auth->logout(session('access_token'));
        session()->flush();
        return redirect('/');
    }
}
```

### Middleware Example

```php
// app/Http/Middleware/WowSQLAuth.php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use WOWSQL\ProjectAuthClient;

class WowSQLAuth
{
    public function __construct(
        private ProjectAuthClient $auth
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $token = session('access_token');

        if (!$token) {
            return redirect('/login');
        }

        try {
            $user = $this->auth->getUser($token);
            $request->merge(['wowsql_user' => $user]);
        } catch (\Exception $e) {
            session()->flush();
            return redirect('/login');
        }

        return $next($request);
    }
}
```

## Examples

### Blog Application

```php
<?php
use WOWSQL\WOWSQLClient;

$client = new WOWSQLClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);

// Create a new post
$post = $client->table('posts')->create([
    'title' => 'Hello World',
    'content' => 'My first blog post',
    'author_id' => 1,
    'published' => true,
]);

// Get published posts with pagination
$posts = $client->table('posts')
    ->select('id', 'title', 'content', 'created_at')
    ->eq('published', true)
    ->orderBy('created_at', 'desc')
    ->paginate(1, 10);

// Get a single post
$singlePost = $client->table('posts')->getById(1);

// Get comments for a post
$comments = $client->table('comments')
    ->select('*')
    ->eq('post_id', 1)
    ->orderBy('created_at', 'asc')
    ->get();

// Get post statistics
$stats = $client->table('posts')
    ->select('DATE(created_at) as date', 'COUNT(*) as count')
    ->eq('published', true)
    ->groupBy('DATE(created_at)')
    ->orderBy('date', 'desc')
    ->limit(30)
    ->get();
```

### E-Commerce Dashboard

```php
<?php
use WOWSQL\WOWSQLClient;

$client = new WOWSQLClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);

// Top selling products
$topProducts = $client->table('order_items')
    ->select('product_id', 'SUM(quantity) as total_sold', 'SUM(price * quantity) as revenue')
    ->groupBy('product_id')
    ->having('SUM(quantity)', 'gt', 10)
    ->orderBy('total_sold', 'desc')
    ->limit(10)
    ->get();

// Monthly revenue report
$monthlyRevenue = $client->table('orders')
    ->select(
        'YEAR(created_at) as year',
        'MONTH(created_at) as month',
        'COUNT(*) as order_count',
        'SUM(total) as revenue'
    )
    ->eq('status', 'completed')
    ->groupBy('YEAR(created_at)', 'MONTH(created_at)')
    ->orderBy('year', 'desc')
    ->orderBy('month', 'desc')
    ->limit(12)
    ->get();

// Customers who spent more than $1000
$topCustomers = $client->table('orders')
    ->select('customer_id', 'COUNT(*) as orders', 'SUM(total) as total_spent')
    ->eq('status', 'completed')
    ->groupBy('customer_id')
    ->having('SUM(total)', 'gt', 1000)
    ->orderBy('total_spent', 'desc')
    ->get();

// Products by price tier
$priceTiers = $client->table('products')
    ->select('FLOOR(price / 50) * 50 as price_tier', 'COUNT(*) as count')
    ->groupBy('FLOOR(price / 50) * 50')
    ->orderBy('price_tier', 'asc')
    ->get();
```

### File Upload Application

```php
<?php
use WOWSQL\WOWSQLClient;
use WOWSQL\WOWSQLStorage;

$client = new WOWSQLClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);

$storage = new WOWSQLStorage(
    projectUrl: getenv('WOWSQL_PROJECT_URL'),
    apiKey: getenv('WOWSQL_SERVICE_ROLE_KEY')
);

// Create an avatars bucket
$storage->createBucket('avatars', ['public' => true]);

// Upload user avatar
$userId = 123;
$avatarPath = "users/{$userId}/avatar.jpg";
$storage->uploadFromPath('/tmp/uploaded-avatar.jpg', 'avatars', $avatarPath);

// Get the public URL and save to database
$avatarUrl = $storage->getPublicUrl('avatars', $avatarPath);
$client->table('users')->update($userId, [
    'avatar_url' => $avatarUrl,
]);

// List user's uploaded files
$files = $storage->listFiles('avatars', [
    'prefix' => "users/{$userId}/",
]);
echo "User has " . count($files) . " files" . PHP_EOL;

// Check storage quota before upload
$quota = $storage->getQuota();
if ($quota->availableGb > 0.1) {
    $storage->uploadFromPath('/tmp/banner.jpg', 'avatars', "users/{$userId}/banner.jpg");
} else {
    echo "Low storage! Consider upgrading." . PHP_EOL;
}
```

### Migration Script

```php
#!/usr/bin/env php
<?php
require 'vendor/autoload.php';

use WOWSQL\WOWSQLSchema;

$schema = new WOWSQLSchema(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);

// Create users table
$schema->createTable('users', [
    ['name' => 'id', 'type' => 'INT', 'auto_increment' => true],
    ['name' => 'email', 'type' => 'VARCHAR(255)', 'not_null' => true, 'unique' => true],
    ['name' => 'name', 'type' => 'VARCHAR(255)', 'not_null' => true],
    ['name' => 'avatar_url', 'type' => 'VARCHAR(512)'],
    ['name' => 'created_at', 'type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
], 'id', [
    ['name' => 'idx_email', 'columns' => ['email']],
]);

// Create posts table
$schema->createTable('posts', [
    ['name' => 'id', 'type' => 'INT', 'auto_increment' => true],
    ['name' => 'user_id', 'type' => 'INT', 'not_null' => true],
    ['name' => 'title', 'type' => 'VARCHAR(255)', 'not_null' => true],
    ['name' => 'content', 'type' => 'TEXT'],
    ['name' => 'published', 'type' => 'BOOLEAN', 'default' => 'false'],
    ['name' => 'created_at', 'type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
], 'id');

// Add foreign key
$schema->executeSql("
    ALTER TABLE posts
    ADD CONSTRAINT fk_user
    FOREIGN KEY (user_id)
    REFERENCES users(id);
");

echo "Migration completed!" . PHP_EOL;
```

### Full Auth Flow Example

```php
<?php
require 'vendor/autoload.php';

use WOWSQL\ProjectAuthClient;
use WOWSQL\WOWSQLClient;
use WOWSQL\WOWSQLException;

$auth = new ProjectAuthClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_ANON_KEY')
);

$db = new WOWSQLClient(
    getenv('WOWSQL_PROJECT_URL'),
    getenv('WOWSQL_SERVICE_ROLE_KEY')
);

// Register a new user
try {
    $signUpResult = $auth->signUp(
        'newuser@example.com',
        'SecurePassword123',
        'New User',
        ['plan' => 'free', 'source' => 'website']
    );

    echo "Registered: " . $signUpResult->user->email . PHP_EOL;

    // Store user profile in your database
    $db->table('profiles')->create([
        'auth_user_id' => $signUpResult->user->id,
        'display_name' => $signUpResult->user->fullName,
        'plan' => 'free',
    ]);

} catch (WOWSQLException $e) {
    echo "Registration failed: " . $e->getMessage() . PHP_EOL;
}

// Sign in
try {
    $signInResult = $auth->signIn('newuser@example.com', 'SecurePassword123');
    $auth->setSession(
        $signInResult->session->accessToken,
        $signInResult->session->refreshToken
    );

    // Get user profile
    $currentUser = $auth->getUser();
    echo "Welcome back, " . $currentUser->fullName . "!" . PHP_EOL;

    // Update profile
    $auth->updateUser(
        username: 'newuser',
        userMetadata: ['last_login' => date('Y-m-d H:i:s')]
    );

} catch (WOWSQLException $e) {
    echo "Login failed: " . $e->getMessage() . PHP_EOL;
}

// Refresh session when token expires
try {
    $auth->refreshToken();
} catch (WOWSQLException $e) {
    echo "Session expired. Please log in again." . PHP_EOL;
    $auth->clearSession();
}

// Logout
$auth->logout();
$auth->clearSession();
```

## Requirements

- PHP 8.0 or higher
- Composer
- ext-json
- ext-curl (or Guzzle HTTP Client)

## Links

- [Documentation](https://wowsql.com/docs)
- [Website](https://wowsql.com)
- [Dashboard](https://app.wowsql.com)
- [Discord](https://discord.gg/wowsql)
- [Issues](https://github.com/wowsql/wowsql-php/issues)

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## Support

- Email: support@wowsql.com
- Discord: https://discord.gg/wowsql
- Documentation: https://wowsql.com/docs

---

Made with care by the WowSQL Team
