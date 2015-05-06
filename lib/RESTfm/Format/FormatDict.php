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

class FormatDict extends FormatAbstract {

    // --- Interface Implementation --- //

    /**
     * Parse the provided data string into the provided RESTfmDataAbstract
     * implementation object.
     *
     * @param RESTfmDataAbstract $restfmData
     * @param string $data
     */
    public function parse (RESTfmDataAbstract $restfmData, $data) {
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

        // Step through pairs identifying multi-dimensional records
        // by a numerical suffix on the section name.
        foreach ($pairsTop as $sectionName => $value) {
            $matches = array();
            if (preg_match('/^(.+?)\d+$/', $sectionName, $matches)) {
                // Two dimensional row data.
                $sectionName = $matches[1];
                $rowData = array();
                $this->_decodeDictPairs($rowData, $value);
                $restfmData->setSectionData($sectionName, NULL, $rowData);
            } else {
                // Single dimensional section data.
                $sectionData = array();
                $this->_decodeDictPairs($sectionData, $value);
                foreach ($sectionData as $key => $val) {
                    $restfmData->setSectionData($sectionName, $key, $val);
                }
            }
        }
    }

    /**
     * Write the provided RESTfmData object into a formatted string.
     *
     * @param RESTfmDataAbstract $restfmData
     *
     * @return string
     */
    public function write (RESTfmDataAbstract $restfmData) {
        $sections = $this->_collate($restfmData);

        $sectionNames = array_keys($sections);

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
            if ($this->_is_assoc($sections[$sectionName])) {
                // This is an assoc array, render each field.
                $rowStr = '';
                foreach ($sections[$sectionName] as $fieldName => $fieldValue) {
                    $rowStr .= $this->_renderDictPair($fieldName, $fieldValue);
                }
                $str .= $this->_renderDictPair($sectionName, $rowStr);
            } else {
                // !_is_assoc($sections[$sectionName])
                // This is an array of records, render each field in each row.
                $rowNum = 1;
                foreach ($sections[$sectionName] as $row) {
                    $rowStr = '';
                    foreach ($row as $fieldName => $fieldValue) {
                        $rowStr .= $this->_renderDictPair($fieldName, $fieldValue);
                    }
                    $str .= $this->_renderDictPair($sectionName.$rowNum, $rowStr);
                    $rowNum++;
                }
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
        $s = '';

        $s .= '<:';
        $s .= $this->_escapeFormatChars($name);
        $s .= ':=';
        $s .= $this->_escapeFormatChars($value);
        $s .= ':>';

        if (RESTfmConfig::getVar('settings', 'formatNicely')) {
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
