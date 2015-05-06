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

class FormatHtml extends FormatAbstract {

    // --- Interface Implementation --- //

    public function parse (RESTfmDataAbstract $restfmData, $data) {
        // $data is URL encoded key => value pairs as in a HTTP POST body or
        // HTTP GET query string.
        $a = array();
        $this->_parse_str($data, $a);

        $restfmData->addSection('data', 2);
        $restfmData->setSectionData('data', NULL, $a);
    }

    public function write (RESTfmDataAbstract $restfmData) {
        $sections = $this->_collate($restfmData);

        //$str = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">' . "\n";
        $str = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">' . "\n";
        $str .= '<html><head>' . "\n";
        $str .= '<meta http-equiv="Content-type" content="text/html; charset=utf-8">' . "\n";
        $str .= "<title>Response</title>\n";
        $str .= '<link type="text/css" rel="stylesheet" href="' . RESTfmConfig::getVar('settings', 'baseURI') . '/css/RESTfm.css">'."\n";
        $str .= "</head><body>\n";
        $str .= '<div id="logo">' .
                '<a target="_blank" href="http://www.restfm.com"><img width="106" height="36" src="' . RESTfmConfig::getVar('settings', 'baseURI') . '/css/RESTfm.logo.png" alt="RESTfm logo"></a>' .
                '<span>' . Version::getRelease() . '</span>' .
            '</div>' . "\n";

        // Credentials in use.
        if ($this->_username == null) {
            $displayUser = '"" (Guest)';
        } else {
            $displayUser = $this->_username;
        }
        $str .= '<div id="credentials">Username: ' . $displayUser . '<br>'.
                    '[ <a href="' . RESTfmConfig::getVar('settings', 'baseURI') . '?RFMreauth=' . rawurlencode($this->_username) . '">change user</a> ]'.
                '</div>';

        $sectionNames = array_keys($sections);

        // Prioritise some sections above others.
        $sectionPriority = function ($a, $b) {
            // Sort as 'nav', 'data', 'info', <any other>.
            if ($a == 'nav' ) { return -1; }
            if ($b == 'nav' ) { return  1; }
            if ($a == 'data') { return -1; }
            if ($b == 'data') { return  1; }
            if ($a == 'info') { return -1; }
            if ($b == 'info') { return  1; }
            return 0;
        };
        usort($sectionNames, $sectionPriority);

        foreach($sectionNames as $sectionName) {
            $str .= '<h3>'.$sectionName.'</h3>'."\n";
            $str .= '<div id="'.$sectionName.'">'."\n";
            if (count($sections[$sectionName]) <= 0) {
                $str .= '<div class="warn">Warning: no records found.</div>'."\n";
            }
            $str .= "<table>\n";
            if ($this->_is_assoc($sections[$sectionName])) {
                // This is an assoc array, render as field per row.
                $str .= $this->_record2htmlFieldRow($sections[$sectionName]);
            } else {
                // !_is_assoc($sections[$sectionName])
                // This is an array of records, render record per row.
                $str .= '<tr>';
                if ($sectionName == 'data' || $sectionName == 'nav') {
                    $str .= '<th></th>'; // No heading for link column.
                } else {
                    // No link column
                }
                // Pull the field names from the first record for the heading row.
                foreach($sections[$sectionName][0] as $fieldName => $val) {
                    $str .= '<th>'.htmlspecialchars($fieldName).'</th>';
                }
                $str .= "</tr>\n";
                $row_num = 0;
                foreach($sections[$sectionName] as $row) {
                    // Set row id and class.
                    $str .= '<tr id="'.$sectionName.'_'.$row_num.'"';
                    if ($row_num % 2 == 0) {
                        $str .= ' class="alt-colour"';
                    }
                    $str .= '>'."\n";

                    if ($sectionName == 'data' && isset($sections['meta'][$row_num]['href'])) {
                        // Inject meta data href as link in first column of record.
                        $str .= '<td>[ <a href="'.$sections['meta'][$row_num]['href'].'">link</a> ]</td>'."\n";
                    } elseif ($sectionName == 'data') {
                        // Data section with empty link column.
                        $str .= '<td></td>'."\n";
                    } elseif ($sectionName == 'nav') {
                        // Inject href field as link in first column of record.
                        $str .= '<td>[ <a href="'.$row['href'].'">link</a> ]</td>'."\n";
                    } else {
                        // No link column
                    }
                    foreach($row as $fieldName => $val) {
                        $str .= '<td><pre>'.htmlspecialchars($val)."</pre></td>\n";
                    }
                    $str .= "</tr>\n";
                    $row_num++;
                }
            }
            $str .= "</table>\n";
            $str .= "</div>\n";
        }

        $str .= "</body></html>\n";
        return $str;
    }

    // -- Public -- //

    /**
     *  Set username for credentials of current request. Displayed in UI, and
     *  used in "change user" link to force reauthentication.
     */
    public function setUsername ($username) {
        $this->_username = $username;
    }

    // -- Protected -- //

    /**
     * @var string
     *  Username for credentials of current request. Displayed in UI, and
     *  used in "change user" link to force reauthentication.
     */
    protected $_username = NULL;

    /**
     * PHP's parse_str converts dots and spaces to underscores, this one
     * doesn't.
     *
     * @param String $str
     *  Input query string to parse.
     * @param Array &$arr
     *  Array reference for parsed results.
     */
    protected function _parse_str($str, &$arr) {
        if (empty($str)) {
            return;
        }
        $pairs = explode('&', $str);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }
            $pair_array = explode('=', $pair);
            $k = urldecode($pair_array[0]);
            if (isset($pair_array[1])) {
                $arr[$k] = urldecode($pair_array[1]);
            } else {
                $arr[$k] = '';
            }
        }
    }

    /**
     * Convert an associative array (a single record) into a HTML
     * string. Rendered as field per row.
     *
     * @param[in] array $assoc
     *   Associative array to convert.
     */
    protected function _record2htmlFieldRow(array $assoc) {
        $str = '';
        $row_num = 0;
        foreach($assoc as $fieldName => $val) {
            $str .= '<tr><th>'.htmlspecialchars($fieldName).'</th>';
            $alt_colour = '';
            if ($row_num %2 == 0) {
                $alt_colour = ' class="alt-colour"';
            }
            if (is_array($val)) {
                $str .= '<td>';
                $str .= "\n".'<table>';
                $str .= self::_array2htmlItemRow($val);
                $str .= '</table>'."\n";
            } else {
                $str .= '<td'.$alt_colour.'>';
                $str .= htmlspecialchars($val);
                // Convert hrefs into links.
                /*
                if (strpos($fieldName, 'href') === FALSE) {
                    $str .= $val;
                } else {
                    $str .= '<a href="'.$val.'">'.$val."\n";
                }
                */
            }
            $str .= '</td></tr>'."\n";
            $row_num++;
        }

        return $str;
    }

    /**
     * Convert an array into a HTML
     * string. Rendered as item per row.
     *
     * @param[in] array $a
     *   Array to convert.
     */
    protected function _array2htmlItemRow(array $a) {
        $str = '';
        $row_num = 0;
        foreach($a as $val) {
            $str .= '<tr>';
            $alt_colour = '';
            if ($row_num %2 == 0) {
                $alt_colour = ' class="alt-colour"';
            }
            if (is_array($val)) {
                $str .= '<td>';
                $str .= '<table>';
                $str .= self::_array2htmlItemRow($val);
                $str .= '</table>';
            } else {
                $str .= '<td'.$alt_colour.'>';
                $str .= htmlspecialchars($val);
                // Convert hrefs into links.
                /*
                if (strpos($fieldName, 'href') === FALSE) {
                    $str .= $val;
                } else {
                    $str .= '<a href="'.$val.'">'.$val."\n";
                }
                */
            }
            $str .= '</td></tr>'."\n";
            $row_num++;
        }

        return $str;
    }
}
