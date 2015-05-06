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

class FormatXml extends FormatAbstract {

    // --- Interface Implementation --- //

    /**
     * Parse the provided data string into the provided RESTfmDataAbstract
     * implementation object.
     *
     * @param RESTfmDataAbstract $restfmData
     * @param string $data
     */
    public function parse (RESTfmDataAbstract $restfmData, $data) {
        libxml_use_internal_errors(TRUE);
        $resourceXML = simplexml_load_string($data);
        if (!$resourceXML) {
            $error = '';
            foreach(libxml_get_errors() as $e) {
                $error .= $e->message."\n";
            }
            throw new ResponseException($error, Response::BADREQUEST);
        }

        // Convert XML Record Names back into arrays.
        // Our specification is that Record Names are always "row".
        //  @see FormatXml::_writeSection()
        foreach ($resourceXML as $sectionXML) {
            foreach ($sectionXML as $childElement) {
                if (strtolower($childElement->getName()) == 'row') {
                    // Two dimensional section of rows.
                    $rowData = array();
                    foreach ($childElement as $field) {
                        $rowData[(string) $field['name']] = (string) $field;
                    }
                    $restfmData->setSectionData($sectionXML->getName(),
                                                (string) $childElement['name'],
                                                $rowData);
                } elseif (strtolower($childElement->getName()) == 'field') {
                    // Single dimensional section of name=>value pairs.
                    $restfmData->setSectionData($sectionXML->getName(),
                                                (string) $childElement['name'],
                                                (string) $childElement);
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
        $xml = new XmlWriter();
        $xml->openMemory();
        if (RESTfmConfig::getVar('settings', 'formatNicely')) {
            $xml->setIndent(TRUE);
        }
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('resource');
        $xml->writeAttribute('xmlns', 'http://www.restfm.com');
        // Deprecated. We don't use xlink now as hrefs are now first class
        // meta entities.
        //$xml->writeAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');

        foreach ($restfmData->getSectionNames() as $sectionName) {
            $xml->startElement($sectionName);
            $this->_writeSection($xml, $restfmData, $sectionName);
            $xml->endElement();
        }

        $xml->endElement();

        return $xml->outputMemory(TRUE);
    }

    // --- Protected Methods --- //

    /**
     * Write the named section into the provided XmlWriter object.
     *
     * @param[out] XmlWriter $xml
     *  An initialised XmlWriter object ref.
     * @param[in] RESTfmDataAbstract $restfmData
     *  Input data object.
     * @param string $sectionName.
     *  Name of section to render.
     */
    protected function _writeSection(XMLWriter $xml, RESTfmDataAbstract $restfmData, $sectionName) {
        if ($restfmData->getSectionDimensions($sectionName) == 2) {
            $restfmData->setIteratorSection($sectionName);
            foreach ($restfmData as $row) {
                // We inject a "Record Name" for XML representations of
                // tables. We use "row" as the Record Name.
                // http://www.w3.org/XML/RDB.html
                $xml->startElement('row');
                self::_row2xml($xml, $row);
                $xml->endElement();
            }
        } else {
            self::_row2xml($xml, $restfmData->getSection($sectionName));
        }
    }

    /**
     * Convert a one-dimensional associative array (a single row) into XML in
     * the provided XMLWriter document.
     *
     * @param[out] XMLWriter $xml
     *   XMLWriter object identifier.
     * @param[in] array $assoc
     *   Associative array to convert.
     */
    protected function _row2xml(XMLWriter $xml, array $assoc) {
        foreach($assoc as $key => $val) {
            $xml->startElement('field');
                $xml->writeAttribute('name', $key);
                if (is_array($val)) {
                    self::_array2xml($xml, $val);
                } else {
                    $xml->text($val);
                }
            $xml->endElement();
        }
    }

    /**
     * Convert an array into XML in the provided XMLWriter document.
     *
     * @param[out] XMLWriter $xml
     *   XMLWriter object identifier.
     * @param[in] array $a
     *   Array to convert.
     */
    protected function _array2xml(XMLWriter $xml, array $a) {
        foreach($a as $val) {
            $xml->startElement('field');
                $xml->writeAttribute('name', $val);
                if (is_array($val)) {
                    self::_array2xml($xml, $val);
                }
            $xml->endElement();
        }
    }

}
