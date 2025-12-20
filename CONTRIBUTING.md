# Contributing to Laravel Bruno Generator

Thank you for considering contributing to Laravel Bruno Generator! This document outlines the process and guidelines.

## Development Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set up your IDE with Laravel Idea or similar

## Development Workflow

### Code Style

We follow PSR-12 and Laravel conventions. Format your code before committing:

```bash
composer format
```

### Static Analysis

Run PHPStan before submitting:

```bash
composer analyse
```

The project uses PHPStan level 8 for maximum type safety.

### Testing

Write tests for all new features:

```bash
composer test
```

Get coverage report:

```bash
composer test-coverage
```

### Requirements

- All code must have `declare(strict_types=1);`
- All properties and methods must have full type declarations
- No mixed or array types without documentation
- Use readonly properties where applicable
- Use enums for constants
- Use DTOs for data transfer
- Follow the existing architecture patterns

## Submitting Changes

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Make your changes
4. Run tests and static analysis
5. Commit with clear messages
6. Push to your fork
7. Submit a pull request

### Commit Messages

Follow conventional commits:

- `feat: add new feature`
- `fix: resolve bug in X`
- `docs: update README`
- `test: add tests for Y`
- `refactor: improve Z performance`

## Pull Request Process

1. Ensure all tests pass
2. Update README if adding features
3. Update CHANGELOG.md
4. Ensure PHPStan level 8 passes
5. Request review from maintainers

## Reporting Issues

- Use the issue tracker
- Provide clear reproduction steps
- Include Laravel and PHP versions
- Include relevant code snippets

## Questions?

Open a GitHub Discussion or reach out to maintainers.

Thank you for contributing!
