<?php

$file = __DIR__ . '/vendor/doctrine/migrations/src/Metadata/Storage/TableMetadataStorage.php';

if (!file_exists($file)) {
    echo "ERROR: File not found: $file\n";
    exit(1);
}

$content = file_get_contents($file);

// Check if already patched
if (strpos($content, '// patched by patch-doctrine.php') !== false) {
    echo "Already patched, skipping.\n";
    exit(0);
}

$old = 'private function needsUpdate(Table $expectedTable): TableDiff|null
    {
        if ($this->schemaUpToDate) {
            return null;
        }

        $currentTable = $this->schemaManager->introspectTable($this->configuration->getTableName());
        $diff         = $this->schemaManager->createComparator()->compareTables($currentTable, $expectedTable);

        return $diff->isEmpty() ? null : $diff;
    }';

$new = 'private function needsUpdate(Table $expectedTable): TableDiff|null
    {
        return null; // patched by patch-doctrine.php
    }';

if (strpos($content, $old) === false) {
    echo "WARNING: Could not find the target method. The vendor file may have changed.\n";
    echo "Please manually replace the needsUpdate method body with: return null;\n";
    exit(1);
}

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Doctrine metadata patch applied successfully.\n";
