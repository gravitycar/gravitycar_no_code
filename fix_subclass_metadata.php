<?php
// PHP script to fix relationshipMetadata to metadata in relationship subclasses

$files = [
    'src/Relationships/OneToManyRelationship.php',
    'src/Relationships/OneToOneRelationship.php',
    'src/Relationships/ManyToManyRelationship.php'
];

foreach ($files as $filePath) {
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        // Replace all instances of $this->relationshipMetadata with $this->metadata
        $content = str_replace('$this->relationshipMetadata', '$this->metadata', $content);
        
        // Write back the file
        file_put_contents($filePath, $content);
        
        echo "Replaced all instances of \$this->relationshipMetadata with \$this->metadata in $filePath\n";
    } else {
        echo "File not found: $filePath\n";
    }
}
