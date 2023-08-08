<?php

declare(strict_types=1);

namespace M3usm\M3uPhpTools;

use ArrayIterator;

class M3uTextStream extends ArrayIterator
{
    /**
     * @param  string|null  $text
     */
    public function __construct(string $text = null)
    {
        $lines = [];
        if (null !== $text) {
            $lines = $this->prepareStream($text);
        }
        parent::__construct($lines);
    }

    /**
     * Trim lines and delete blank lines
     * @param  string  $text  - plain text
     * @return array<string> - prepared array
     */
    private function prepareStream(string $text): array
    {
        $text = $this->removeUtf8BOM($text);
        $lines = explode("\n", trim((string)$text));
        $lines = array_map('trim', $lines);

        return array_filter($lines);
    }

    /**
     * @param  string  $text
     * @return string|null
     */
    private function removeUtf8BOM(string $text): ?string
    {
        $bom = pack('H*', 'EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }

    /**
     * Add string line to stream
     * @param  string  $line
     * @return $this
     */
    public function add(string $line): M3uTextStream
    {
        $this->append($line);
        return $this;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return implode("\n", $this->getArrayCopy())."\n";
    }
}