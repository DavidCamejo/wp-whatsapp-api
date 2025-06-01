# Contributing to WhatsApp API WordPress Plugin

Thank you for your interest in contributing to this project! Here are the guidelines for contributing to the WhatsApp API WordPress Integration plugin.

## Development Environment Setup

1. Set up a local WordPress development environment
2. Clone this repository to your `wp-content/plugins/` directory
3. Install development dependencies:
   ```
   cd wp-whatsapp-integration
   composer install
   ```

## Code Standards

This project follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/). Before submitting a pull request, please ensure your code adheres to these standards.

You can check your code using PHPCS:

```bash
phpcs --standard=WordPress --extensions=php .
```

## Git Workflow

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Make your changes
4. Commit changes with descriptive commit messages:
   ```
   git commit -m "feature: add new functionality X"
   git commit -m "fix: resolve issue with Y"
   ```
5. Push your branch: `git push origin feature/your-feature-name`
6. Create a Pull Request against the main branch

## Versioning

We follow [Semantic Versioning](https://semver.org/) for this plugin:

- MAJOR version for incompatible API changes (X.0.0)
- MINOR version for functionality added in a backward compatible manner (0.X.0)
- PATCH version for backward compatible bug fixes (0.0.X)

## Pull Request Process

1. Update the CHANGELOG.md with details of changes
2. Update README.md with any new requirements or features
3. Update version number in the main plugin file according to semantic versioning
4. The PR will be merged once it passes reviews and checks

## Creating a Release

Only repository maintainers can create official releases:

1. Update the version constant in `wp-whatsapp-api.php`
2. Add a new entry to CHANGELOG.md
3. Commit these changes
4. Create a new tag: `git tag -a vX.Y.Z -m "Version X.Y.Z"`
5. Push the tag: `git push origin vX.Y.Z`
6. The GitHub Actions workflow will automatically create a release

## Additional Resources

- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Developer Documentation](https://docs.woocommerce.com/document/create-a-plugin/)

## Questions?

If you have any questions about contributing, please open an issue in the GitHub repository.
