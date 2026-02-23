# AGENTS.md - Developer Guide for Given That API

## Overview

This is a Laravel 12 application with PHP 8.2+, using Pest for testing, and Vite for frontend asset building. The project follows Laravel conventions and uses an API-first architecture.

---

## Commands

### Running Tests

```bash
# Run all tests
composer test

# Or using artisan
php artisan test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run a specific test by name using --filter
php artisan test --filter="test_name_here"

# Run tests with coverage
php artisan test --coverage

# Run only Feature tests
php artisan test --testsuite=Feature

# Run only Unit tests
php artisan test --testsuite=Unit

# Run tests in parallel (requires pest-plugin-parallel)
php artisan test --parallel
```

### Code Quality

```bash
# Run Laravel Pint (code formatting)
./vendor/bin/pint

# Check formatting without changes
./vendor/bin/pint --test

# Run Pint on specific files
./vendor/bin/pint app/Http/Controllers/Api/CourseController.php
```

### Build Commands

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Build frontend assets
npm run dev    # Development with hot reload
npm run build  # Production build

# Full setup (composer + npm + migrations)
composer setup
```

### Other Commands

```bash
# Clear config cache (required before running tests)
php artisan config:clear

# Clear all caches
php artisan optimize:clear

# Run migrations
php artisan migrate

# Run migrations with seeding
php artisan migrate --seed
```

---

## Code Style Guidelines

### General Conventions

- **Indentation**: 4 spaces (configured in `.editorconfig`)
- **Line endings**: LF (Unix)
- **Charset**: UTF-8
- **Final newline**: Yes
- **Trailing whitespace**: Trimmed

### PHP Conventions

1. **PSR-4 Autoloading**: Follow PSR-4 standards with namespaces matching directory structure
   - `App\Http\Controllers\Api\CourseController` -> `app/Http/Controllers/Api/CourseController.php`

2. **Class Naming**:
   - Controllers: `PascalCase` with `Controller` suffix (e.g., `CourseController`)
   - Models: `PascalCase`, singular (e.g., `Course`)
   - Traits: `PascalCase` with `Trait` suffix (optional) (e.g., `ApiResponse`)
   - Requests: `PascalCase` with `Request` suffix (e.g., `StoreCourseRequest`)
   - Resources: `PascalCase` with `Resource` suffix (e.g., `CourseResource`)

3. **Method/Property Naming**:
   - Use `camelCase` for methods and properties
   - Use `$this` for instance properties, `self::$` for static

4. **Imports**:
   - Use fully qualified class names or explicit `use` statements
   - Group imports: Laravel externals first, then custom, then models
   - Sort alphabetically within groups

5. **Types**:
   - Use strict types with return type declarations
   - Use PHP 8 union types where appropriate
   - Example: `public function store(Request $request): JsonResponse`

6. **DocBlocks**:
   - Use `@param` and `@return` for complex methods
   - Use `@var` for model property documentation
   - Keep docblocks concise and meaningful

### Laravel-Specific Conventions

1. **Controllers**:
   - Use Form Request classes for validation (don't validate in controller)
   - Return typed responses: `JsonResponse`
   - Use the `ApiResponse` trait for consistent API responses
   - Use API Resources for data transformation

2. **Models**:
   - Use `$fillable` for mass assignment (not `$guarded`)
   - Define relationships using method signatures: `public function courses(): HasMany`
   - Use scopes for common queries: `public function scopePublished($query)`
   - Use accessors/mutators for attribute transformation

3. **Request Classes**:
   - Create FormRequest classes in `app/Http/Requests/{Entity}/`
   - Implement `authorize()`, `rules()`, and optionally `messages()`
   - Use `Rule::in()` for enum-like validations

4. **Traits**:
   - Place in `app/Traits/`
   - Use meaningful names: `ApiResponse`, `HasFactory`

### Error Handling

- Use `findOrFail()` for model lookups that should 404
- Use try/catch for operations that may fail gracefully
- Return appropriate HTTP status codes (200, 201, 400, 401, 403, 404, 422)
- Use the `ApiResponse` trait's helper methods:
  - `successResponse()` / `errorResponse()` / `paginatedResponse()`
  - `createdResponse()` / `notFoundResponse()` / `forbiddenResponse()`

### Testing Conventions (Pest)

1. **Test Structure**:
   - Feature tests in `tests/Feature/`
   - Unit tests in `tests/Unit/`
   - Use Pest's `test()` function, not PHPUnit's `testMethod`

2. **Test Naming**:
   - Use descriptive names: `test('user can enroll in course')`
   - Use `expect()` for assertions

3. **Test Patterns**:
   ```php
   test('description of behavior', function () {
       // Arrange
       $user = User::factory()->create();
       
       // Act
       $response = $this->actingAs($user)->get('/api/courses');
       
       // Assert
       $response->assertStatus(200);
       $response->assertJsonStructure([...]);
   });
   ```

4. **Database**:
   - Use `$this->refreshDatabase()` in TestCase if needed
   - Use Pest's `pest()->refreshDatabase()` in Pest.php

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/    # API controllers
│   ├── Requests/           # Form request classes
│   └── Resources/          # API resources
├── Models/                  # Eloquent models
├── Services/                # Business logic layer
├── Traits/                  # Reusable traits
routes/
└── api.php                  # API routes
tests/
├── Feature/                 # Feature tests
└── Unit/                    # Unit tests
```

---

## Common Patterns

### Adding a New API Endpoint

1. Create Request class in `app/Http/Requests/{Entity}/`
2. Create Resource class in `app/Http/Resources/`
3. Add method to Controller
4. Add route in `routes/api.php`

### Adding a Model Relationship

```php
public function courses(): HasMany
{
    return $this->hasMany(Course::class);
}
```

### Adding a Model Scope

```php
public function scopePublished($query)
{
    return $query->where('status', 'published');
}
```

---

## IDE Helpers

- Laravel IDE Helper files are generated: `_ide_helper.php`, `_ide_helper_models.php`
- Re-generate with: `php artisan ide-helper:generate`
- Re-generate models: `php artisan ide-helper:models`

---

## Environment

- Copy `.env.example` to `.env` and configure
- Key settings: `APP_KEY`, database credentials, external service API keys
