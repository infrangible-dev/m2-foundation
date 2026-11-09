<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\Foundation\Command;

use Magento\Framework\Encryption\EncryptorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command that decrypts a value with the encryption key of the installation.
 */
class Decrypt extends Command
{
    /** command name */
    public const NAME = 'encryption:decrypt';

    /** option which value to decrypt */
    public const OPTION_VALUE = 'value';

    /**
     * @param EncryptorInterface $encryptor
     * @param string|null $name
     */
    public function __construct(
        protected EncryptorInterface $encryptor,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(static::NAME)->setDescription('Decrypt value');

        $this->addOption(
            static::OPTION_VALUE,
            null,
            InputOption::VALUE_REQUIRED,
            'Value to decrypt'
        );

        parent::configure();
    }

    /**
     * Writes the decrypted value to the output.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws RuntimeException if no value was given
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getOption(static::OPTION_VALUE);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(
                sprintf(
                    'Not enough arguments (missing: "%s").',
                    static::OPTION_VALUE
                )
            );
        }

        $output->writeln(
            $this->encryptor->decrypt($value),
            OutputInterface::OUTPUT_RAW
        );

        return Command::SUCCESS;
    }
}
