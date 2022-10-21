<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Autoload\LegacyClassLoader;
use Symfony\Component\Filesystem\Filesystem;

class LegacyClassLoaderTest extends TestCase
{
    private const ROOT_DIR = __DIR__ . '/test/';
    private const CACHE_DIR = __DIR__ . '/cache/';

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::ROOT_DIR); // cleanup existing files.
        $fs->remove(self::CACHE_DIR);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::ROOT_DIR);
        $fs->remove(self::CACHE_DIR);
    }

    /**
     * @dataProvider provideClasses
     */
    public function testBuildIndex(array $files, array $expected, bool $overrides = false): void
    {
        $fs = new Filesystem();
        foreach ($files as $file) {
            $fs->dumpFile(self::ROOT_DIR . $file[0], '<?php ' . $file[1]);
        }

        $legacyClassLoader = new LegacyClassLoader(self::ROOT_DIR, self::CACHE_DIR);
        $index = $legacyClassLoader->buildClassIndex($overrides);

        self::assertIsArray($index);
        self::assertSame($index, $expected);
    }

    public function provideClasses(): iterable
    {
        yield 'interfaces' => [
            [
                ['classes/ChecksumInterface.php', 'interface ChecksumInterface {}'],
            ],
            [
                'ChecksumInterface' => [
                    'path' => 'classes/ChecksumInterface.php',
                    'type' => 'interface',
                ],
            ],
        ];

        yield 'abstract classes withtout overrides' => [
            [
                ['classes/Db.php', 'abstract class DbCore {}'],
                ['classes/DbMySQLiCore.php', 'class DbMySQLiCore extends Db {}'],
            ],
            [
                'Db' => [
                    'path' => null,
                    'type' => 'abstract class',
                ],
                'DbCore' => [
                    'path' => 'classes/Db.php',
                    'type' => 'abstract class',
                ],
                'DbMySQLi' => [
                    'path' => null,
                    'type' => 'class',
                ],
                'DbMySQLiCore' => [
                    'path' => 'classes/DbMySQLiCore.php',
                    'type' => 'class',
                ],
            ],
        ];
        yield 'All combined !' => [
            [
                ['classes/Db.php', 'abstract class DbCore {}'],
                ['classes/DbMySQLiCore.php', 'class DbMySQLiCore extends Db {}'],
                ['classes/FooConfig.php', 'class FooConfigCore {}'],
                ['controllers/FrontController.php', 'class FrontControllerCore {}'],
                ['controllers/front/ProductController.php', 'class ProductControllerCore extends FrontController {}'],
            ],
            [
                'Db' => [
                    'path' => null,
                    'type' => 'abstract class',
                ],
                'DbCore' => [
                    'path' => 'classes/Db.php',
                    'type' => 'abstract class',
                ],
                'DbMySQLi' => [
                    'path' => null,
                    'type' => 'class',
                ],
                'DbMySQLiCore' => [
                    'path' => 'classes/DbMySQLiCore.php',
                    'type' => 'class',
                ],
                'FooConfig' => [
                    'path' => null,
                    'type' => 'class',
                ],
                'FooConfigCore' => [
                    'path' => 'classes/FooConfig.php',
                    'type' => 'class',
                ],
                'FrontController' => [
                    'path' => null,
                    'type' => 'class',
                ],
                'FrontControllerCore' => [
                    'path' => 'controllers/FrontController.php',
                    'type' => 'class',
                ],
                'ProductController' => [
                    'path' => null,
                    'type' => 'class',
                ],
                'ProductControllerCore' => [
                    'path' => 'controllers/front/ProductController.php',
                    'type' => 'class',
                ],
            ],
        ];

        yield 'abstract classes with overrides' => [
            [
                ['override/classes/Db.php', 'abstract class Db extends DbCore {}'],
                ['classes/Db.php', 'abstract class DbCore {}'],
            ],
            [
                'Db' => [
                    'path' => 'override/classes/Db.php',
                    'type' => 'abstract class',
                ],
                'DbCore' => [
                    'path' => 'classes/Db.php',
                    'type' => 'abstract class',
                ],
            ],
            true,
        ];

        yield 'classes with overrides' => [
            [
                ['override/classes/Tools.php', 'class Tools extends ToolsCore {}'],
                ['classes/Tools.php', 'class ToolsCore {}'],
            ],
            [
                'Tools' => [
                    'path' => 'override/classes/Tools.php',
                    'type' => 'class',
                ],
                'ToolsCore' => [
                    'path' => 'classes/Tools.php',
                    'type' => 'class',
                ],
            ],
            true,
        ];
    }

    public function testGetCacheDirectory(): void
    {
        $classLoader = new LegacyClassLoader(__DIR__, 'test');
        self::assertSame('test', $classLoader->getCacheDirectory());
    }
}
