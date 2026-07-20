# Reviewer Certificate Plugin - Test Suite

**Version**: 1.8.1
**OJS Compatibility**: 3.3.x, 3.4.x, 3.5.x
**Last Updated**: July 6, 2026
**Coverage**: 177 PHP tests + 96 Playwright E2E tests

---

## Overview

This directory contains comprehensive tests for the Reviewer Certificate Plugin, with specific focus on OJS 3.5 compatibility. The test suite ensures that all class loading, namespace resolution, database operations, and functionality work correctly across all supported OJS versions.

## Test Structure

```
tests/
├── README.md         # This file
├── Unit/             # Unit tests for individual classes
├── Integration/      # Workflow / integration tests
├── Compatibility/    # OJS 3.3/3.4/3.5 compatibility tests
├── Security/         # Authorization and input-validation tests
├── Locale/           # Translation validation (key parity, short-dir sync)
├── e2e/              # Playwright E2E tests (Docker OJS 3.3/3.4/3.5)
├── mocks/            # Mocked OJS infrastructure (OJSMockLoader, DatabaseMock)
├── fixtures/         # Test data
├── manual/           # Manual testing checklists
└── scripts/          # Test automation scripts
```

The PHPUnit suites (`Unit`, `Integration`, `Compatibility`, `Security`) are defined in `phpunit.xml`; `Locale` runs on demand. A lowercase `tests/compatibility/` also exists (`ClassLoadingTest.php`) but is not part of any PHPUnit suite. See the root `CLAUDE.md` for the full testing guide.

## Running Tests

See individual test directories for specific instructions.

## OJS 3.5 Compatibility

All tests verify compatibility with OJS 3.5, particularly:
- Fully qualified class names
- Namespace resolution
- DAO compatibility
- Hook system integration

---

For complete documentation, see the test files in each subdirectory.
