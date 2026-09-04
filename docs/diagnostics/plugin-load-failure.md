# OJS 3.5 plugin load failure diagnosis

## Symptom

The OJS plugin list hangs or fails to render when the Metafora import/export plugin directory is present. Removing the plugin directory restores the plugin list.

## Root cause

`PKP\plugins\ImportExportPlugin` is an abstract class and declares the abstract method:

```php
abstract public function executeCLI($scriptName, &$args);
```

The initial Metafora plugin implementation did not implement this method. PHP therefore cannot instantiate `MetaforaExportPlugin` while OJS enumerates plugins.

## Fix

Implement a minimal `executeCLI()` method and `usage()` helper. CLI export is intentionally disabled until a stable export contract is implemented.

## Verification

1. `php -l` passes for `MetaforaExportPlugin.php`.
2. Plugin can be instantiated as a concrete subclass of `ImportExportPlugin`.
3. OJS plugin list renders with the plugin directory present.
4. Import/Export plugin page can be opened before enabling API operations.
