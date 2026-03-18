<?php

declare(strict_types=1);

namespace PrestaShop\Autoload;

final class PrestashopAutoload
{
    private readonly \PrestaShop\Autoload\LegacyClassLoader $classLoader;

    private readonly \PrestaShop\Autoload\Autoloader $autoload;

    private bool $enableOverrides = true;

    private static ?\PrestaShop\Autoload\PrestashopAutoload $instance = null;

    public function __construct(string $rootDirectory, string $cacheDirectory)
    {
        $this->classLoader = new LegacyClassLoader($rootDirectory, $cacheDirectory);
        $this->autoload = new Autoloader($rootDirectory);

        $this->autoload->setInitializationCallBack(
            function (): void {
                $cacheFile = $this->classLoader->getClassIndexFilepath();
                if (is_file($cacheFile)) {
                    $this->autoload->setClassIndex($this->classLoader->loadClassCache());
                } else {
                    $this->generateIndex();
                }
            }
        );
    }

    public function generateIndex(): void
    {
        $this->autoload->setClassIndex(
            $this->classLoader->buildClassIndex($this->enableOverrides)
        );
    }

    public function getClassPath(string $className): ?string
    {
        return $this->autoload->getClassPath($className);
    }

    public function register(): self
    {
        $this->autoload->register();

        return $this;
    }

    public function disableOverrides(): self
    {
        $this->enableOverrides = false;

        return $this;
    }

    public static function create(string $rootDirectory, string $cacheDirectory): self
    {
        self::$instance = new self($rootDirectory, $cacheDirectory);

        return self::$instance;
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }
}
