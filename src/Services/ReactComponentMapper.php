<?php
namespace Gravitycar\Services;

use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Core\ServiceLocator;
use Psr\Log\LoggerInterface;

/**
 * ReactComponentMapper: Maps field types to React components and validation rules
 */
class ReactComponentMapper {
    private MetadataEngine $metadataEngine;
    private LoggerInterface $logger;
    private array $fieldComponentMap;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->logger = ServiceLocator::getLogger();
        $this->initializeFieldComponentMap();
    }
    
    /**
     * Initialize field component mapping from cached metadata
     */
    private function initializeFieldComponentMap(): void {
        // Get dynamically discovered field types from cache
        $fieldTypes = $this->metadataEngine->getFieldTypeDefinitions();
        
        // Build component map from cached metadata
        $this->fieldComponentMap = [];
        foreach ($fieldTypes as $fieldType => $fieldData) {
            $this->fieldComponentMap[$fieldType] = [
                'component' => $fieldData['react_component'] ?? $this->getDefaultReactComponent($fieldType),
                'props' => $this->getDefaultPropsForFieldType($fieldType),
                'validation_support' => $fieldData['validation_rules'] ?? []
            ];
        }
    }
    
    /**
     * Generate React form schema for a model
     */
    public function generateFormSchema(string $modelName): array {
        try {
            $modelData = $this->metadataEngine->getModelMetadata($modelName);
            
            $formSchema = [
                'model' => $modelName,
                'layout' => 'vertical',
                'fields' => []
            ];
            
            foreach ($modelData['fields'] ?? [] as $fieldName => $fieldData) {
                $formSchema['fields'][$fieldName] = [
                    'component' => $this->getReactComponentForField($fieldData),
                    'props' => $this->getComponentPropsFromField($fieldData),
                    'validation' => $this->getReactValidationRules($fieldData),
                    'label' => $fieldData['label'] ?? ucfirst($fieldName),
                    'required' => $fieldData['required'] ?? false
                ];
            }
            
            return $formSchema;
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate form schema for {$modelName}: " . $e->getMessage());
        }
    }
    
    /**
     * Get React component for a field based on field data
     */
    public function getReactComponentForField(array $fieldData): string {
        $fieldType = $fieldData['type'] ?? 'Text';
        return $this->fieldComponentMap[$fieldType]['component'] ?? $this->getDefaultReactComponent($fieldType);
    }
    
    /**
     * Get React component for a field type
     */
    public function getReactComponentForFieldType(string $fieldType): string {
        return $this->fieldComponentMap[$fieldType]['component'] ?? $this->getDefaultReactComponent($fieldType);
    }
    
    /**
     * Get component props for a field type
     */
    public function getComponentPropsForFieldType(string $fieldType): array {
        return $this->fieldComponentMap[$fieldType]['props'] ?? $this->getDefaultPropsForFieldType($fieldType);
    }
    
    /**
     * Get React validation rules from field data
     */
    public function getReactValidationRules(array $fieldData): array {
        $validationRules = $fieldData['validationRules'] ?? [];
        $reactValidation = [];
        
        // Map from metadata validation rules to React validation
        foreach ($validationRules as $rule) {
            if (is_string($rule)) {
                switch ($rule) {
                    case 'Required':
                        $reactValidation['required'] = true;
                        $reactValidation['message'] = 'This field is required';
                        break;
                    case 'Email':
                        $reactValidation['type'] = 'email';
                        $reactValidation['message'] = 'Please enter a valid email address';
                        break;
                    case 'Alphanumeric':
                        $reactValidation['pattern'] = '/^[a-zA-Z0-9]+$/';
                        $reactValidation['message'] = 'Only letters and numbers are allowed';
                        break;
                }
            } elseif (is_array($rule) && isset($rule['name'])) {
                switch ($rule['name']) {
                    case 'Required':
                        $reactValidation['required'] = true;
                        $reactValidation['message'] = $rule['description'] ?? 'This field is required';
                        break;
                    case 'Email':
                        $reactValidation['type'] = 'email';
                        $reactValidation['message'] = $rule['description'] ?? 'Please enter a valid email address';
                        break;
                    case 'Alphanumeric':
                        $reactValidation['pattern'] = '/^[a-zA-Z0-9]+$/';
                        $reactValidation['message'] = $rule['description'] ?? 'Only letters and numbers are allowed';
                        break;
                }
            }
        }
        
        // Add field-specific validation from metadata
        if (isset($fieldData['required']) && $fieldData['required']) {
            $reactValidation['required'] = true;
        }
        
        if (isset($fieldData['unique']) && $fieldData['unique']) {
            $reactValidation['unique'] = true;
        }
        
        if (isset($fieldData['max_length'])) {
            $reactValidation['maxLength'] = $fieldData['max_length'];
        }
        
        if (isset($fieldData['maxLength'])) {
            $reactValidation['maxLength'] = $fieldData['maxLength'];
        }
        
        return $reactValidation;
    }
    
    /**
     * Get component props from field data
     */
    public function getComponentPropsFromField(array $fieldData): array {
        $fieldType = $fieldData['type'] ?? 'Text';
        $baseProps = $this->fieldComponentMap[$fieldType]['props'] ?? [];
        $props = [];
        
        // Map metadata properties to component props
        foreach ($baseProps as $prop) {
            switch ($prop) {
                case 'placeholder':
                    $props['placeholder'] = $fieldData['placeholder'] ?? $fieldData['label'] ?? '';
                    break;
                case 'maxLength':
                    $props['maxLength'] = $fieldData['maxLength'] ?? $fieldData['max_length'] ?? null;
                    break;
                case 'defaultValue':
                    $props['defaultValue'] = $fieldData['defaultValue'] ?? null;
                    break;
                case 'options':
                    $props['options'] = $fieldData['options'] ?? [];
                    break;
                case 'multiple':
                    $props['multiple'] = $fieldData['multiple'] ?? false;
                    break;
                case 'disabled':
                    $props['disabled'] = $fieldData['disabled'] ?? false;
                    break;
                case 'readonly':
                    $props['readonly'] = $fieldData['readonly'] ?? false;
                    break;
            }
        }
        
        return array_filter($props, function($value) {
            return $value !== null;
        });
    }
    
    /**
     * Get field to component mapping
     */
    public function getFieldToComponentMap(): array {
        return $this->fieldComponentMap;
    }
    
    /**
     * Get default React component for field type
     */
    private function getDefaultReactComponent(string $fieldType): string {
        $componentMap = [
            'Text' => 'TextInput',
            'Email' => 'EmailInput',
            'Password' => 'PasswordInput',
            'BigText' => 'TextArea',
            'Integer' => 'NumberInput',
            'Float' => 'NumberInput',
            'Boolean' => 'Checkbox',
            'Date' => 'DatePicker',
            'DateTime' => 'DateTimePicker',
            'Enum' => 'Select',
            'MultiEnum' => 'MultiSelect',
            'RadioButtonSet' => 'RadioGroup',
            'RelatedRecord' => 'RelatedRecordSelect',
            'ID' => 'HiddenInput',
            'Image' => 'ImageUpload'
        ];
        
        return $componentMap[$fieldType] ?? 'TextInput';
    }
    
    /**
     * Get default props for field type
     */
    private function getDefaultPropsForFieldType(string $fieldType): array {
        $propsMap = [
            'Text' => ['placeholder', 'maxLength'],
            'Email' => ['placeholder'],
            'Password' => ['placeholder'],
            'BigText' => ['placeholder', 'rows'],
            'Integer' => ['placeholder', 'min', 'max'],
            'Float' => ['placeholder', 'min', 'max', 'step'],
            'Boolean' => ['defaultChecked'],
            'Date' => ['format'],
            'DateTime' => ['format', 'showTime'],
            'Enum' => ['options', 'placeholder'],
            'MultiEnum' => ['options', 'multiple'],
            'RadioButtonSet' => ['options'],
            'RelatedRecord' => ['modelName', 'displayField'],
            'ID' => [],
            'Image' => ['accept', 'maxSize']
        ];
        
        return $propsMap[$fieldType] ?? ['placeholder'];
    }
}
