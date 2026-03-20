<?php

declare (strict_types=1);
namespace Presta_Shop\Autoload;

final class Prestashop_Autoload
{
    private readonly \Presta_Shop\Autoload\Legacy_Class_Loader $class_loader;
    private readonly \Presta_Shop\Autoload\Autoloader $autoload;
    private bool $enable_overrides = true;
    private static ?\Presta_Shop\Autoload\Prestashop_Autoload $instance = null;
    public function __construct(string $root_directory, string $cache_directory)
    {
        $this->class_loader = new Legacy_Class_Loader($root_directory, $cache_directory);
        $this->autoload = new Autoloader($root_directory);
        $this->autoload->set_initialization_call_back(function (): void {
            $cache_file = $this->class_loader->get_class_index_filepath();
            if (is_file($cache_file)) {
                $this->autoload->set_class_index($this->class_loader->load_class_cache());
            } else {
                $this->generate_index();
            }
        });
    }
    public function generate_index(): void
    {
        $this->autoload->set_class_index($this->class_loader->build_class_index($this->enable_overrides));
    }
    public function get_class_path(string $class_name): ?string
    {
        return $this->autoload->get_class_path($class_name);
    }
    public function register(): self
    {
        $this->autoload->register();
        return $this;
    }
    public function disable_overrides(): self
    {
        $this->enable_overrides = false;
        return $this;
    }
    public static function create(string $root_directory, string $cache_directory): self
    {
        self::$instance = new self($root_directory, $cache_directory);
        return self::$instance;
    }
    public static function get_instance(): self
    {
        return self::$instance;
    }
}