<?php
namespace Sumkabum\Magento2ProductImport\Service;

class Report
{
    /**
     * @var string
     */
    public $title = 'report';
    /**
     * @var string[]
     */
    public $messages = [];

    const KEY_START_TIME = 'Start Time';
    const KEY_END_TIME = 'End Time';
    const KEY_IMAGES_ADDED = 'Images Added';
    const KEY_IMAGES_REMOVED = 'Images Removed';
    const KEY_PRODUCTS_PROCESSED = 'Products Processed';
    const KEY_PRODUCTS_CREATED = 'Products Created';
    const KEY_PRODUCTS_UPDATED = 'Products Updated';
    const KEY_PRODUCTS_DIDNT_NEED_UPDATING = 'Products didnt need updating';
    const KEY_STATUS_CHANGED_TO_ENABLED = 'Status Changed To Enabled';
    const KEY_STATUS_CHANGED_TO_DISABLED = 'Status Changed To Disabled';
    const KEY_ERRORS = 'Errors';

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    public function increaseByNumber(string $key, int $number = 1)
    {
        if (!isset($this->messages[$key])) {
            $this->messages[$key] = 0;
        }
        $this->messages[$key] += $number;
    }

    public function addMessage(string $key, string $message)
    {
        if (!isset($this->messages[$key])) {
            $this->messages[$key] = [];
        }
        $this->messages[$key][] = $message;
    }

    public function getMessagesAsHtml(): string
    {
        $customMessagesString = '';

        foreach ($this->messages as $key => $value) {

            if (is_array($value)) {
                $value = implode('<br>', $value);
            }

            $customMessagesString .= "
                <tr>
                    <td>$key</td>
                    <td>$value</td>
                </tr>";
        }
        return $customMessagesString;
    }

    public function getMessagesAsString(): string
    {
        $messages = [];

        foreach ($this->messages as $key => $value) {
            if (is_array($value)) {
                $value = implode("\n", $value);
            }
            $messages[] = "$key: $value";
        }
        return implode("\n", $messages);
    }

    public function getReportStringHtml(): string
    {
        return <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$this->title}</title>
    <style>
        td {
            vertical-align: top;
        }
</style>
</head>
<body>
    <table>
        {$this->getMessagesAsHtml()}
    </table>
</body>
</html>
EOT;
    }

    public function getReportString(): string
    {
        $string = '';
        if (!empty($this->getMessagesAsString())) {
            $string .= $this->getMessagesAsString() . "\n";
        }
        return $string;
    }
}
