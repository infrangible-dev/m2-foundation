<?php

declare(strict_types=1);

namespace Infrangible\Foundation\Command;

use Magento\Framework\Encryption\EncryptorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Decrypt
    extends Command
{
    /** command name */
    public const NAME = 'encryption:decrypt';

    /** option which value to encrypt */
    public const OPTION_VALUE = 'value';

    /** @var EncryptorInterface */
    protected $encryptor;

    /**
     * @param EncryptorInterface $encryptor
     * @param string|null        $name
     */
    public function __construct(EncryptorInterface $encryptor, string $name = null)
    {
        parent::__construct($name);

        $this->encryptor = $encryptor;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName(static::NAME)->setDescription('Decrypt value');

        $this->addOption(static::OPTION_VALUE, null, InputOption::VALUE_REQUIRED, 'Value to decrypt');

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $value = $input->getOption(static::OPTION_VALUE);

        if (empty($value)) {
            throw new RuntimeException(sprintf('Not enough arguments (missing: "%s").', static::OPTION_VALUE));
        }

        echo $this->encryptor->decrypt($value);
    }
}
