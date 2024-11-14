# PHP Commit Generator

A PHP library that generates meaningful and standardized Git commit messages using AI powered by Groq API. This tool analyzes your git diff and generates commit messages that follow best practices and conventional commit standards.

## Features

- AI-powered commit message generation
- Follows conventional commit message standards
- Analyzes git diff to understand code changes
- Generates both commit titles and detailed descriptions
- Easy integration with existing PHP projects

## Requirements

- PHP 8.0 or higher
- Composer
- Groq API key

## Installation

You can install the package via composer:

```bash
composer require ay4t/php-commit-generator
```

## Usage

### Basic Usage

```php
use Ay4t\PCGG\Commit;

// Initialize with your Groq API key
$commit = new Commit('your-groq-api-key');

// Provide git diff
$diff = shell_exec('git diff --staged');
$commit->gitDiff($diff);

// Generate commit message
$message = $commit->generate();
echo $message;
```

### Using the CLI Script

The package includes a CLI script for easy usage:

1. Set your Groq API key:
```bash
export GROQ_API_KEY=your-groq-api-key
```

2. Run the generator:
```bash
php generate.php
```

The script will automatically:
- Get staged changes using `git diff --staged`
- Generate an appropriate commit message
- Output the message ready for use

## Generated Message Format

The generated commit messages follow this format:

```
<type>(<scope>): <short summary>

<detailed description>

- Change 1 details
- Change 2 details
```

Where:
- `type`: The type of change (feat, fix, docs, style, refactor, test, chore)
- `scope`: The scope of changes (optional)
- `summary`: A brief description of changes
- `detailed description`: A more comprehensive explanation of the changes

## Configuration

The commit message generator is configured to follow best practices:
- Commit titles are limited to 50 characters
- Descriptions are wrapped at 72 characters
- Follows conventional commit format
- Includes relevant context and reasoning

## Error Handling

The library includes comprehensive error handling:

```php
try {
    $commit = new Commit($apiKey);
    $commit->gitDiff($diff);
    $message = $commit->generate();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Testing

Run the test suite:

```bash
composer test
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

- Author: Ayatulloh Ahad R
- Powered by [Groq API](https://console.groq.com)

## Support

If you encounter any problems or have suggestions, please [open an issue](https://github.com/ay4t/php-commit-generator/issues).
