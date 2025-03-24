<?php

declare(strict_types=1);

use Kevinsillo\PhpJsonDB\PhpJsonDB;
use ReflectionClass;
use ReflectionMethod;

class DocumentationGenerator
{
    private $phpJsonDB;
    private $tests;

    public function __construct(string $classPath)
    {
        // Usar require_once con la ruta completa al archivo
        require_once $classPath . '/src/PhpJsonDB.php';

        // Usar el nombre completo de la clase o la ruta de clase
        $this->phpJsonDB = new ReflectionClass('Kevinsillo\PhpJsonDB\PhpJsonDB');
    }

    public function generateReadme(): string
    {
        $readme = "# 📦 PhpJsonDB - JSON Database Management Library 🚀\n\n";

        // Extract description and metadata
        $readme .= $this->extractClassDescription();

        // Features
        $readme .= "## 🌟 Key Features\n\n";
        $features = [
            "💡 Lightweight JSON-based database management",
            "📁 File-system based table storage",
            "🔒 Type-safe CRUD operations",
            "🔍 Advanced querying capabilities",
            "📤 Export and import functionality"
        ];
        $readme .= $this->formatBulletList($features);

        // Installation
        $readme .= "\n## 🛠️ Installation\n\n";
        $readme .= "```bash\n# Instala fácilmente con Composer 🎉\ncomposer require kevinsillo/phpjsondb\n```\n";

        // Public Methods Overview
        $readme .= "\n## 🧰 Methods Overview\n\n";
        $readme .= $this->generateMethodsTable();

        // Usage Example
        $readme .= "\n## 💡 Quick Example\n\n";
        $readme .= "```php\n";
        $readme .= "\$db = new PhpJsonDB();\n";
        $readme .= "\$db->table('users')->insert([\n";
        $readme .= "    'name' => 'John Doe',\n";
        $readme .= "    'email' => 'john@example.com'\n";
        $readme .= "]);\n";
        $readme .= "```\n";

        // Contribution and License
        $readme .= "\n## 🤝 Contributing\n\nContributions are welcome! 🌈 Submit pull requests or open issues.\n";
        $readme .= "\n## 📄 License\n\nMIT License \n";

        return $readme;
    }

    private function extractClassDescription(): string
    {
        $description = "## 🌐 Overview\n\n";
        $description .= "PhpJsonDB is a modern PHP library for managing databases using JSON files. It provides a simple, file-system based approach to database management with type-safe operations. Perfect for small to medium projects! 🚀\n\n";

        return $description;
    }

    private function formatBulletList(array $items): string
    {
        return implode("\n", array_map(fn($item) => "- {$item}", $items)) . "\n";
    }

    private function generateMethodsTable(): string
    {
        $methods = $this->phpJsonDB->getMethods(ReflectionMethod::IS_PUBLIC);

        $table = "| 🔧 Method | 📝 Description |\n|--------|-------------|\n";

        $excludedMethods = ['__construct', 'getAllowedOperators'];

        foreach ($methods as $method) {
            if (in_array($method->getName(), $excludedMethods)) continue;

            $docComment = $method->getDocComment();
            $description = $this->extractMethodDescription($docComment);

            $table .= "| `{$method->getName()}()` | {$description} |\n";
        }

        return $table;
    }

    private function extractMethodDescription($docComment): string
    {
        if (empty($docComment)) return 'No description available 📭';

        $docComment = preg_replace('/^\s*\/\*\*\s*|\s*\*\/\s*$/', '', $docComment);

        $lines = preg_split('/\n/', $docComment);
        $descriptionLines = [];

        foreach ($lines as $line) {
            $line = preg_replace('/^\s*\*\s*/', '', $line);

            if (preg_match('/^@/', $line)) break;

            if (!empty(trim($line))) {
                $descriptionLines[] = trim($line);
            }
        }

        $description = !empty($descriptionLines) ? $descriptionLines[0] : 'No description available 📭';

        $description = ucfirst(rtrim($description, '.')) . '.';

        return $description;
    }

    public function writeReadme(string $outputPath): bool
    {
        $readme = $this->generateReadme();
        return file_put_contents($outputPath, $readme) !== false;
    }

    public static function generateDocumentation(string $classPath, string $outputPath = 'README.md')
    {
        $generator = new self($classPath);

        if ($generator->writeReadme($outputPath)) {
            echo "📝 README.md generated successfully! 🎉\n";
        } else {
            echo "❌ Failed to generate README.md\n";
            exit(1);
        }
    }
}

if (php_sapi_name() === 'cli') {
    // Ajustar la ruta a src
    $classPath = __DIR__;
    $outputPath = $classPath . '/README.md';

    DocumentationGenerator::generateDocumentation($classPath, $outputPath);
}
