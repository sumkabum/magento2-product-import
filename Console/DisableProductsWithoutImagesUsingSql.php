<?php

namespace Sumkabum\Magento2ProductImport\Console;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DisableProductsWithoutImagesUsingSql extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Sumkabum\Magento2ProductImport\Service\DisableProductsWithoutImagesUsingSql
     */
    private $disableProductsWithoutImagesUsingSql;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        \Sumkabum\Magento2ProductImport\Service\DisableProductsWithoutImagesUsingSql $disableProductsWithoutImagesUsingSql,
        LoggerInterface $logger,
        string $name = null
    ) {
        parent::__construct($name);
        $this->disableProductsWithoutImagesUsingSql = $disableProductsWithoutImagesUsingSql;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('sumkabum:disable-products-without-images-using-sql');
    }

    /**
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->disableProductsWithoutImagesUsingSql->execute(
            $output,
            null,
            $this->logger
        );
    }
}
