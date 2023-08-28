<?php
declare(strict_types=1);

namespace M3usm\M3uPhpTools;

/**
 *  M3u Playlist parser with state machine
 */
class M3uParserStateMachine
{
    // States
    const STATE_INITIAL = 0;
    const STATE_PROCESS_TAGS = 1;
    const STATE_EXT_M3U = 2;
    const STATE_EXTINF = 3;
    const STATE_EXTGRP = 4;
    const STATE_EXTTV = 5;
    const STATE_MEDIA = 6;
    const STATE_COMMENT = 7;
    const STATE_MEDIA_SEGMENT_END = 10;

    /**
     * Current group of media segment
     * @var string
     */
    private $currentGroup;


    /**
     * Main function of parsing
     * @param M3uTextStream $stream
     * @return array
     */
    public function parsePlaylist(M3uTextStream $stream): array
    {
        // Initialize
        $currentState = self::STATE_INITIAL;
        $this->currentGroup = '';
        $result = array();
        $currentItem = array();

        while ($stream->valid()) {
            $currentLine = $stream->current();

            switch ($currentState) {
                case self::STATE_INITIAL:
                    // Check if the currentLine starts with '#EXTM3U'
                    if (stripos($currentLine, '#EXTM3U') === 0) {
                        $currentState = self::STATE_EXT_M3U;
                        break;
                    }
                    $stream->next();
                    break;

                case self::STATE_PROCESS_TAGS:

                    if (stripos($currentLine, '#EXTINF:') === 0) {
                        $currentState = self::STATE_EXTINF;
                    } elseif (stripos($currentLine, '#EXTGRP:') === 0) {
                        $currentState = self::STATE_EXTGRP;
                    } elseif (stripos($currentLine, '#EXTTV:') === 0) {
                        $currentState = self::STATE_EXTTV;
                    } elseif (stripos($currentLine, '#') === 0) {
                        $currentState = self::STATE_COMMENT;
                    } else {
                        $currentState = self::STATE_MEDIA;
                    }
                    break;

                case self::STATE_EXT_M3U:
                    // Process EXTM3U header
                    $this->processExtM3uHeader($currentLine);

                    $currentState = self::STATE_PROCESS_TAGS;
                    $stream->next();
                    break;

                case self::STATE_EXTTV:
                    // Process EXTTV tag
                    $currentItem['exttv'] = $this->processExtTvTag($currentLine);;

                    $currentState = self::STATE_PROCESS_TAGS;
                    $stream->next();
                    break;

                case self::STATE_EXTGRP:
                    // Process EXTGRP tag
                    $grp = $this->processExtGrpTag($currentLine);
                    if ($grp !== '') {
                        $this->currentGroup = $grp;
                    }

                    $currentState = self::STATE_PROCESS_TAGS;
                    $stream->next();
                    break;

                case self::STATE_EXTINF:
                    // Process EXTINF tag
                    $currentItem = $this->processExtInfTag($currentLine);
                    $currentItem = $this->processExtInfAttributes($currentItem);

                    $currentState = self::STATE_PROCESS_TAGS;
                    $stream->next();
                    break;

                case self::STATE_MEDIA:
                    // Process url line in playlist
                    $currentItem['url'] = $currentLine;

                    $currentState = self::STATE_MEDIA_SEGMENT_END;
                    break;

                case self::STATE_MEDIA_SEGMENT_END:
                    // Process current media segment end
                    if (!array_key_exists('group-title', $currentItem)) {
                        $currentItem['group-title'] = $this->currentGroup;
                    }

                    $result[] = $currentItem;

                    $currentItem = array();
                    $currentState = self::STATE_PROCESS_TAGS;
                    $stream->next();
                    break;

                case self::STATE_COMMENT:
                    // Process any other content as comment and delete this
                    $currentState = self::STATE_PROCESS_TAGS;
                    $stream->next();
                    break;
            }
        }

        // Save the current item
        if (!empty($currentItem)) {
            $result[] = $currentItem;
        }

        return $result;
    }

    /**
     * @param string $line
     * @return array <string,array>
     */
    private function processExtInfTag(string $line): array
    {
        $result = array();

        // Parse EXTINF tag attributes
        $pattern = '/([\w-]+\s*=\s*"[^"]*"|[\w-]+\s*=\s*[^"\s]+)\s*/iu';
        preg_match_all($pattern, $line, $matches);

        if (count($matches) > 1) {
            $res = array();
            // Split attributes in string to key:value
            // 'explode' doesn't work because there may be other delimiters in the string (=)
            foreach ($matches[1] as $attrline) {
                $pos = stripos($attrline, '=');
                $attrName = substr($attrline, 0, $pos);
                $attrValue = substr($attrline, $pos + 1);
                $res[$attrName] = $attrValue;
            }
            $result['attributes'] = $res;
        }


        // Remove attributes from string line
        $line = preg_replace($pattern, "", $line);

        // Parse EXTINF tag title and duration in clean sting line (removed attributes)
        $pattern = '/:\s*(-?[\d.]+)\s*,\s*(.*)/iu';
        preg_match_all($pattern, $line, $matches);

        if (count($matches) >= 2) {
            $result['duration'] = floatval($matches[1][0]);
            $result['title'] = stripslashes(trim($matches[2][0], '"'));
        }

        return $result;
    }

    /**
     * @param array $currentItem
     * @return array
     */
    private function processExtInfAttributes(array $currentItem): array
    {
        // Check if attributes exists
        if (!array_key_exists('attributes', $currentItem)) {
            $currentItem['attributes'] = [];
        }

        // Process 'group-title' attribute
        if (array_key_exists('group-title', $currentItem['attributes'])) {
            $currentItem['group-title'] = $currentItem['attributes']['group-title'];
        }
        // Process 'group-title' attribute
        if (array_key_exists('tvg-id', $currentItem['attributes'])) {
            $currentItem['tvg-id'] = $currentItem['attributes']['tvg-id'];
        } else {
            $currentItem['tvg-id'] = $this->generateTvgIdFromTitle($currentItem['title']);
        }

        return $currentItem;
    }

    /**
     * @param string $line
     * @return string
     */
    private function processExtGrpTag(string $line): string
    {
        return stripslashes(trim(substr($line, strlen('#EXTGRP:')), '".'));
    }

    /**
     * @param string $currentLine
     * @return string
     */
    private function processExtM3uHeader(string $currentLine): string
    {
        return $currentLine;
    }

    /**
     * @param string $currentLine
     * @return string
     */
    private
    function processExtTvTag(string $currentLine): string
    {
        return $currentLine;
    }

    /**
     * @param string $str
     * @return string
     */
    private
    function generateTvgIdFromTitle(string $str): string
    {
        $rus = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я', ' ');
        $lat = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya', ' ');

        $str = str_replace($rus, $lat, $str); // переводим на английский
        $str = str_replace('-', '', $str); // удаляем все исходные "-"
        return preg_replace('/[^A-Za-z0-9-]+/', '-', $str); // заменяет все спецсимволы и пробелы на "-"
    }


}