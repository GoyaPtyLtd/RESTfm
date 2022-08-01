<?php
/**
 * RESTfm - FileMaker RESTful Web Service
 *
 * @copyright
 *  Copyright (c) 2011-2017 Goya Pty Ltd.
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

namespace RESTfm\Format;

use RESTfm\FormatInterface;
use RESTfm\Message\Message;

class FormatDict implements FormatInterface {

    // --- Interface Implementation --- //

    /**
     * Parse the provided data string into the provided \RESTfm\Message\Message
     * implementation object.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     * @param string $data
     */
    public function parse (Message $restfmMessage, $data) {
        // Data is key/value pairs and may be two dimensional:
        // <:sectionNameN:=value:><:sectionNameN+1:=value:>
        // OR one dimensional:
        // <:sectionName:=value:>
        //
        // Where 'value' in both cases is further encoded dict
        // fieldname/value pairs.

        // Decode top level pairs.
        $pairsTop = array();
        $this->_decodeDictPairs($pairsTop, $data);

        $sectionData = array();

        // Step through pairs identifying multi-dimensional records
        // by a numerical suffix on the section name.
        foreach ($pairsTop as $sectionName => $value) {
            $matches = array();
            if (preg_match('/^(.+?)\d+$/', $sectionName, $matches)) {
                // Two dimensional row data.
                $sectionName = $matches[1];
                $rowData = array();
                $this->_decodeDictPairs($rowData, $value);
                if (!isset($sectionData[$sectionName])) {
                    $sectionData[$sectionName] = array();
                }
                $sectionData[$sectionName][] = $rowData;
            } else {
                // Single dimensional section data.
                $sectionData[$sectionName] = array();
                $this->_decodeDictPairs($sectionData[$sectionName], $value);
            }
        }

        $restfmMessage->importArray($sectionData);
    }

    /**
     * Write the provided \RESTfm\Message\Message object into a formatted string.
     *
     * @param \RESTfm\Message\Message $restfmMessage
     *
     * @return string
     */
    public function write (Message $restfmMessage) {
        $sectionNames = $restfmMessage->getSectionNames();

        // Prioritise some sections above others. This priority is taken
        // from the original dict_export.xslt.
        $sectionPriority = function ($a, $b) {
            // Sort as 'metaField', 'data', 'info', <any other>.
            if ($a == 'metaField' ) { return -1; }
            if ($b == 'metaField' ) { return  1; }
            if ($a == 'data') { return -1; }
            if ($b == 'data') { return  1; }
            if ($a == 'info') { return -1; }
            if ($b == 'info') { return  1; }
            return 0;
        };
        usort($sectionNames, $sectionPriority);

        // Render
        $str = '';
        foreach ($sectionNames as $sectionName) {
            $messageSection = $restfmMessage->getSection($sectionName);

            $rowNum = 1;
            foreach ($messageSection->getRows() as $row) {
                $rowStr = '';
                foreach ($row as $fieldName => $fieldValue) {
                    $rowStr .= $this->_renderDictPair($fieldName, $fieldValue);
                }
                if ($messageSection->getDimensions() == 1) {
                    $dictSectionName = $sectionName;
                } else {
                    $dictSectionName = $sectionName.$rowNum;
                }
                $str .= $this->_renderDictPair($dictSectionName, $rowStr);
                $rowNum++;
            }
        }

        return $str;
    }

    // -- Protected -- //

    /**
     * Decode name/value pairs from dict format.
     *
     * @param[out] array &$a
     *  Associative array of decoded name/value pairs.
     * @param[in] string $data
     *  Many key/value pairs in the form
     *  <:name:=value:>[<:name:=value:> ...]
     */
    protected function _decodeDictPairs (array &$a, $data) {
        $pairs = preg_split('/<:|:>\s*/s', $data, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($pairs as $pairStr) {
           list($name, $value) = explode(':=', $pairStr, 2);
           $a[$this->_unescapeFormatChars($name)] = $this->_unescapeFormatChars($value);
        }
    }

    /**
     * Unescape slash prefixed dict format delimiting characters
     * (/=, /:, /<, />)
     *
     * @param string $s
     *
     * @return string
     */
    protected function _unescapeFormatChars ($s) {
        $s = preg_replace('/\/(=|:|<|>)/', '${1}', $s);
        return $s;
    }

    /**
     * Take the name/value pair and render to dict format:
     * <:name:=value:>
     * Any dict format delimiting characters in name/value
     * are escaped.
     *
     * @param string $name
     * @param string $value
     *
     * @return string
     */
    protected function _renderDictPair ($name, $value) {
        if (is_bool($value)) {
            // DataAPI backend now returns bools in field metadata,
            // we need to convert this to an int.
            $value = $value ? 1 : 0;
        }
        $s = '';

        $s .= '<:';
        $s .= $this->_escapeFormatChars($name);
        $s .= ':=';
        $s .= $this->_escapeFormatChars($value);
        $s .= ':>';

        if (\RESTfm\Config::getVar('settings', 'formatNicely')) {
            $s .= "\n";
        }

        return $s;
    }

    /**
     * Escape dict format delimiting characters (=, :, <, >) with a slash.
     * Note: not a backslash.
     *
     * @param string $s
     *
     * @return string
     */
    protected function _escapeFormatChars ($s) {
        $s = preg_replace('/(=|:|<|>)/', '/${1}', $s);
        return $s;
    }

}
