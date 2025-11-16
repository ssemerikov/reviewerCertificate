# Reviewer Certificate Plugin - Test Suite

Comprehensive test suite for the Reviewer Certificate Plugin, ensuring correctness and full compatibility with OJS 3.3, 3.4, and 3.5.

## Overview

This test suite provides:

- **Unit Tests**: Test individual components in isolation
- **Integration Tests**: Test complete workflows and interactions
- **Compatibility Tests**: Verify compatibility with OJS 3.3, 3.4, and 3.5
- **Security Tests**: Validate security features and input validation

## Requirements

- **PHP**: 7.3 or higher
- **PHPUnit**: 9.5 or higher
- **PHP Extensions**: mbstring, gd, mysqli, zip

## Installation

### Install PHPUnit via Composer

```bash
composer install
```

Or manually install PHPUnit:

```bash
composer require --dev phpunit/phpunit:^9.5
```

## Running Tests

### Run All Tests

```bash
./run-tests.sh
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only
vendor/bin/phpunit --testsuite "Unit Tests"

# Integration tests only
vendor/bin/phpunit --testsuite "Integration Tests"

# Compatibility tests only
vendor/bin/phpunit --testsuite "Compatibility Tests"

# Security tests only
vendor/bin/phpunit --testsuite "Security Tests"
```

### Run Tests for Specific OJS Version

```bash
# Test with OJS 3.3
OJS_VERSION=3.3 vendor/bin/phpunit

# Test with OJS 3.4
OJS_VERSION=3.4 vendor/bin/phpunit

# Test with OJS 3.5
OJS_VERSION=3.5 vendor/bin/phpunit
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/CertificateTest.php
```

### Run with Code Coverage

```bash
vendor/bin/phpunit --coverage-html tests/coverage/html
```

Then open `tests/coverage/html/index.html` in your browser.

## Test Structure

```
tests/
├── bootstrap.php                 # Test initialization
├── TestCase.php                  # Base test class with helpers
├── Unit/                         # Unit tests
│   ├── CertificateTest.php       # Certificate model tests
│   ├── CertificateDAOTest.php    # DAO tests
│   └── CertificateGeneratorTest.php # PDF generation tests
├── Integration/                  # Integration tests
│   └── CertificateWorkflowTest.php # End-to-end workflow tests
├── Compatibility/                # OJS version compatibility
│   ├── OJS33CompatibilityTest.php # OJS 3.3 tests
│   ├── OJS34CompatibilityTest.php # OJS 3.4 tests
│   └── OJS35CompatibilityTest.php # OJS 3.5 tests
├── Security/                     # Security tests
│   └── SecurityTest.php          # Security & validation tests
├── mocks/                        # Mock objects
│   ├── OJSMockLoader.php         # OJS core mocks
│   └── DatabaseMock.php          # Database mock
└── fixtures/                     # Test data
    ├── sample-certificate-data.json
    └── sample-background.txt
```

## Test Categories

### Unit Tests

Test individual components in isolation:

- **CertificateTest**: Certificate model getters/setters
- **CertificateDAOTest**: Database operations (CRUD)
- **CertificateGeneratorTest**: PDF generation logic, template variables

### Integration Tests

Test complete workflows:

- Certificate creation workflow
- Batch certificate generation
- Download tracking
- Certificate verification
- Statistics calculation

### Compatibility Tests

Ensure compatibility with different OJS versions:

- **OJS 3.3**: Traditional DAO pattern
- **OJS 3.4**: Repo facade pattern
- **OJS 3.5**: Latest features and PHP 8+ support

### Security Tests

Validate security features:

- Access control
- File upload validation
- SQL injection prevention
- XSS protection
- Certificate code uniqueness
- Context isolation

## Writing Tests

### Basic Test Example

```php
<?php
require_once dirname(__FILE__) . '/../bootstrap.php';

class MyTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

### Using Test Helpers

```php
public function testCertificateCreation(): void
{
    // Create mock objects
    $user = $this->createMockUser(1, 'John', 'Doe');
    $context = $this->createMockContext(1, 'Test Journal');
    $submission = $this->createMockSubmission(100, 'Test Manuscript');

    // Test your code
    $this->assertEquals('John Doe', $user->getFullName());
}
```

### Testing PDF Generation

```php
public function testPDFGeneration(): void
{
    $pdfContent = '...'; // Generated PDF content
    $this->assertValidPDF($pdfContent);
}
```

### Testing Certificate Codes

```php
public function testCertificateCode(): void
{
    $code = 'ABC123XYZ789';
    $this->assertValidCertificateCode($code);
}
```

## Continuous Integration

Tests run automatically on GitHub Actions for:

- Multiple PHP versions: 7.3, 7.4, 8.0, 8.1, 8.2
- Multiple OJS versions: 3.3, 3.4, 3.5
- Code coverage reporting

See `.github/workflows/test.yml` for CI configuration.

## Code Coverage

Generate coverage reports:

```bash
# HTML report
vendor/bin/phpunit --coverage-html tests/coverage/html

# Text report
vendor/bin/phpunit --coverage-text

# Clover XML (for CI)
vendor/bin/phpunit --coverage-clover tests/coverage/clover.xml
```

## Environment Variables

- `OJS_VERSION`: OJS version to test against (3.3, 3.4, or 3.5)
- `TEST_MODE`: Set to "true" for test mode
- `DB_DRIVER`: Database driver (default: sqlite)
- `DB_HOST`: Database host (default: :memory:)

## Troubleshooting

### PHPUnit Not Found

```bash
composer install
```

### Permission Errors

```bash
chmod -R 755 tests/
mkdir -p tests/coverage tests/logs tests/tmp
```

### Memory Limit Issues

```bash
php -d memory_limit=512M vendor/bin/phpunit
```

### Skip Specific Tests

```php
public function testSomething(): void
{
    $this->markTestSkipped('Reason for skipping');
}
```

## Best Practices

1. **Isolate Tests**: Each test should be independent
2. **Use Mocks**: Mock external dependencies
3. **Clear Names**: Use descriptive test method names
4. **One Assertion Focus**: Each test should test one thing
5. **Clean Up**: Use setUp() and tearDown() methods
6. **Test Edge Cases**: Include boundary conditions

## Contributing

When adding new features:

1. Write tests first (TDD approach)
2. Ensure all tests pass
3. Maintain code coverage above 80%
4. Update this README if needed

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [OJS Developer Documentation](https://docs.pkp.sfu.ca/dev/)
- [Plugin README](../README.md)

## Support

For issues with tests:

1. Check test output for details
2. Review test logs in `tests/logs/`
3. Enable verbose output: `vendor/bin/phpunit --verbose`
4. Check CI logs on GitHub Actions

## License

Same as the main plugin - GNU General Public License v3.0
