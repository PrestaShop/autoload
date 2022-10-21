<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Autoload\Autoloader;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @group isolatedProcess
 */
class AutoloaderTest extends TestCase
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

    public function testAutoload(): void
    {
        $files = [
            ['classes/FooInterface.php', 'interface FooInterface {}'],
            ['classes/Foo.php', 'class FooCore implements FooInterface {}'],
        ];
        $fs = new Filesystem();
        foreach ($files as $file) {
            $fs->dumpFile(self::ROOT_DIR . $file[0], "<?php \n" . $file[1]);
        }

        $classIndex = [
            'FooInterface' => [
                'path' => 'classes/FooInterface.php',
                'type' => 'interface',
            ],
            'Foo' => [
                'path' => null,
                'type' => 'class',
            ],
            'FooCore' => [
                'path' => 'classes/Foo.php',
                'type' => 'class',
            ],
        ];

        self::assertFalse(class_exists('Foo'));
        self::assertFalse(class_exists('FooInterface'));

        $autoload = new Autoloader(self::ROOT_DIR);
        $autoload->setClassIndex($classIndex);

        spl_autoload_register([$autoload, 'load']);
        $foo = new \Foo(); // @phpstan-ignore-line
        self::assertInstanceOf(\FooCore::class, $foo); // @phpstan-ignore-line
        self::assertInstanceOf(\FooInterface::class, $foo); // @phpstan-ignore-line
        spl_autoload_unregister([$autoload, 'load']);

        self::assertTrue(class_exists('Foo'));
        self::assertSame('Foo', get_class($foo));
        self::assertTrue(interface_exists('FooInterface'));
    }

    public function testAutoloadAbstract(): void
    {
        $classIndex = [
            'AbstractBar' => [
                'path' => null,
                'type' => 'class',
            ],
            'AbstractBarCore' => [
                'path' => 'classes/AbstractBar.php',
                'type' => 'class',
            ],
            'Bar' => [
                'path' => null,
                'type' => 'class',
            ],
            'BarCore' => [
                'path' => 'classes/Bar.php',
                'type' => 'class',
            ],
            'Baz' => [
                'path' => null,
                'type' => 'class',
            ],
            'BazCore' => [
                'path' => 'classes/Baz.php',
                'type' => 'class',
            ],
            'AbstractObject' => [
                'path' => null,
                'type' => 'class',
            ],
            'AbstractObjectCore' => [
                'path' => 'classes/AbstractObject.php',
                'type' => 'class',
            ],
        ];

        // Bar & Baz extends the same class that extends AbstractObject
        $files = [
            ['classes/Bar.php', 'class BarCore extends AbstractBar {}'],
            ['classes/Baz.php', 'class BazCore extends AbstractBar {}'],
            ['classes/AbstractBar.php', 'abstract class AbstractBarCore extends AbstractObject {}'],
            ['classes/AbstractObject.php', 'abstract class AbstractObjectCore {}'],
        ];
        $fs = new Filesystem();
        foreach ($files as $file) {
            $fs->dumpFile(self::ROOT_DIR . $file[0], "<?php \n" . $file[1]);
        }

        $autoload = new Autoloader(self::ROOT_DIR);
        $autoload->setClassIndex($classIndex);

        spl_autoload_register([$autoload, 'load']);
        new \Bar(); // @phpstan-ignore-line
        new \PrestaShop\PrestaShop\Adapter\Entity\Baz(); // @phpstan-ignore-line
        new \PrestaShop\PrestaShop\Adapter\Entity\Bar(); // @phpstan-ignore-line
        new \Baz(); // @phpstan-ignore-line
        spl_autoload_unregister([$autoload, 'load']);

        self::assertTrue(class_exists('Bar'));
        self::assertTrue(class_exists('Baz'));
        self::assertTrue(class_exists('AbstractBar'));
        self::assertTrue(class_exists('AbstractBarCore'));
        self::assertTrue(class_exists('AbstractObject'));
        self::assertTrue(class_exists('AbstractObjectCore'));
    }

    public function testRegisterAutoloadTwiceDoesNotWork(): void
    {
        $autoload = new Autoloader(self::ROOT_DIR);
        $autoload->register(); // register autoload once.

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Autoload is already registered.');

        try {
            $autoload->register();
        } finally {
            spl_autoload_unregister([$autoload, 'load']); // for next tests
        }
    }

    public function testCallLoadClassTwiceShouldWork(): void
    {
        $classIndex = [
            'BarTest' => [
                'path' => null,
                'type' => 'class',
            ],
            'BarTestCore' => [
                'path' => 'classes/BarTest.php',
                'type' => 'class',
            ],
            'PrestaShopLogger' => [
                'path' => null,
                'type' => 'class',
            ],
            'PrestaShopLoggerCore' => [
                'path' => 'classes/PrestaShopLogger.php',
                'type' => 'class',
            ],
        ];

        $files = [
            ['classes/BarTest.php', 'class BarTestCore {}'],
            ['classes/PrestaShopLogger.php', 'class PrestaShopLoggerCore {}'],
        ];

        $autoload = new Autoloader(self::ROOT_DIR);
        $autoload->setClassIndex($classIndex);
        $autoload->register(); // register autoload once.

        $fs = new Filesystem();
        foreach ($files as $file) {
            $fs->dumpFile(self::ROOT_DIR . $file[0], "<?php \n" . $file[1]);
        }

        $autoload->load('BarTest');
        $autoload->load('BarTest');
        $autoload->load('Logger'); // Logger is an alias to PrestaShopLogger
        $autoload->load('Logger');
        self::assertTrue(true);

        spl_autoload_unregister([$autoload, 'load']);
    }

    public function testAutoloadCallsStaticMethods(): void
    {
        $classIndex = [
            'Baz' => [
                'path' => null,
                'type' => 'class',
            ],
            'BazCore' => [
                'path' => 'classes/Baz.php',
                'type' => 'class',
            ],
        ];

        $files = [
            [
                'classes/Baz.php',
                'class BazCore {
                    public static function foo () {
                        return Baz::getStaticValue();
                    }
                    public static function getStaticValue () {
                        return "Hello world";
                    }
                }',
            ],
        ];
        $fs = new Filesystem();
        foreach ($files as $file) {
            $fs->dumpFile(self::ROOT_DIR . $file[0], "<?php \n" . $file[1]);
        }

        $autoload = new Autoloader(self::ROOT_DIR);
        $autoload->setClassIndex($classIndex);

        spl_autoload_register([$autoload, 'load']);
        \Baz::foo(); // @phpstan-ignore-line
        spl_autoload_unregister([$autoload, 'load']);

        self::assertSame('Hello world', \Baz::foo()); // @phpstan-ignore-line
    }
}
