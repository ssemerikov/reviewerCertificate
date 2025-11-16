#!/bin/bash

################################################################################
# Reviewer Certificate Plugin - Test Runner Script
#
# This script provides a convenient way to run the test suite with various
# options and configurations.
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Default values
OJS_VERSION="${OJS_VERSION:-3.4}"
TESTSUITE=""
COVERAGE=false
VERBOSE=false
FILTER=""

################################################################################
# Functions
################################################################################

print_header() {
    echo -e "${BLUE}"
    echo "========================================"
    echo "  Reviewer Certificate Plugin Tests"
    echo "========================================"
    echo -e "${NC}"
}

print_usage() {
    cat << EOF
Usage: $0 [OPTIONS]

Options:
    -v, --version VERSION    OJS version to test (3.3, 3.4, or 3.5) [default: 3.4]
    -s, --suite SUITE        Test suite to run (unit, integration, compatibility, security)
    -c, --coverage           Generate code coverage report
    -f, --filter PATTERN     Filter tests by pattern
    -V, --verbose            Verbose output
    -h, --help               Show this help message

Examples:
    $0                                  # Run all tests
    $0 -v 3.3                          # Test with OJS 3.3
    $0 -s unit                         # Run only unit tests
    $0 -c                              # Run with coverage
    $0 -v 3.4 -s integration -V        # OJS 3.4 integration tests (verbose)
    $0 -f Certificate                  # Run tests matching 'Certificate'

Test Suites:
    unit            - Unit tests
    integration     - Integration tests
    compatibility   - Compatibility tests
    security        - Security tests

EOF
}

check_requirements() {
    echo -e "${YELLOW}Checking requirements...${NC}"

    # Check PHP
    if ! command -v php &> /dev/null; then
        echo -e "${RED}Error: PHP is not installed${NC}"
        exit 1
    fi

    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    echo "  ✓ PHP version: $PHP_VERSION"

    # Check Composer
    if [ ! -f "composer.json" ] && [ ! -f "vendor/bin/phpunit" ]; then
        echo -e "${YELLOW}  ! Composer dependencies not found${NC}"
        echo "  Installing PHPUnit..."

        if command -v composer &> /dev/null; then
            composer require --dev phpunit/phpunit:^9.5 --no-interaction
        else
            echo -e "${RED}Error: Composer is not installed${NC}"
            echo "Please install Composer or PHPUnit manually"
            exit 1
        fi
    fi

    # Check PHPUnit
    if [ ! -f "vendor/bin/phpunit" ]; then
        echo -e "${RED}Error: PHPUnit not found${NC}"
        echo "Run: composer install"
        exit 1
    fi

    PHPUNIT_VERSION=$(vendor/bin/phpunit --version | grep -oP 'PHPUnit \K[0-9.]+')
    echo "  ✓ PHPUnit version: $PHPUNIT_VERSION"

    echo ""
}

create_directories() {
    echo -e "${YELLOW}Creating test directories...${NC}"
    mkdir -p tests/coverage tests/logs tests/tmp
    chmod -R 755 tests/
    echo "  ✓ Directories created"
    echo ""
}

run_tests() {
    echo -e "${GREEN}Running tests...${NC}"
    echo "  OJS Version: $OJS_VERSION"

    if [ -n "$TESTSUITE" ]; then
        echo "  Test Suite: $TESTSUITE"
    else
        echo "  Test Suite: All"
    fi

    echo ""

    # Build PHPUnit command
    PHPUNIT_CMD="vendor/bin/phpunit"

    # Add test suite filter
    if [ -n "$TESTSUITE" ]; then
        case "$TESTSUITE" in
            unit)
                PHPUNIT_CMD="$PHPUNIT_CMD --testsuite \"Unit Tests\""
                ;;
            integration)
                PHPUNIT_CMD="$PHPUNIT_CMD --testsuite \"Integration Tests\""
                ;;
            compatibility)
                PHPUNIT_CMD="$PHPUNIT_CMD --testsuite \"Compatibility Tests\""
                ;;
            security)
                PHPUNIT_CMD="$PHPUNIT_CMD --testsuite \"Security Tests\""
                ;;
            *)
                echo -e "${RED}Error: Invalid test suite '$TESTSUITE'${NC}"
                echo "Valid options: unit, integration, compatibility, security"
                exit 1
                ;;
        esac
    fi

    # Add coverage
    if [ "$COVERAGE" = true ]; then
        PHPUNIT_CMD="$PHPUNIT_CMD --coverage-html tests/coverage/html --coverage-text --coverage-clover tests/coverage/clover.xml"
    fi

    # Add filter
    if [ -n "$FILTER" ]; then
        PHPUNIT_CMD="$PHPUNIT_CMD --filter \"$FILTER\""
    fi

    # Add verbose
    if [ "$VERBOSE" = true ]; then
        PHPUNIT_CMD="$PHPUNIT_CMD --verbose"
    fi

    # Always add testdox for better output
    PHPUNIT_CMD="$PHPUNIT_CMD --testdox"

    # Export OJS version
    export OJS_VERSION="$OJS_VERSION"
    export TEST_MODE="true"

    # Run tests
    echo "Executing: $PHPUNIT_CMD"
    echo ""

    eval $PHPUNIT_CMD
    TEST_EXIT_CODE=$?

    echo ""

    if [ $TEST_EXIT_CODE -eq 0 ]; then
        echo -e "${GREEN}✓ All tests passed!${NC}"
    else
        echo -e "${RED}✗ Some tests failed${NC}"
    fi

    return $TEST_EXIT_CODE
}

show_coverage() {
    if [ "$COVERAGE" = true ]; then
        echo ""
        echo -e "${BLUE}Code coverage report generated:${NC}"
        echo "  HTML: tests/coverage/html/index.html"
        echo "  XML:  tests/coverage/clover.xml"
        echo ""

        if command -v xdg-open &> /dev/null; then
            read -p "Open coverage report in browser? (y/n) " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                xdg-open tests/coverage/html/index.html
            fi
        fi
    fi
}

################################################################################
# Main
################################################################################

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -v|--version)
            OJS_VERSION="$2"
            shift 2
            ;;
        -s|--suite)
            TESTSUITE="$2"
            shift 2
            ;;
        -c|--coverage)
            COVERAGE=true
            shift
            ;;
        -f|--filter)
            FILTER="$2"
            shift 2
            ;;
        -V|--verbose)
            VERBOSE=true
            shift
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        *)
            echo -e "${RED}Error: Unknown option $1${NC}"
            print_usage
            exit 1
            ;;
    esac
done

# Validate OJS version
if [[ ! "$OJS_VERSION" =~ ^3\.[3-5]$ ]]; then
    echo -e "${RED}Error: Invalid OJS version '$OJS_VERSION'${NC}"
    echo "Valid versions: 3.3, 3.4, 3.5"
    exit 1
fi

# Run
print_header
check_requirements
create_directories
run_tests
EXIT_CODE=$?
show_coverage

exit $EXIT_CODE
