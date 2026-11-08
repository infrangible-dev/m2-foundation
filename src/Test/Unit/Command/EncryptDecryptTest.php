<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Test\Unit\Command;

use Infrangible\Foundation\Command\Decrypt;
use Infrangible\Foundation\Command\Encrypt;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the encryption:encrypt and encryption:decrypt console commands.
 */
class EncryptDecryptTest extends TestCase
{
    /**
     * Returns an encryptor that marks values as encrypted with a prefix.
     *
     * @return EncryptorInterface
     */
    private function createEncryptor(): EncryptorInterface
    {
        $encryptor = $this->createStub(EncryptorInterface::class);

        $encryptor->method('encrypt')->willReturnCallback(
            function (string $value): string {
                return 'encrypted:' . $value;
            }
        );
        $encryptor->method('decrypt')->willReturnCallback(
            function (string $value): string {
                return substr($value, strlen('encrypted:'));
            }
        );

        return $encryptor;
    }

    /**
     * Provides command class, input value and expected output.
     *
     * @return array<string, array{class-string<Command>, string, string}>
     */
    public static function commandProvider(): array
    {
        return [
            'encrypt'                => [Encrypt::class, 'secret', 'encrypted:secret'],
            'decrypt'                => [Decrypt::class, 'encrypted:secret', 'secret'],
            'encrypt zero'           => [Encrypt::class, '0', 'encrypted:0'],
            'decrypt formatting tag' => [Decrypt::class, 'encrypted:<b>x</b>', '<b>x</b>']
        ];
    }

    /**
     * @dataProvider commandProvider
     *
     * @param class-string<Command> $commandClass
     * @param string $value
     * @param string $expectedOutput
     * @return void
     */
    #[DataProvider('commandProvider')]
    public function testCommandWritesResult(string $commandClass, string $value, string $expectedOutput): void
    {
        $commandTester = new CommandTester(new $commandClass($this->createEncryptor()));

        $exitCode = $commandTester->execute(['--value' => $value]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($expectedOutput . PHP_EOL, $commandTester->getDisplay());
    }

    /**
     * Provides the command classes.
     *
     * @return array<string, array{class-string<Command>}>
     */
    public static function commandClassProvider(): array
    {
        return ['encrypt' => [Encrypt::class], 'decrypt' => [Decrypt::class]];
    }

    /**
     * @dataProvider commandClassProvider
     *
     * @param class-string<Command> $commandClass
     * @return void
     */
    #[DataProvider('commandClassProvider')]
    public function testCommandRequiresValue(string $commandClass): void
    {
        $commandTester = new CommandTester(new $commandClass($this->createEncryptor()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing: "value"');

        $commandTester->execute([]);
    }

    /**
     * The commands are registered under their documented names.
     *
     * @return void
     */
    public function testCommandNames(): void
    {
        $this->assertSame('encryption:encrypt', (new Encrypt($this->createEncryptor()))->getName());
        $this->assertSame('encryption:decrypt', (new Decrypt($this->createEncryptor()))->getName());
    }
}
