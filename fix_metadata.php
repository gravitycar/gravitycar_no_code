<?php
// PHP script to fix relationshipMetadata to metadata in RelationshipBase.php

$filePath = 'src/Relationships/RelationshipBase.php';
$content = file_get_contents($filePath);

// Replace all instances of $this->relationshipMetadata with $this->metadata
$content = str_replace('$this->relationshipMetadata', '$this->metadata', $content);

// Write back the file
file_put_contents($filePath, $content);

echo "Replaced all instances of \$this->relationshipMetadata with \$this->metadata in $filePath\n";
