<?php
namespace Gravitycar\ComponentGenerator;

use Gravitycar\Core\ComponentGeneratorBase;
use Monolog\Logger;

/**
 * DefaultComponentGenerator: Generates a basic React component for any field type.
 */
class DefaultComponentGenerator extends ComponentGeneratorBase {
    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
    }

    public function generateComponent(): string {
        $fieldType = $this->metadata['type'] ?? 'Text';
        $fieldName = $this->metadata['name'] ?? '';
        $label = $this->metadata['label'] ?? $fieldName;

        return "
import React from 'react';

const {$fieldType}Field = ({ value, onChange, label, required }) => {
    return (
        <div className=\"field-container\">
            <label htmlFor=\"{$fieldName}\">{$label}</label>
            <input 
                type=\"text\" 
                name=\"{$fieldName}\" 
                id=\"{$fieldName}\"
                value={value || ''}
                onChange={(e) => onChange(e.target.value)}
                required={required}
            />
        </div>
    );
};

export default {$fieldType}Field;
        ";
    }
}
