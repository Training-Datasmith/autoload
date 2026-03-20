<?php

declare (strict_types=1);
namespace Presta_Shop\Autoload;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Spl_File_Info;
/**
 * Parses all the core classes to build indexes file used by the Prestashop Autoload.
 */
final class Legacy_Class_Loader
{
    public const CORE_SUFFIX = 'Core';
    /**
     * @var string[]
     */
    private array $core_directories = ['classes', 'controllers'];
    /**
     * @var string[]
     */
    private array $override_directories = ['override'];
    private readonly \Symfony\Component\Filesystem\Filesystem $filesystem;
    public const TYPE_CLASS = 'class';
    public const TYPE_ABSTRACT_CLASS = 'abstract class';
    public const TYPE_INTERFACE = 'interface';
    private readonly string $root_directory;
    public function __construct(string $directory, private readonly string $cache_directory)
    {
        if (!str_ends_with($directory, DIRECTORY_SEPARATOR)) {
            $directory .= DIRECTORY_SEPARATOR;
        }
        $this->root_directory = $directory;
        $this->filesystem = new Filesystem();
    }
    /**
     * @return array<int|string, array<string, string|null>>
     */
    public function build_class_index(bool $enable_overrides): array
    {
        $this->filesystem->mkdir($this->cache_directory);
        $classes = $this->parse_classes($this->load_classes($this->core_directories));
        if ($enable_overrides) {
            $override_classes = $this->parse_classes($this->load_classes($this->override_directories));
            $classes = array_merge($classes, $override_classes);
        }
        ksort($classes);
        $this->dump_class_index($classes);
        return $classes;
    }
    /**
     * Get Class index cache file.
     */
    public function get_class_index_filepath(): string
    {
        return $this->cache_directory . 'class_index.php';
    }
    /**
     * @param array<string> $directories
     *
     * @return array<string, SplFileInfo>
     */
    private function load_classes(array $directories): array
    {
        // The finder cannot loop on directories that does not exist.
        // So we must check dirs before putting them in the finder
        $directories = array_filter(array_map(fn(string $value) => $this->root_directory . $value, $directories), static fn(string $directory) => is_dir($directory));
        if ([] === $directories) {
            return [];
        }
        $finder = new Finder();
        $finder->in($directories)->follow_links()->files()->name('*.php');
        return iterator_to_array($finder->getIterator());
    }
    /**
     * @param SplFileInfo[] $files
     *
     * @return array<int|string, array<string, string|null>>
     */
    private function parse_classes(array $files): array
    {
        $core_suffix_length = strlen(self::CORE_SUFFIX);
        $classes = [];
        $root_dir_str_len = strlen($this->root_directory);
        foreach ($files as $file) {
            $content = $file->get_contents();
            $name_pattern = '[a-z_\x7f-\xff][a-z0-9_\x7f-\xff]*';
            $name_with_ns_pattern = '(?:\\\\?(?:' . $name_pattern . '\\\\)*' . $name_pattern . ')';
            $pattern = '~(?<!\w)((abstract\s+)?class|interface)\s+(?P<classname>' . $file->get_basename('.php') . '(?:' . self::CORE_SUFFIX . ')?)' . '(?:\s+extends\s+' . $name_with_ns_pattern . ')?(?:\s+implements\s+' . $name_with_ns_pattern . '(?:\s*,\s*' . $name_with_ns_pattern . ')*)?\s*\{~i';
            if (preg_match($pattern, $content, $m)) {
                $classes[$m['classname']] = ['path' => substr($file->get_pathname(), $root_dir_str_len), 'type' => trim($m[1])];
                if (str_ends_with($m['classname'], self::CORE_SUFFIX)) {
                    $classes[substr($m['classname'], 0, -$core_suffix_length)] = ['path' => null, 'type' => $classes[$m['classname']]['type']];
                }
            }
        }
        return $classes;
    }
    /**
     * @param array<int|string, array<string, string|null>> $classes
     */
    private function dump_class_index(array $classes): void
    {
        $content = '<?php return ' . var_export($classes, true) . '; ?>';
        $this->filesystem->dump_file($this->get_class_index_filepath(), $content);
    }
    public function get_cache_directory(): string
    {
        return $this->cache_directory;
    }
    /**
     * @return array<int|string, array<string, string|null>>
     */
    public function load_class_cache(): array
    {
        $cache_file = $this->get_class_index_filepath();
        if (is_file($cache_file) && is_readable($cache_file)) {
            return include $cache_file;
        }
        throw new \RuntimeException(self::class . ' has no cache file');
    }
}