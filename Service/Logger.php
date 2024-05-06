<?php
namespace Sumkabum\Magento2ProductImport\Service;

use DateTime;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Psr\Log\AbstractLogger;
use Throwable;

class Logger extends AbstractLogger
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    protected $fileName = 'sumkabum_magento2productimport.log';

    public function __construct(
        Filesystem $filesystem
    ) {
        $this->filesystem = $filesystem;
    }

    /**
     * @param string $fileName
     * @return Logger
     */
    public function setFileName(string $fileName): Logger
    {
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void|null
     * @throws FileSystemException
     */
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param string|\Stringable $message
     * @param mixed[] $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $text = (new DateTime())->format('Y-m-d H:i:s') . ' [' . $level . ']: ';
        if ($message instanceof Throwable) {
            $text .= $message->getFile() . '(' . $message->getLine() . '): ';
            $text .= $message->getMessage() . "\n";
            $text .= $message->getTraceAsString();
        } else {
            $text .= $message;
        }
        $text .= "\n";

        $this->getDirectoryToWrite()->writeFile($this->fileName, $text, 'a');
    }

    /**
     * @return WriteInterface
     * @throws FileSystemException
     */
    protected function getDirectoryToWrite(): WriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::LOG);
    }
}
