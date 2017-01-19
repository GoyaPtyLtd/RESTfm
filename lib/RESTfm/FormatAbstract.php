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

abstract class FormatAbstract {

    // *** Virtual methods. *** //

    /**
     * Parse the provided data string into the provided RESTfmMessage
     * implementation object.
     *
     * @param RESTfmMessage $restfmMessage
     * @param string $data
     */
    abstract public function parse (RESTfmMessage $restfmMessage, $data);

    /**
     * Write the provided RESTfmMessage object into a formatted string.
     *
     * @param RESTfmMessage $restfmMessage
     *
     * @return string
     */
    abstract public function write (RESTfmMessage $restfmMessage);

    // *** Static data. *** //

    /**
     * @var array
     *  Map of common sectionName => dimensions.
     */
    protected static $_commonDimensions = array (
        'info'  => 1,
    );

    // *** Implemented  methods. *** //

    /**
     * Return the number of dimensions for any commonly known sections.
     * Defaults to 2 if section is not known.
     *
     * @param string $sectionName
     *
     * @return integer
     *  Number of dimensions.
     */
    protected function _getCommonDimension ($sectionName) {
        return isset(self::$_commonDimensions[$sectionName]) ? self::$_commonDimensions[$sectionName] : 2;
    }

    /**
     * Collate all sections into a single array. All two dimensional
     * associative arrays are converted to indexed (the keys are dropped
     * from the first dimension).
     *
     * @param RESTfmMessage $restfmMessage
     *  Input data.
     *
     * @return array
     *  Collated array of all sections in $restfmMessage.
     */
    protected function _collate (RESTfmMessage $restfmMessage) {
        $a = array();

        foreach ($restfmMessage->getSectionNames() as $sectionName) {
            $section = $restfmMessage->getSection($sectionName);
            if ($restfmMessage->getSectionDimensions($sectionName) == 2) {
                $a[$sectionName] = array_values($section);
            } else  {
                $a[$sectionName] = $section;
            }
        }

        return $a;
    }

    /**
     * Check the parameter is an associative array.
     *
     * @param array $arr
     *   Array to check.
     *
     * @return TRUE|FALSE
     *   Returns TRUE on success.
     */
    protected function _is_assoc ($arr) {
            return (is_array($arr) && (!count($arr) || count(array_filter(array_keys($arr),'is_string')) == count($arr)));
    }

};
