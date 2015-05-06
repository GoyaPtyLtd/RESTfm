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

/**
 * .simple format data parser and writer class.
 *
 *  This format is designed to be easy to parse on systems unable to support
 *  more complete formats like JSON and XML.
 *
 *  Export a simplified data format:
 *      - section begins with                   : name
 *      - section separator                     : \n\n
 *      - record separator                      : \n
 *      - field separator                       : &
 *      - field represented as                  : name="value"
 *      - value chars \ ¶ " escaped with        : \
 *      - value char \n translated to           : ¶
 */
class FormatSimple extends FormatAbstract {

    /**
     * Parse the provided data string into the provided RESTfmDataAbstract
     * implementation object.
     *
     * @param RESTfmDataAbstract $restfmData
     *
     * @param string $data
     */
    public function parse (RESTfmDataAbstract $restfmData, $data) {

        // Each section delimited by \n\n, allow for \r\n\r\n, \r\r
        $sections = preg_split('/\n\n|\r\n\r\n|\r\r/', $data, -1, PREG_SPLIT_NO_EMPTY);
        foreach($sections as $section) {
            $this->_parseSection($restfmData, $section);
        }

        // Debugging.
        //ini_set('html_errors', FALSE);
        //var_dump($restfmData);
        //exit();
    }

    /**
     * Write the provided RESTfmData object into a formatted string.
     *
     * @param RESTfmDataAbstract $restfmData
     *
     * @return string
     */
    public function write (RESTfmDataAbstract $restfmData){
        $s = '';    // Final .simple output.

        foreach ($restfmData->getSectionNames() as $sectionName) {
            $s .= $sectionName."\n";
            if ($restfmData->getSectionDimensions($sectionName) == 2) {
                $s .= $this->_encodeRows($restfmData->getSection($sectionName));
            } else {
                $s .= $this->_encodeSingle($restfmData->getSection($sectionName)) . "\n";
            }
            $s .= "\n";
        }

        return $s;
    }

    /**
     * @var Array
     *  Array of unicode line endings to be converted to pilcrows when
     *  writing to .simple format.
     */
    protected static $_lineEndings = array();

    /**
     * @var boolean
     *  Set to TRUE once _initialised.
     */
    protected static $_initialised = FALSE;


    public function __construct() {
        // Initialise class variables.

        if (self::$_initialised === TRUE) {
            return;
        }

        // Set up $_lineEndings array with \n and various unicode equivilents.
        // Need to write unicode as JSON, and decode to get them into PHP.
        $JSONunicodeEndings = array('\u0085', '\u2028', '\u2029');
        foreach ($JSONunicodeEndings as $JSONchar) {
            self::$_lineEndings[] = json_decode('"'.$JSONchar.'"');
        }
        self::$_lineEndings[] = "\n";      // Need to convert plain old newline too.

        self::$_initialised = TRUE;
    }

    /**
     * Parse out a single intact section.
     *
     * @param RESTfmDataAbstract $restfmData
     *  RestfmData object to store section data.
     * @param string $section
     *  .simple encoded string of section including name.
     */
    protected function _parseSection (RESTfmDataAbstract $restfmData, $section) {
        // Allow various line endings.
        $rows = preg_split('/\n|\r\n|\r/', $section, -1, PREG_SPLIT_NO_EMPTY);

        $sectionName = array_shift($rows);  // First line is always the name.

        // We can't determine section dimensions from the .simple format
        // so we need assistance.
        $sectionDimensions = $this->_getCommonDimension($sectionName);

        $restfmData->addSection($sectionName, $sectionDimensions);

        foreach ($rows as $row) {
            $a = $this->_parseRow($row);
            if ($sectionDimensions == 2) {
                $restfmData->setSectionData($sectionName, NULL, $a);
            } else {
                // Break up row into individual fields.
                foreach ($a as $fieldName => $value) {
                    $restfmData->setSectionData($sectionName, $fieldName, $value);
                }
            }
        }
    }

    /**
     * Parse a single row.
     *
     * @param string $row
     *  .simple encoded string of row, with line endings stripped.
     *
     * @return array
     *  Associative array of fieldName => value pairs.
     */
    protected function _parseRow ($row) {
        $a = array();

        // Split multibyte string (UTF8, etc) into an array of multibyte chars.
        $chars = preg_split('/(?<!^)(?!$)/u', $row);

        // State machine to parse char sequence and split out field names and
        // values. Recall the following rules:
        //      - field separator                       : &
        //      - field represented as                  : name="value"
        //      - value chars \ ¶ " escaped with        : \
        //      - value char \n translated to           : ¶
        //
        // States:
        //  0 - in name
        //  1 - in equals, start value
        //  2 - in value
        //  3 - in field separator/delimiter
        $state = 0;
        $fieldName = '';
        $fieldValue = '';
        $escape = FALSE;
        foreach ($chars as $ch) {
            switch($state) {
                case 0:                         // In name.
                    if ($ch == '=') {
                        $state = 1;
                    } else {
                        $fieldName .= $ch;
                    }
                    break;
                case 1:                         // In equals, start value.
                    if ($ch == '"') {
                        $state = 2;
                    }
                    break;
                case 2:                         // In value.
                    if ($escape === TRUE) {     // This char is escaped.
                        $fieldValue .= $ch;
                        $escape = FALSE;
                    } elseif ($ch == '\\') {    // Next char is escaped.
                        $escape = TRUE;
                    } elseif ($ch == '¶') {     // Convert to EOL.
                        $fieldValue .= "\n";
                    } elseif ($ch == '"') {     // End of value.
                        $state = 3;
                    } else {
                        $fieldValue .= $ch;
                    }
                    break;
                case 3:
                    if ($ch == '&') {           // Field separator.
                        $a[$fieldName] = $fieldValue;   // Store field.
                        $state = 0;             // Reset state.
                        $fieldName = '';        // Reset field variables.
                        $fieldValue = '';
                    }
                    break;
                default:
            }
        }
        if ($fieldName != '') {                 // Store last field
            $a[$fieldName] = $fieldValue;
        }

        return($a);
    }

    /**
     * Encode rows of arrays in $data array.
     *
     * @param Array $data
     *  Two dimensional array. Array may be either associative or not.
     *
     * @return String
     */
    protected function _encodeRows ($data) {
        $s = '';
        foreach ($data as $row) {
            $s .= $this->_encodeSingle($row) . "\n";
        }

        return $s;
    }

    /**
     * Encode all elements in single dimensional $data array.
     *
     * @param Array $data
     *  Single dimensional array. Array may be either associative or not.
     *
     * @return String
     */
    protected function _encodeSingle ($data) {
        $fields = array();
        foreach ($data as $fieldName => $value) {
            $fields[] = $fieldName . '="' . $this->_encodeSpecialChars($value) . '"';
        }

        return join('&', $fields);
    }

    /**
     * Encode .simple special characters in $s.
     *
     * @param String $s
     * @return String
     */
    protected function _encodeSpecialChars ($s) {
        // Escape: \ ¶ "
        $s = preg_replace('/(\\\|"|¶)/', '\\\${1}', $s);

        // Convert line endings into pilcrows.
        $s = preg_replace('/' . join('|', self::$_lineEndings) . '/', '¶', $s);

        return $s;
    }

}
