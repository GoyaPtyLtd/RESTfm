<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2015 Goya Pty Ltd.
 *
 * @license
 *  Licensed under The MIT License. For full copyright and license information,
 *  please see the LICENSE file distributed with this package.
 *  Redistributions of files must retain the above copyright notice.
 *
 * @link
 *  http://restfm.com
 *
 * @author
 *  Gavin Stewart
 */

class FormatJson extends FormatAbstract {

    // --- Interface Implementation --- //

    public function parse (RESTfmDataAbstract $restfmData, $data) {
        $a = json_decode($data, TRUE);
        foreach ($a as $sectionName => $sectionData) {

            if ($this->_is_assoc($sectionData)) {           // Associative is
                $restfmData->addSection($sectionName, 1);   // one dimension.
                foreach ($sectionData as $key => $val) {
                    $restfmData->setSectionData($sectionName, $key, $val);
                }
            } else {                                        // Indexed is
                $restfmData->addSection($sectionName, 2);   // two dimensions.
                foreach ($sectionData as $val) {
                    $restfmData->setSectionData($sectionName, NULL, $val);
                }
            }

        }
    }

    public function write (RESTfmDataAbstract $restfmData) {
        $tables = $this->_collate($restfmData);
        if (RESTfmConfig::getVar('settings', 'formatNicely')) {
            return $this->_json_encode_pretty($tables);
        } else {
            return json_encode($tables);
        }
    }

    /**
     * JSON pretty printer.
     *
     * Not recommended for production use. This will decrease performance.
     *
     * Uses JSON_PRETTY_PRINT where PHP >= 5.4.0 OR a slower internal
     * implementation otherwise.
     *
     * @param mixed $value
     *  Input data to be represented as JSON.
     *
     * @return string
     *  Pretty printed JSON | FALSE on error.
     */
    protected function _json_encode_pretty($value) {

        if (version_compare(phpversion(), '5.4.0', '>=')) {
            // Native PHP implementation.
            return(json_encode($value, JSON_PRETTY_PRINT));
        }

        // Internal implementation.

        // Flat JSON representation.
        $json = json_encode($value);
        if ($json === FALSE) {
            return FALSE;
        }

        // Iterate over JSON string one character at a time.
        $indentDepth = 0;
        $indentString = '    ';
        $inQuote = FALSE;
        $ignoreNext = FALSE;
        $result = '';

        for($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];

            if ($ignoreNext) {
                $result .= $char;
                $ignoreNext = FALSE;
            } else {
                switch($char) {
                    case ':':
                        $result .= $char . (!$inQuote ? ' ' : '');
                        break;

                    case '{':
                        if (!$inQuote) {
                            $indentDepth++;
                            $result .= $char . "\n" . str_repeat($indentString, $indentDepth);
                        } else {
                            $result .= $char;
                        }
                        break;

                    case '}':
                        if (!$inQuote) {
                            $indentDepth--;
                            $result .= "\n" . str_repeat($indentString, $indentDepth) . $char;
                        } else {
                            $result .= $char;
                        }
                        break;

                    case '[':
                        if (!$inQuote) {
                            $indentDepth++;
                            $result .= $char . "\n" . str_repeat($indentString, $indentDepth);
                        } else {
                            $result .= $char;
                        }
                        break;

                    case ']':
                        if (!$inQuote) {
                            $indentDepth--;
                            $result .= "\n" . str_repeat($indentString, $indentDepth) . $char;
                        } else {
                            $result .= $char;
                        }
                        break;

                    case ',':
                        if (!$inQuote) {
                            $result .= $char . "\n" . str_repeat($indentString, $indentDepth);
                        } else {
                            $result .= $char;
                        }
                        break;

                    case '"':
                        $inQuote = !$inQuote;
                        $result .= $char;
                        break;

                    case '\\':
                        if ($inQuote) {
                            $ignoreNext = TRUE;
                        }
                        $result .= $char;
                        break;

                    default:
                        $result .= $char;
                }
            }
        }

        return $result;
    }
}
