<?php

declare (strict_types=1);
namespace Presta_Shop\Autoload;

use Closure;
final class Autoloader
{
    private bool $registered = false;
    /**
     * @var array<int|string, array<string, string|null>>
     */
    private array $class_index = [];
    /**
     * Must end with \\ because some classes exists with a namespace begining by `Entity`.
     * ex with class PrestaShop\PrestaShop\Adapter\EntityTranslation\DataLangFactory;.
     */
    private const NAMESPACED_CLASSES = 'PrestaShop\PrestaShop\Adapter\Entity\\';
    /**
     * @var string[]
     */
    private static array $class_aliases = ['Collection' => 'PrestaShopCollection', 'Autoload' => 'PrestaShopAutoload', 'Backup' => 'PrestaShopBackup', 'Logger' => 'PrestaShopLogger'];
    private readonly string $root_directory;
    /**
     * @var array<string, bool>
     */
    private array $loaded_classes = [];
    private bool $initialized = false;
    private ?\Closure $initialization_callback = null;
    public function __construct(string $directory)
    {
        if (!str_ends_with($directory, DIRECTORY_SEPARATOR)) {
            $directory .= DIRECTORY_SEPARATOR;
        }
        $this->root_directory = $directory;
    }
    public function register(): void
    {
        if ($this->registered) {
            throw new \RuntimeException('Autoload is already registered.');
        }
        spl_autoload_register($this->load(...));
        $this->registered = true;
    }
    public function load(string $class_name): void
    {
        if (!$this->initialized && null !== $this->initialization_callback) {
            ($this->initialization_callback)();
            $this->initialized = true;
        }
        if (str_starts_with($class_name, self::NAMESPACED_CLASSES)) {
            $class_without_ns = substr($class_name, strlen(self::NAMESPACED_CLASSES));
            class_alias($class_without_ns, '\\' . $class_name);
            return;
        }
        if (array_key_exists($class_name, $this->loaded_classes)) {
            return;
        }
        if (array_key_exists($class_name, self::$class_aliases)) {
            $this->load_class_alias($class_name);
        }
        // Class is not handled is this autoload
        if (!array_key_exists($class_name, $this->class_index)) {
            return;
        }
        // Try to load a class that is in classIndex and has a specified path (ex: core classes, interfaces)
        if (null !== $this->class_index[$class_name]['path']) {
            require_once $this->root_directory . $this->class_index[$class_name]['path'];
            $this->loaded_classes[$class_name] = true;
            return;
        }
        $core_class = $class_name . Legacy_Class_Loader::CORE_SUFFIX;
        if (array_key_exists($core_class, $this->class_index) && $this->is_class((string) $this->class_index[$core_class]['type'])) {
            // cannot use class_alias because get_class() returns the Core class name
            // @see https://www.php.net/manual/en/function.class-alias.php#101636
            eval($this->class_index[$core_class]['type'] . ' ' . $class_name . ' extends ' . $core_class . ' {}');
        }
        $this->loaded_classes[$class_name] = true;
    }
    /**
     * @param array<int|string, array<string, string|null>> $classIndex
     */
    public function set_class_index(array $class_index): void
    {
        $this->class_index = $class_index;
    }
    public function get_class_path(string $class_name): ?string
    {
        return $this->class_index[$class_name]['path'] ?? null;
    }
    private function is_class(string $type): bool
    {
        return Legacy_Class_Loader::TYPE_CLASS === $type || Legacy_Class_Loader::TYPE_ABSTRACT_CLASS === $type;
    }
    private function load_class_alias(string $class_name): void
    {
        eval('class ' . $class_name . ' extends ' . self::$class_aliases[$class_name] . ' {}');
        $this->loaded_classes[$class_name] = true;
    }
    public function set_initialization_call_back(Closure $closure): self
    {
        $this->initialization_callback = $closure;
        return $this;
    }
}