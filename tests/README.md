# Reviewer Certificate Plugin - Test Suite

**Version**: 1.0.3
**OJS Compatibility**: 3.3.x, 3.4.x, 3.5.x
**Last Updated**: November 22, 2025

---

## Overview

This directory contains comprehensive tests for the Reviewer Certificate Plugin, with specific focus on OJS 3.5 compatibility. The test suite ensures that all class loading, namespace resolution, database operations, and functionality work correctly across all supported OJS versions.

## Test Structure

```
tests/
├── README.md                           # This file
├── unit/                              # Unit tests for individual classes
├── integration/                       # Integration tests
├── compatibility/                     # OJS 3.5 specific tests
├── fixtures/                          # Test data
├── manual/                            # Manual testing checklists
└── scripts/                           # Test automation scripts
```

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
