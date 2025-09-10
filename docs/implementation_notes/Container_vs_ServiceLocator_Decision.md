# Container vs ServiceLocator: Architectural Decision

## The Question
Should we recommend using `Container->get('model_factory')` or `ServiceLocator::getModelFactory()` for model creation in our pure dependency injection architecture?

## Analysis

### ServiceLocator Implementation
```php
public static function getModelFactory(): \Gravitycar\Factories\ModelFactory {
    return self::getContainer()->get('model_factory');
}
```

ServiceLocator is literally just a wrapper around the Container - it's the same instance!

### Comparison

| Aspect | Container Approach | ServiceLocator Approach |
|--------|-------------------|-------------------------|
| **Explicitness** | ✅ Clear DI usage | ❌ Hides container |
| **Consistency** | ✅ Matches pure DI goals | ❌ Contradicts "no ServiceLocator" |
| **Verbosity** | ❌ 2 lines vs 1 | ✅ Single line |
| **IDE Support** | ✅ Full type hints | ✅ Typed methods |
| **Architecture** | ✅ True dependency injection | ❌ Service locator anti-pattern |

## Decision: Container Approach

**Rationale:**
1. **Architectural Consistency**: We eliminated ServiceLocator from ModelBase constructors for pure DI
2. **Educational Value**: Shows developers proper dependency injection patterns
3. **Explicit Dependencies**: Makes it clear we're using the DI container
4. **Future-Proof**: Direct container access enables advanced DI features

## Updated Patterns

### Recommended (Container-based)
```php
// Multi-line (clear and explicit)
$container = ContainerConfig::getContainer();
$factory = $container->get('model_factory');
$model = $factory->new('Users');

// One-liner (when appropriate)
$model = ContainerConfig::getContainer()->get('model_factory')->new('Users');
```

### Legacy Compatibility (ServiceLocator)
```php
// Still works, but not recommended for new code
$factory = ServiceLocator::getModelFactory();
$model = $factory->new('Users');
```

## Testing Results

✅ **All approaches tested and working:**
- Container returns same ModelFactory instance as ServiceLocator
- All created models have proper dependency injection
- Both multi-line and one-liner patterns work correctly

## Documentation Updated

1. **`.github/copilot-instructions.md`** - Container as primary, ServiceLocator as legacy
2. **`.github/chatmodes/coder.chatmode.md`** - Container-based coding rules
3. **`docs/migration/Pure_DI_ModelBase_Migration_Guide.md`** - Container examples

## Impact

- **Developers** see consistent pure DI patterns in AI suggestions
- **Architecture** aligns with dependency injection principles
- **Legacy Code** continues working with ServiceLocator compatibility
- **Future Development** follows proper DI practices

This decision reinforces our commitment to pure dependency injection while maintaining backward compatibility.
