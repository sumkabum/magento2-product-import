<?php

namespace Sumkabum\Magento2ProductImport\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DisableProductsWithoutImages extends \Symfony\Component\Console\Command\Command
{
    const OPTION_DELETE_INSTEAD_DISABLE = 'delete-instead-disable';
    /**
     * @var \Sumkabum\Magento2ProductImport\Service\DisableProductsWithoutImages
     */
    private $disableProductsWithoutImages;

    public function __construct(
        \Sumkabum\Magento2ProductImport\Service\DisableProductsWithoutImages $disableProductsWithoutImages,
        string $name = null
    ) {
        parent::__construct($name);
        $this->disableProductsWithoutImages = $disableProductsWithoutImages;
    }

    protected function configure()
    {
        $this->setName('sumkabum:disable-products-without-images');
        $this->addOption(self::OPTION_DELETE_INSTEAD_DISABLE, null, InputOption::VALUE_OPTIONAL, 'Delete products instead disabling');
    }

    /**
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->disableProductsWithoutImages->execute(
            $input->getOption(self::OPTION_DELETE_INSTEAD_DISABLE) ?? false,
            $output
        );
    }
}
