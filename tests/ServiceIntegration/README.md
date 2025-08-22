# Service Integration Tests

This directory contains tests that require:
- Laravel service container bindings
- Database connections
- Factory methods
- Service provider registration

These tests are separated from unit tests because they require a full Laravel application context to run properly.

## Running These Tests

These tests should be run in a full Laravel application context where:
1. The package is properly installed
2. Database migrations are run
3. Service providers are registered
4. Factories are available

## Why Separated?

- Unit tests should run in isolation without external dependencies
- Service integration tests need the full Laravel framework
- This separation allows the package to have passing unit tests while acknowledging integration requirements